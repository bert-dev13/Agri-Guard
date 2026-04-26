<?php

namespace App\Http\Controllers;

use App\Services\StructureAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class StructureAnalysisController extends Controller
{
    public function index(): View
    {
        return view('user.structures.index');
    }

    public function detectLocation(Request $request, StructureAnalysisService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
        ]);

        try {
            $payload = $service->detectLocation((float) $validated['latitude'], (float) $validated['longitude']);

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Structure location detect endpoint failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Location detection is currently unavailable. Please try again.',
            ], 500);
        }
    }

    public function analyze(Request $request, StructureAnalysisService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
            'soil_type' => ['required', 'string', 'in:Clay,Clay Loam,Sandy Loam,Silty Loam,Sandy,Rocky'],
            'terrain' => ['required', 'string', 'in:Flat,Gently Sloping,Undulating,Steep'],
            'wind_exposure' => ['required', 'string', 'in:Low,Moderate,High'],
        ]);

        try {
            $payload = $service->analyzeWithSelectedConditions(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (string) $validated['soil_type'],
                (string) $validated['terrain'],
                (string) $validated['wind_exposure'],
            );

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Structure analysis endpoint failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Structure analysis is currently unavailable. Please try again.',
            ], 500);
        }
    }
}
