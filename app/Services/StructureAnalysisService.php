<?php

namespace App\Services;

use RuntimeException;
use Throwable;

final class StructureAnalysisService
{
    /**
     * Mirror flood-risk barangay classification used on the map page.
     *
     * @var array<string, string>
     */
    private const FLOOD_RISK_BY_BARANGAY = [
        'abolo' => 'High',
        'alituntung' => 'High',
        'annafatan' => 'High',
        'anquiray' => 'High',
        'babayuan' => 'High',
        'bauan' => 'High',
        'baccuit' => 'High',
        'baculud' => 'High',
        'balauini' => 'High',
        'calamagui' => 'High',
        'casingsingannorte' => 'High',
        'casingsingansur' => 'High',
        'centro' => 'High',
        'dafunganay' => 'High',
        'dugayung' => 'High',
        'estefania' => 'High',
        'gabut' => 'High',
        'jurisdiccion' => 'High',
        'logung' => 'High',
        'marobbob' => 'High',
        'pacacgrande' => 'High',
        'pacacpequeno' => 'High',
        'pacacpequeo' => 'High',
        'palacu' => 'High',
        'palayag' => 'High',
        'unag' => 'High',
        'concepcion' => 'High',
        'tana' => 'High',
        'annabuculan' => 'High',
        'agguirit' => 'High',
        'aggurit' => 'High',
        'goran' => 'High',
        'montealegre' => 'High',
        'dadda' => 'Moderate',
        'cordova' => 'Moderate',
        'calintaan' => 'Moderate',
        'caratacat' => 'Moderate',
        'gangauan' => 'Moderate',
        'magogod' => 'Moderate',
        'manalo' => 'Moderate',
        'masical' => 'Moderate',
        'nangalasauan' => 'Moderate',
        'nabbialan' => 'Low',
        'sanjuan' => 'Low',
        'lasuerte' => 'Low',
        'bacring' => 'Low',
        'backring' => 'Low',
        'nagsabaran' => 'Low',
        'bayabat' => 'Low',
        'nanuccauan' => 'Low',
        'catarauan' => 'Low',
        'cataruan' => 'Low',
    ];

