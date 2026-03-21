<?php

namespace App\Http\Controllers;

use App\Support\PsgcBarangayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PsgcController extends Controller
{
    /**
     * Fetch barangays of Amulung Municipality from PSGC API.
     * Returns JSON list of barangays sorted alphabetically by name.
     */
    public function amulungBarangays(): JsonResponse
    {
        try {
            $barangays = PsgcBarangayResolver::getAmulungBarangays();

            return response()->json([
                'barangays' => $barangays ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('PSGC API exception', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to load barangays. Please try again later.',
                'barangays' => [],
            ], 502);
        }
    }

    /**
     * Resolve a PSGC barangay code to its display name (Amulung barangays only).
     * Uses cached PSGC data and falls back to a readable label when unresolved.
     */
    public static function getBarangayNameForCode(?string $code): string
    {
        return PsgcBarangayResolver::resolveName($code) ?? '—';
    }
}
