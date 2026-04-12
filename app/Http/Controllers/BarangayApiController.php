<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangayApiController extends Controller
{
    /**
     * JSON list of barangays for dropdowns (single DB source).
     * Optional query: municipality — filter by municipality name.
     */
    public function index(Request $request): JsonResponse
    {
        $municipality = $request->query('municipality');
        $municipality = is_string($municipality) ? trim($municipality) : '';

        return response()->json([
            'barangays' => $this->barangayList($municipality),
        ]);
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

        return response()->json([
            'barangays' => $this->barangayList($municipality),
        ]);
    }

    /**
     * @return list<array{id: string, name: string, municipality: string}>
     */
    private function barangayList(string $municipality): array
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
