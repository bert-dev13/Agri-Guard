<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PsgcBarangayResolver
{
    private const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';
    private const AMULUNG_MUNICIPALITY_CODE = '021504000';
    private const LIST_CACHE_KEY = 'psgc_amulung_barangays_list_v2';
    private const MAP_CACHE_KEY = 'psgc_amulung_barangays_map_v2';
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function getAmulungBarangays(): array
    {
        return Cache::remember(self::LIST_CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $items = self::fetchBarangaysFromApi();
            usort($items, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            return $items;
        });
    }

    /**
     * Resolve farm_barangay into a display name.
     * Accepts either PSGC code or already-stored barangay name.
     */
    public static function resolveName(?string $barangay): ?string
    {
        if ($barangay === null) {
            return null;
        }

        $value = trim($barangay);
        if ($value === '') {
            return null;
        }

        // If legacy records already store text, treat as display name.
        if (preg_match('/[A-Za-z]/', $value) === 1) {
            return $value;
        }

        $map = self::getAmulungBarangayMap();
        if (isset($map[$value]) && trim((string) $map[$value]) !== '') {
            return (string) $map[$value];
        }

        return 'Unknown Barangay';
    }

    public static function formatFarmLocation(?string $barangay, ?string $municipality): string
    {
        $resolvedBarangay = self::resolveName($barangay);
        $municipalityName = trim((string) ($municipality ?? '')) !== ''
            ? trim((string) $municipality)
            : 'Amulung';

        if ($resolvedBarangay !== null) {
            return 'Barangay ' . $resolvedBarangay . ', ' . $municipalityName . ', Cagayan';
        }

        return $municipalityName . ', Cagayan';
    }

    /**
     * @return array<string, string>
     */
    private static function getAmulungBarangayMap(): array
    {
        return Cache::remember(self::MAP_CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $items = self::fetchBarangaysFromApi();
            $map = [];
            foreach ($items as $item) {
                if ($item['code'] !== '') {
                    $map[$item['code']] = $item['name'];
                }
            }
            return $map;
        });
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private static function fetchBarangaysFromApi(): array
    {
        $url = self::PSGC_BASE_URL . '/municipalities/' . self::AMULUNG_MUNICIPALITY_CODE . '/barangays.json';

        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                Log::warning('PSGC API error', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [];
            }

            $items = [];
            foreach ($payload as $item) {
                $items[] = [
                    'code' => (string) ($item['code'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                ];
            }

            return $items;
        } catch (\Throwable $e) {
            Log::warning('PSGC fetch failed', ['message' => $e->getMessage()]);
            return [];
        }
    }
}
