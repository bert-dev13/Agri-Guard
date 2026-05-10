<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BarangayApiController extends Controller
{
    /**
     * Cache TTL for the dropdown list. Barangays change rarely (admin-managed), so a long
     * TTL is safe. The cache key embeds the table's row count + latest updated_at so any
     * write invalidates the cache automatically without explicit busts.
     */
    private const BARANGAY_LIST_CACHE_TTL_HOURS = 24;

    /**
     * Browser cache lifetime sent with the response. Public, immutable-ish data, so we
     * let the browser short-circuit subsequent dropdown fetches.
     */
    private const HTTP_CACHE_MAX_AGE_SECONDS = 600;

    /**
     * JSON list of barangays for dropdowns (single DB source).
     * Optional query: municipality — filter by municipality name.
     */
    public function index(Request $request): JsonResponse
    {
        $municipality = $request->query('municipality');
        $municipality = is_string($municipality) ? trim($municipality) : '';

        return $this->cachedResponse($municipality);
    }

    /**
     * Legacy path: defaults municipality to Amulung when omitted (registration/settings scripts).
     */
    public function amulungBarangays(Request $request): JsonResponse
    {
        $municipality = $request->query('municipality');
        $municipality = is_string($municipality) ? trim($municipality) : '';
        if ($municipality === '') {
            $municipality = 'Amulung';
        }

        return $this->cachedResponse($municipality);
    }

    private function cachedResponse(string $municipality): JsonResponse
    {
        $payload = [
            'barangays' => $this->cachedBarangayList($municipality),
        ];

        return response()->json($payload)
            // Public, cache-friendly: the dropdown shape never personalizes per user.
            ->setPublic()
            ->setMaxAge(self::HTTP_CACHE_MAX_AGE_SECONDS);
    }

    /**
     * @return list<array{id: string, name: string, municipality: string}>
     */
    private function cachedBarangayList(string $municipality): array
    {
        $version = (string) (Barangay::query()->max('updated_at') ?? '0');
        $count = (string) Barangay::query()->count();
        $cacheKey = 'barangay_list:v1:'.md5($municipality.'|'.$version.'|'.$count);

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::BARANGAY_LIST_CACHE_TTL_HOURS),
            fn (): array => $this->buildBarangayList($municipality)
        );
    }

    /**
     * @return list<array{id: string, name: string, municipality: string}>
     */
    private function buildBarangayList(string $municipality): array
    {
        $q = Barangay::query()->orderedByName();
        if ($municipality !== '') {
            $q->where('municipality', $municipality);
        }

        return $q->get(['id', 'name', 'municipality'])->map(static function (Barangay $b): array {
            return [
                'id' => (string) $b->id,
                'name' => $b->name,
                'municipality' => $b->municipality,
            ];
        })->all();
    }
}
