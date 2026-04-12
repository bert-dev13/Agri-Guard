<?php

namespace App\Services;

use App\Models\User;
use App\Services\AiAdvisory\AiAdvisoryService;
use Illuminate\Support\Facades\Log;

class AiRecommendationService
{
    public function __construct(
        private readonly FarmRecommendationService $farmRecommendationService,
        private readonly AiAdvisoryService $aiAdvisoryService,
    ) {}

    /**
     * Build a shared recommendation payload and return a normalized smart recommendation.
     */
    public function generateSmartRecommendation(User $user, array $context, string $pageContext = 'dashboard'): array
    {
        $payload = $this->farmRecommendationService->buildPayload($user, $context);
        $run = $this->aiAdvisoryService->run(AiAdvisoryService::PAGE_DASHBOARD, $user, $payload);
        $recommendation = $this->aiAdvisoryService->formatDashboardCard($run);

        $recommendationMeta = is_array($run['_meta'] ?? null) ? $run['_meta'] : [];
        $recommendationFailed = (($recommendationMeta['ai_status'] ?? 'failed') !== 'success');

        $recommendation['ai_status'] = (string) ($recommendationMeta['ai_status'] ?? 'failed');
        $recommendation['ai_error'] = (string) ($recommendationMeta['error'] ?? '');
        $recommendation['ai_model'] = (string) ($recommendationMeta['model'] ?? config('togetherai.model', config('services.togetherai.model', '')));

        if ($recommendationFailed) {
            Log::warning('AI smart recommendation unavailable', [
                'user_id' => $user->id,
                'page_context' => $pageContext,
                'ai_status' => $recommendation['ai_status'],
                'error' => $recommendation['ai_error'],
            ]);
        }

        return [
            'recommendation' => $recommendation,
            'failed' => $recommendationFailed,
            'raw' => $run,
        ];
    }
}
