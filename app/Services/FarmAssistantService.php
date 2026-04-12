<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class FarmAssistantService
{
    private const LANG_EN = 'en';

    private const LANG_TL = 'tl';

    private const LANG_TAGLISH = 'taglish';

    private const LANG_OTHER = 'other';

    public function __construct(
        private readonly TogetherAiService $togetherAiService,
        private readonly FarmWeatherService $farmWeatherService,
        private readonly FloodRiskAssessmentService $floodRiskService,
        private readonly RainfallHeatmapService $rainfallHeatmapService,
        private readonly CropTimelineService $cropTimelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildAssistantContext(User $user): array
    {
        $stageKey = $this->cropTimelineService->normalizeStageKey((string) ($user->farming_stage ?? ''));
        $stageLabel = CropTimelineService::STAGE_LABELS[$stageKey] ?? 'Not set';
        $cropType = trim((string) ($user->crop_type ?? ''));
        $locationDisplay = trim((string) ($user->farm_location_display ?? ''));

        $lat = is_numeric($user->farm_lat ?? null) ? (float) $user->farm_lat : null;
        $lng = is_numeric($user->farm_lng ?? null) ? (float) $user->farm_lng : null;
        $hasGps = $lat !== null && $lng !== null;

        $weather = null;
        $flood = ['level' => 'LOW', 'label' => 'Low Risk', 'message' => 'No strong flood signal right now.'];
        $rainfallLabel = 'Low';
        $rainfallMm = null;
        $rainfallPop = null;

        if ($hasGps) {
            $weather = $this->farmWeatherService->getNormalizedWeatherByCoordinates($lat, $lng);
            $flood = $this->floodRiskService->assess($weather, []);
            $rainfallLabel = $this->rainfallHeatmapService->intensityLabel($weather);
            $rainfallMm = isset($weather['today_expected_rainfall']) && is_numeric($weather['today_expected_rainfall'])
                ? (float) $weather['today_expected_rainfall']
                : null;
            $rainfallPop = isset($weather['today_rain_probability']) && is_numeric($weather['today_rain_probability'])
                ? (int) $weather['today_rain_probability']
                : null;
        }

        $sessionLang = (string) session('assistant_language_preference', self::LANG_EN);
        $sessionLang = in_array($sessionLang, [self::LANG_EN, self::LANG_TL, self::LANG_TAGLISH], true)
            ? $sessionLang
            : self::LANG_EN;

        return [
            'crop_type' => $cropType !== '' ? $cropType : 'Not set',
            'growth_stage' => $stageLabel,
            'growth_stage_key' => $stageKey,
            'barangay' => $user->farm_barangay_name ?: '—',
            'municipality' => $user->farm_municipality ?: '—',
            'location_display' => $locationDisplay !== '' ? $locationDisplay : '—',
            'has_gps' => $hasGps,
            'weather' => $weather,
            'current_weather_text' => $this->weatherLine($weather),
            'rainfall_level' => $rainfallLabel,
            'rainfall_mm' => $rainfallMm,
            'rainfall_probability' => $rainfallPop,
            'flood_risk' => $flood,
            'temperature_c' => is_numeric($weather['current_temperature'] ?? null) ? (float) $weather['current_temperature'] : null,
            'humidity' => is_numeric($weather['humidity'] ?? null) ? (int) $weather['humidity'] : null,
            'current_date_human' => now()->format('l, F j, Y'),
            'assistant_language_preference' => $sessionLang,
        ];
    }

    /**
     * @param  array<int, array{role:string,content:string}>  $history
     * @return array<string,mixed>
     */
    public function answer(User $user, string $message, array $history, array $context): array
    {
        $normalized = trim($message);
        if ($normalized === '') {
            throw new RuntimeException('Please type your question first.');
        }

        $lang = $this->languageContextForMessage($normalized);
        $effectiveLanguage = (string) ($lang['effective_language'] ?? self::LANG_EN);

        $payload = [
            'language_context' => $lang,
            'farm_context' => [
                'crop_type' => $context['crop_type'] ?? 'Not set',
                'crop_stage' => $context['growth_stage'] ?? 'Not set',
                'location' => $context['location_display'] ?? '—',
                'barangay' => $context['barangay'] ?? '—',
                'date' => $context['current_date_human'] ?? now()->format('l, F j, Y'),
            ],
            'weather_context' => [
                'condition' => data_get($context, 'weather.condition', 'Unknown'),
                'rain_chance_percent' => $context['rainfall_probability'] ?? null,
                'rainfall_mm' => $context['rainfall_mm'] ?? null,
                'flood_risk' => data_get($context, 'flood_risk.label', 'Low Risk'),
                'temperature_c' => $context['temperature_c'] ?? null,
                'humidity' => $context['humidity'] ?? null,
            ],
            'chat' => [
                'history' => $history,
                'latest_user_message' => $normalized,
            ],
        ];

        try {
            $result = $this->togetherAiService->generateRecommendation($payload, $this->assistantPrompt());
            $decoded = $this->decode((string) ($result['raw_content'] ?? ''));
            $text = is_array($decoded) ? trim((string) ($decoded['message'] ?? '')) : '';
            if ($text === '') {
                throw new RuntimeException('Malformed AI response');
            }

            return [
                'message' => $text,
                'meta' => [
                    'source' => 'ai_chat',
                    'language' => $effectiveLanguage,
                    'fallback_mode' => false,
                    'ai_model' => (string) ($result['model_used'] ?? ''),
                ],
            ];
        } catch (\Throwable) {
            return [
                'message' => $this->basicFallbackReply($context, $effectiveLanguage),
                'meta' => [
                    'source' => 'basic_fallback',
                    'language' => $effectiveLanguage,
                    'fallback_mode' => true,
                    'fallback_note' => 'Using basic farm guidance',
                ],
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function starterMessage(array $context): array
    {
        $lang = (string) ($context['assistant_language_preference'] ?? self::LANG_EN);
        $message = match ($lang) {
            self::LANG_TL => 'Kumusta! Ako ang AgriGuard Assistant. Magtanong ka lang tungkol sa tanim, ulan, baha, pagdidilig, o pag-spray at tutulungan kitang magdesisyon para sa farm mo.',
            self::LANG_TAGLISH => 'Hi! I am your AgriGuard Assistant. Ask anything about your farm and I will help you decide based on your crop and weather context.',
            default => 'Hi! I am your AgriGuard Assistant. Ask anything about your farm and I will help you decide using your crop and weather context.',
        };

        return [
            'message' => $message,
            'meta' => [
                'source' => 'context_starter',
                'language' => $lang,
                'fallback_mode' => false,
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function languageContextForMessage(string $latestUserMessage): array
    {
        $m = mb_strtolower(trim($latestUserMessage), 'UTF-8');

        $sessionPref = session('assistant_language_preference', null);
        $sessionPref = is_string($sessionPref) ? $sessionPref : null;
        $sessionPref = in_array($sessionPref, [self::LANG_EN, self::LANG_TL, self::LANG_TAGLISH], true) ? $sessionPref : null;

        $explicit = null;
        if (preg_match('/\btaglish\b/i', $m)) {
            $explicit = self::LANG_TAGLISH;
        } elseif (preg_match('/\btagalog\b/i', $m)) {
            $explicit = self::LANG_TL;
        } elseif (preg_match('/\benglish\b/i', $m)) {
            $explicit = self::LANG_EN;
        }

        $tagalogPatterns = ['/paano/i', '/dapat/i', '/gawin/i', '/tanim/i', '/ulan/i', '/baha/i', '/ngayon/i'];
        $englishPatterns = ['/can/i', '/should/i', '/what/i', '/how/i', '/today/i', '/plant/i', '/spray/i', '/water/i', '/flood/i'];

        $tagCount = 0;
        foreach ($tagalogPatterns as $p) {
            if (preg_match($p, $m) === 1) {
                $tagCount++;
            }
        }
        $enCount = 0;
        foreach ($englishPatterns as $p) {
            if (preg_match($p, $m) === 1) {
                $enCount++;
            }
        }

        $detected = self::LANG_OTHER;
        if ($explicit !== null) {
            $detected = $explicit;
        } elseif ($tagCount > 0 && $enCount > 0) {
            $detected = self::LANG_TAGLISH;
        } elseif ($tagCount > 0) {
            $detected = self::LANG_TL;
        } elseif ($enCount > 0) {
            $detected = self::LANG_EN;
        }

        if ($detected === self::LANG_OTHER) {
            $effective = $sessionPref ?? self::LANG_EN;
        } else {
            $effective = $detected;
            session(['assistant_language_preference' => $detected]);
        }

        return [
            'detected_language' => $effective,
            'effective_language' => $effective,
            'session_language' => $effective,
        ];
    }

    private function assistantPrompt(): string
    {
        return <<<'PROMPT'
You are AgriGuard Assistant, a conversational farm companion.

Goals:
- Reply naturally like a real chat assistant, not a form.
- Mirror the user language and style (English, Tagalog, Taglish, or other language).
- Keep answers short to medium, practical, and friendly.
- Quietly use payload.farm_context + payload.weather_context for grounded advice.
- Avoid robotic formatting, headings, bullet lists, and rigid templates.
- Use simple words and explain technical ideas in plain language.

Important:
- Never invent weather or farm facts outside payload.
- If some context is missing, say it briefly and still give the best safe guidance.
- Keep continuity with follow-up questions.

Return strict JSON only:
{
  "message": "natural conversational reply text"
}
PROMPT;
    }

    private function basicFallbackReply(array $context, string $lang): string
    {
        $rain = is_numeric($context['rainfall_probability'] ?? null) ? (int) $context['rainfall_probability'] : null;
        $flood = (string) data_get($context, 'flood_risk.label', 'Low Risk');
        $weather = (string) data_get($context, 'weather.condition', 'Unknown');
        $stage = (string) ($context['growth_stage'] ?? 'current stage');

        if ($lang === self::LANG_TL) {
            return 'May pansamantalang issue sa AI kaya basic farm guidance muna. Sa ngayon, '.$weather.' ang kondisyon at '.$flood.' ang flood advisory. Mas safe kung unahin mo ang field check at drainage, tapos i-adjust ang pagdidilig o pag-spray base sa ulan'.($rain !== null ? " ({$rain}% chance)." : '.');
        }
        if ($lang === self::LANG_TAGLISH) {
            return 'May temporary AI issue, so basic farm guidance muna. Right now, weather is '.$weather.' with '.$flood.' flood advisory. Mas safe if unahin mo ang field check and drainage, then adjust watering or spraying based on rain'.($rain !== null ? " ({$rain}% chance)." : '.');
        }

        return 'There is a temporary AI issue, so I am using basic farm guidance. Right now, weather is '.$weather.' with '.$flood.' flood advisory. The safer move is to start with field and drainage checks, then adjust watering or spraying based on rain'.($rain !== null ? " ({$rain}% chance)." : '.').' Keep actions appropriate for your '.$stage.'.';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = preg_replace('/^```json\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $m) === 1) {
            $decoded = json_decode((string) $m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function weatherLine(?array $weather): string
    {
        if (! is_array($weather)) {
            return 'Weather unavailable';
        }

        $temp = is_numeric($weather['current_temperature'] ?? null)
            ? round((float) $weather['current_temperature']).'°C'
            : '—';
        $condition = trim((string) ($weather['condition'] ?? 'Unknown'));

        return "{$temp} · {$condition}";
    }
}