    public function __construct(
        private readonly TogetherAiService $togetherAiService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detectLocation(float $latitude, float $longitude): array
    {
        $location = $this->resolveLocationFromGeoJson($latitude, $longitude);
        $siteConditions = $this->buildSiteConditions(
            $latitude,
            $longitude,
            (string) $location['barangay'],
            $location['feature_properties'] ?? []
        );

        return [
            'location' => [
                'barangay' => $location['barangay'],
                'city' => $location['city'],
                'province' => $location['province'],
                'latitude' => round($latitude, 7),
                'longitude' => round($longitude, 7),
            ],
            'site_conditions' => $siteConditions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeWithSelectedConditions(
        float $latitude,
        float $longitude,
        string $soilType,
        string $terrain,
        string $windExposure
    ): array {
        $detected = $this->detectLocation($latitude, $longitude);
        $location = $detected['location'];
        $autoSite = $detected['site_conditions'];

        $floodRisk = (string) ($autoSite['flood_risk'] ?? '');
        if (trim($floodRisk) === '') {
            throw new RuntimeException('Flood risk could not be determined for this location.');
        }

        $siteConditions = [
            'soil_type' => $soilType,
            'terrain' => $terrain,
            'flood_risk' => $floodRisk,
            'wind_exposure' => $windExposure,
        ];

        $aiPayload = [
            'barangay' => $location['barangay'],
            'city' => $location['city'],
            'province' => $location['province'],
            'coordinates' => [
                'latitude' => round($latitude, 7),
                'longitude' => round($longitude, 7),
            ],
            'soil_type' => $siteConditions['soil_type'],
            'terrain' => $siteConditions['terrain'],
            'flood_risk' => $siteConditions['flood_risk'],
            'wind_exposure' => $siteConditions['wind_exposure'],
        ];

        $analysis = $this->requestAiAnalysis($aiPayload);

        return [
            'location' => $location,
            'site_conditions' => $siteConditions,
            'analysis' => $analysis,
        ];
    }

    /**
     * @return array{barangay: string, city: string, province: string, feature_properties: array<string, mixed>}
     */
    private function resolveLocationFromGeoJson(float $latitude, float $longitude): array
    {
        $datasetPath = public_path('amulung.json');
        if (! is_file($datasetPath)) {
            throw new RuntimeException('Barangay boundary dataset is missing.');
        }

        try {
            $decoded = json_decode((string) file_get_contents($datasetPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Barangay boundary dataset is invalid.');
        }

        $features = is_array($decoded['features'] ?? null) ? $decoded['features'] : [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry)) {
                continue;
            }

            if ($this->pointInsideGeometry($longitude, $latitude, $geometry)) {
                $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

                return [
                    'barangay' => (string) ($properties['adm4_en'] ?? 'Unknown Barangay'),
                    'city' => (string) ($properties['adm3_en'] ?? 'Amulung'),
                    'province' => (string) ($properties['adm2_en'] ?? 'Cagayan'),
                    'feature_properties' => $properties,
                ];
            }
        }

        return [
            'barangay' => 'Outside mapped barangay boundary',
            'city' => 'Amulung',
            'province' => 'Cagayan',
            'feature_properties' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function pointInsideGeometry(float $lng, float $lat, array $geometry): bool
    {
        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates)) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->pointInsidePolygonRings($lng, $lat, $coordinates);
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (is_array($polygon) && $this->pointInsidePolygonRings($lng, $lat, $polygon)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, array<int, float|int>>>  $rings
     */
    private function pointInsidePolygonRings(float $lng, float $lat, array $rings): bool
    {
        if ($rings === []) {
            return false;
        }

        $insideOuter = $this->pointInsideRing($lng, $lat, $rings[0] ?? []);
        if (! $insideOuter) {
            return false;
        }

        $holes = array_slice($rings, 1);
        foreach ($holes as $hole) {
            if (is_array($hole) && $this->pointInsideRing($lng, $lat, $hole)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ray-casting point-in-polygon.
     *
     * @param  array<int, array<int, float|int>>  $ring
     */
    private function pointInsideRing(float $lng, float $lat, array $ring): bool
    {
        if (count($ring) < 3) {
            return false;
        }

        $inside = false;
        $j = count($ring) - 1;
        for ($i = 0; $i < count($ring); $i++) {
            $xi = (float) ($ring[$i][0] ?? 0.0);
            $yi = (float) ($ring[$i][1] ?? 0.0);
            $xj = (float) ($ring[$j][0] ?? 0.0);
            $yj = (float) ($ring[$j][1] ?? 0.0);

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi));

            if ($intersects) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /**
     * @param  array<string, mixed>  $featureProperties
     * @return array{soil_type: string, terrain: string, flood_risk: string, wind_exposure: string}
     */
    private function buildSiteConditions(float $latitude, float $longitude, string $barangay, array $featureProperties): array
    {
        $floodRisk = $this->floodRiskFromBarangay($barangay);

        return [
            'soil_type' => $this->soilTypeFromCoordinates($latitude, $longitude),
            'terrain' => $this->terrainFromCoordinates($latitude, $longitude),
            'flood_risk' => $featureProperties !== [] ? $floodRisk : 'Unknown (outside mapped boundary)',
            'wind_exposure' => $this->windExposureFromCoordinates($latitude, $longitude),
        ];
    }

    private function soilTypeFromCoordinates(float $latitude, float $longitude): string
    {
        $bucket = (int) floor(abs($latitude * 1000 + $longitude * 500)) % 3;

        return match ($bucket) {
            0 => 'Clay loam',
            1 => 'Sandy loam',
            default => 'Silty loam',
        };
    }

    private function terrainFromCoordinates(float $latitude, float $longitude): string
    {
        $bucket = (int) floor(abs($latitude * 100 + $longitude * 100)) % 3;

        return match ($bucket) {
            0 => 'Low-lying plain',
            1 => 'Gently sloping',
            default => 'Undulating',
        };
    }

    private function floodRiskFromBarangay(string $barangay): string
    {
        $canonical = strtolower(preg_replace('/[^a-z0-9]/', '', $barangay) ?? '');

        return self::FLOOD_RISK_BY_BARANGAY[$canonical] ?? 'Unknown';
    }

    private function windExposureFromCoordinates(float $latitude, float $longitude): string
    {
        $bucket = (int) floor(abs($latitude * 1000 - $longitude * 1000)) % 3;

        return match ($bucket) {
            0 => 'High',
            1 => 'Moderate',
            default => 'Low',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestAiAnalysis(array $payload): array
    {
        $systemPrompt = <<<'TXT'
You are a practical agricultural advisor and farm planner.
Your job is to give clear, simple, actionable advice for farmers.
Return valid JSON only (no markdown, no extra keys).

Schema:
{
  "land_use_classification": "Crop Zone|Livestock Zone|Mixed Zone|Restricted Zone",
  "engineering_summary": {
    "key_risks": ["string", "string"],
    "best_development_strategy": "string"
  },
  "structure_recommendations": [
    {
      "structure_name": "string",
      "suitability_level": "Recommended|Conditional|Not Recommended",
      "engineering_explanation": "string",
      "design_requirements": {
        "foundation_type": "string",
        "elevation_level": "string",
        "drainage_needs": "string",
        "wind_resistance_measures": "string",
        "material_suggestions": "string"
      }
    }
  ]
}

Rules:
- Use ONLY the provided input data (barangay, soil, terrain, flood risk, wind).
- Keep language simple and farmer-friendly.
- No technical engineering wording (avoid terms like pile foundation, shear walls, complex structural jargon).
- Keep each explanation short and easy to understand.
- Provide 3 to 5 structure_recommendations only.
- For suitability_level mapping use:
  - "Recommended"
  - "Use with Caution"
  - "Not Recommended"
- In engineering_explanation, write simple "Why" reasoning based on soil, terrain, flood, and wind.
- In design_requirements fields, write practical "What to Do" actions in plain language:
  - foundation_type: plain support/base advice (non-technical)
  - elevation_level: simple raise/height advice when needed
  - drainage_needs: practical water flow/drainage advice
  - wind_resistance_measures: simple wind-protection actions
  - material_suggestions: practical material choice guidance
- engineering_summary:
  - key_risks must contain 2 to 3 short bullet-like risk statements
  - best_development_strategy must be one short practical priority statement
- Ensure all fields are non-empty.
TXT;

        $userInstruction = 'Give practical farm structure recommendations using simple language. Keep it easy to follow for farmers and focused on what to do.';

        try {
            $result = $this->togetherAiService->generateRecommendation($payload, $systemPrompt, $userInstruction);
            $raw = (string) ($result['raw_content'] ?? '');
            $decoded = $this->decodeJsonObject($raw);
            if ($decoded === null) {
                return $this->buildFallbackAnalysis($payload);
            }

            $normalized = $this->normalizeAiResponse($decoded);
            if ($normalized === null) {
                return $this->buildFallbackAnalysis($payload);
            }

            return $normalized;
        } catch (Throwable) {
            return $this->buildFallbackAnalysis($payload);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trimmed, $matches)) {
            $trimmed = trim((string) ($matches[1] ?? ''));
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        try {
            $decoded = json_decode(substr($trimmed, $start, ($end - $start) + 1), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function normalizeAiResponse(array $decoded): ?array
    {
        $classification = trim((string) ($decoded['land_use_classification'] ?? ''));
        if ($classification === '') {
            $classification = trim((string) ($decoded['land_use_recommendation'] ?? ''));
        }
        $summary = $decoded['engineering_summary'] ?? ($decoded['summary'] ?? []);
        $recommendations = $decoded['structure_recommendations']
            ?? ($decoded['recommendations'] ?? ($decoded['structures'] ?? null));

        if ($classification === '') {
            $classification = 'Mixed Zone';
        }
        if (! is_array($summary)) {
            $summary = [];
        }
        if (! is_array($recommendations)) {
            return null;
        }

        $keyRisks = array_values(array_filter(
            is_array($summary['key_risks'] ?? null) ? $summary['key_risks'] : [],
            static fn ($risk) => is_string($risk) && trim($risk) !== ''
        ));
        $strategy = trim((string) ($summary['best_development_strategy'] ?? ''));
        if ($keyRisks === []) {
            $keyRisks = [
                'Flood and wind conditions can affect structural safety.',
                'Site drainage and base stability must be checked before building.',
            ];
        }
        if ($strategy === '') {
            $strategy = 'Prioritize elevated layouts, drainage, and stable structural placement.';
        }

        $normalizedRecommendations = [];
        foreach ($recommendations as $item) {
            if (is_string($item) && trim($item) !== '') {
                $item = [
                    'structure_name' => trim($item),
                    'suitability_level' => 'Use with Caution',
                    'engineering_explanation' => 'Suitability depends on local flood, wind, soil, and terrain conditions.',
                    'design_requirements' => [],
                ];
            }

            if (! is_array($item)) {
                continue;
            }

            $design = $item['design_requirements'] ?? null;
            if (! is_array($design)) {
                $design = [];
            }

            $whatToDo = trim((string) ($item['what_to_do'] ?? ''));
            $fallbackAction = $whatToDo !== '' ? $whatToDo : 'Adjust this structure based on flood and wind conditions.';
            $suitability = trim((string) ($item['suitability_level'] ?? ''));
            $suitability = $this->normalizeSuitabilityLevel($suitability);
            if ($suitability === '') {
                $suitability = 'Use with Caution';
            }

            $normalizedItem = [
                'structure_name' => trim((string) ($item['structure_name'] ?? '')),
                'suitability_level' => $suitability,
                'engineering_explanation' => trim((string) ($item['engineering_explanation'] ?? '')),
                'design_requirements' => [
                    'foundation_type' => trim((string) ($design['foundation_type'] ?? $fallbackAction)),
                    'elevation_level' => trim((string) ($design['elevation_level'] ?? $fallbackAction)),
                    'drainage_needs' => trim((string) ($design['drainage_needs'] ?? $fallbackAction)),
                    'wind_resistance_measures' => trim((string) ($design['wind_resistance_measures'] ?? $fallbackAction)),
                    'material_suggestions' => trim((string) ($design['material_suggestions'] ?? $fallbackAction)),
                ],
            ];

            if ($normalizedItem['engineering_explanation'] === '') {
                $normalizedItem['engineering_explanation'] = trim((string) ($item['why'] ?? ''));
            }
            if ($normalizedItem['engineering_explanation'] === '') {
                $normalizedItem['engineering_explanation'] = 'Suitability depends on local soil, terrain, flood risk, and wind exposure.';
            }

            $hasEmptyField = $normalizedItem['structure_name'] === ''
                || $normalizedItem['suitability_level'] === ''
                || $normalizedItem['engineering_explanation'] === ''
                || in_array('', $normalizedItem['design_requirements'], true);

            if (! $hasEmptyField) {
                $normalizedRecommendations[] = $normalizedItem;
            }
        }

        $normalizedRecommendations = $this->ensureMinimumRecommendationCount($normalizedRecommendations);

        return [
            'land_use_classification' => $classification,
            'engineering_summary' => [
                'key_risks' => $keyRisks,
                'best_development_strategy' => $strategy,
            ],
            'structure_recommendations' => $normalizedRecommendations,
        ];
    }

    private function normalizeSuitabilityLevel(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        return match ($normalized) {
            'recommended' => 'Recommended',
            'conditional', 'use with caution', 'caution', 'with caution' => 'Use with Caution',
            'not recommended', 'avoid' => 'Not Recommended',
            default => trim($value),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildFallbackAnalysis(array $payload): array
    {
        $floodRisk = strtolower(trim((string) ($payload['flood_risk'] ?? 'unknown')));
        $terrain = strtolower(trim((string) ($payload['terrain'] ?? '')));
        $wind = strtolower(trim((string) ($payload['wind_exposure'] ?? '')));
        $soil = strtolower(trim((string) ($payload['soil_type'] ?? '')));

        $highFlood = str_contains($floodRisk, 'high');
        $moderateFlood = str_contains($floodRisk, 'moderate');
        $steepTerrain = str_contains($terrain, 'steep') || str_contains($terrain, 'undulating');
        $highWind = str_contains($wind, 'high');
        $moderateWind = str_contains($wind, 'moderate');
        $unstableSoil = str_contains($soil, 'sandy') || str_contains($soil, 'silty');

        $keyRisks = [];
        if ($highFlood || $moderateFlood) {
            $keyRisks[] = 'Flooding can affect ground-level structures during heavy rain.';
        }
        if ($highWind || $moderateWind) {
            $keyRisks[] = 'Strong winds can damage roofs and weak wall connections.';
        }
        if ($steepTerrain || $unstableSoil) {
            $keyRisks[] = 'Ground stability may require stronger base preparation and drainage.';
        }
        if ($keyRisks === []) {
            $keyRisks = [
                'Weather changes can still affect long-term structure performance.',
                'Site drainage and foundation stability should be checked before construction.',
            ];
        }

        $recommended = ($highFlood || $moderateFlood)
            ? 'Elevated Livestock Shelter'
            : 'Farm Storage Shed';
        $caution = ($highWind || $moderateWind)
            ? 'Open Processing Hut'
            : 'Poultry House';
        $notRecommended = $highFlood
            ? 'Ground-Level Crop Warehouse'
            : 'Heavy Masonry Farm Office';

        $recommendations = [
            [
                'structure_name' => $recommended,
                'suitability_level' => 'Recommended',
                'engineering_explanation' => 'This option best matches current flood, wind, and terrain conditions when basic protection measures are applied.',
                'design_requirements' => [
                    'foundation_type' => 'Use a compacted and reinforced base suited to local soil.',
                    'elevation_level' => $highFlood || $moderateFlood
                        ? 'Raise floor level above nearby flood-prone ground.'
                        : 'Keep floor slightly elevated from surrounding ground.',
                    'drainage_needs' => 'Provide perimeter drains to keep water away from the structure.',
                    'wind_resistance_measures' => $highWind || $moderateWind
                        ? 'Use strong anchoring and cross-bracing for walls and roof.'
                        : 'Add basic anchoring for roof and corner posts.',
                    'material_suggestions' => 'Use durable, moisture-resistant framing and roofing materials.',
                ],
            ],
            [
                'structure_name' => $caution,
                'suitability_level' => 'Use with Caution',
                'engineering_explanation' => 'This structure can work, but it needs stricter drainage, wind protection, and base preparation.',
                'design_requirements' => [
                    'foundation_type' => 'Prepare a firm base and check settlement regularly.',
                    'elevation_level' => 'Raise working floor above wet spots and runoff paths.',
                    'drainage_needs' => 'Install side drains and maintain clear outflow routes.',
                    'wind_resistance_measures' => 'Reinforce roof edges and main joints with anchors.',
                    'material_suggestions' => 'Choose weather-resistant materials with regular maintenance.',
                ],
            ],
            [
                'structure_name' => $notRecommended,
                'suitability_level' => 'Not Recommended',
                'engineering_explanation' => 'This option has the highest risk under current site conditions and would need major upgrades to perform safely.',
                'design_requirements' => [
                    'foundation_type' => 'Would require extensive base strengthening and soil treatment.',
                    'elevation_level' => 'Would need significant elevation above expected flood levels.',
                    'drainage_needs' => 'Would need large-capacity drainage around the full perimeter.',
                    'wind_resistance_measures' => 'Would require heavy wind bracing and stronger roof connections.',
                    'material_suggestions' => 'Only consider with high-strength materials and expert verification.',
                ],
            ],
        ];

        return [
            'land_use_classification' => $highFlood || $steepTerrain ? 'Mixed Zone' : 'Crop Zone',
            'engineering_summary' => [
                'key_risks' => array_slice($keyRisks, 0, 3),
                'best_development_strategy' => 'Prioritize elevated layouts, strong drainage, and wind anchoring before expanding structures.',
            ],
            'structure_recommendations' => $this->ensureMinimumRecommendationCount($recommendations),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     * @return array<int, array<string, mixed>>
     */
    private function ensureMinimumRecommendationCount(array $recommendations): array
    {
        $fallbackPool = [
            [
                'structure_name' => 'Storage Shed',
                'suitability_level' => 'Use with Caution',
                'engineering_explanation' => 'This structure can be used if drainage and elevation are improved.',
                'design_requirements' => [
                    'foundation_type' => 'Use a stable, compacted base.',
                    'elevation_level' => 'Raise the floor above common wet areas.',
                    'drainage_needs' => 'Provide perimeter drainage channels.',
                    'wind_resistance_measures' => 'Add basic anchoring and cross-support.',
                    'material_suggestions' => 'Use durable, weather-resistant materials.',
                ],
            ],
            [
                'structure_name' => 'Livestock Shelter',
                'suitability_level' => 'Recommended',
                'engineering_explanation' => 'This is a practical option when ventilation, drainage, and anchoring are applied.',
                'design_requirements' => [
                    'foundation_type' => 'Use firm footing with compacted gravel support.',
                    'elevation_level' => 'Keep the floor slightly raised from surrounding ground.',
                    'drainage_needs' => 'Create runoff paths away from pens and feed areas.',
                    'wind_resistance_measures' => 'Secure posts and roof framing against strong winds.',
                    'material_suggestions' => 'Choose rot-resistant wood or treated steel members.',
                ],
            ],
            [
                'structure_name' => 'Processing Hut',
                'suitability_level' => 'Not Recommended',
                'engineering_explanation' => 'This option has lower suitability unless major flood and wind controls are added.',
                'design_requirements' => [
                    'foundation_type' => 'Use reinforced footing with stable base preparation.',
                    'elevation_level' => 'Raise working floor higher than nearby flood-prone points.',
                    'drainage_needs' => 'Add side drains and keep water flow away from the structure.',
                    'wind_resistance_measures' => 'Install diagonal bracing and secure roof edges.',
                    'material_suggestions' => 'Use durable materials suited for wet and windy exposure.',
                ],
            ],
        ];

        $existingNames = [];
        foreach ($recommendations as $item) {
            $name = strtolower(trim((string) ($item['structure_name'] ?? '')));
            if ($name !== '') {
                $existingNames[$name] = true;
            }
        }

        foreach ($fallbackPool as $fallback) {
            if (count($recommendations) >= 3) {
                break;
            }
            $name = strtolower((string) $fallback['structure_name']);
            if (isset($existingNames[$name])) {
                continue;
            }
            $recommendations[] = $fallback;
            $existingNames[$name] = true;
        }

        return $recommendations;
    }
}
