<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TogetherAiService
{
    /**
     * Send farm/weather context to Together AI chat completions endpoint.
     *
     * @param  string|null  $userInstructionOverride  When set, replaces the default crop/weather user preamble (e.g. map-page JSON tasks).
     */
    public function generateRecommendation(array $inputPayload, string $systemPrompt, ?string $userInstructionOverride = null): array
    {
        $apiKey = (string) (config('togetherai.api_key') ?? config('services.togetherai.api_key'));
        $primaryModel = trim((string) (config('togetherai.model') ?? config('services.togetherai.model', '')));
        $fallbackModels = config('togetherai.fallback_models', config('services.togetherai.fallback_models', []));
        $baseUrl = rtrim((string) (config('togetherai.base_url') ?? config('services.togetherai.base_url', 'https://api.together.xyz/v1')), '/');

        if ($apiKey === '' || $primaryModel === '' || $baseUrl === '') {
            throw new RuntimeException('Together AI configuration is incomplete.');
        }

        $encodedInput = json_encode($inputPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $defaultUserPreamble = "Use only this input JSON and no outside assumptions.\n"
            ."If the latest_user_message is a language-support/capability question (e.g., Tagalog/Taglish/English understanding), do not reject it; acknowledge politely in the requested language and ask what farm question they want to ask.\n"
            ."Otherwise, generate recommendations strictly for the given crop_type and growth_stage using weather and rainfall fields.\n"
            ."Do not mention other crops, keep responses short and actionable, and return valid JSON only.\n"
            ."Input JSON:\n";

        $userContent = $userInstructionOverride !== null && trim($userInstructionOverride) !== ''
            ? rtrim($userInstructionOverride)."\n\nInput JSON:\n".$encodedInput
            : $defaultUserPreamble.$encodedInput;

        $modelsToTry = [$primaryModel];
        if (is_array($fallbackModels)) {
            foreach ($fallbackModels as $candidate) {
                if (is_string($candidate)) {
                    $trimmed = trim($candidate);
                    if ($trimmed !== '' && ! in_array($trimmed, $modelsToTry, true)) {
                        $modelsToTry[] = $trimmed;
                    }
                }
            }
        }

        $lastError = 'Together AI request failed.';

        foreach ($modelsToTry as $model) {
            $requestTimestamp = now()->toIso8601String();
            $requestBody = [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
            ];

            try {
                $response = Http::timeout(25)
                    ->connectTimeout(10)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->acceptJson()
                    ->asJson()
                    ->post($baseUrl.'/chat/completions', $requestBody);
            } catch (ConnectionException $e) {
                $lastError = 'Together AI request timed out or network is unavailable.';
                Log::error('Together AI connection failed', [
                    'model' => $model,
                    'requested_at' => $requestTimestamp,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                throw new RuntimeException($lastError, 0, $e);
            } catch (RequestException $e) {
                $status = $e->response?->status();
                $body = $e->response?->body();
                $lastError = trim((string) data_get($e->response?->json(), 'error.message', 'Together AI request failed.'));
                Log::error('Together AI request exception', [
                    'status' => $status,
                    'model' => $model,
                    'requested_at' => $requestTimestamp,
                    'body' => $body,
                    'message' => $e->getMessage(),
                ]);
                throw new RuntimeException($lastError !== '' ? $lastError : 'Together AI request failed.', 0, $e);
            } catch (\Throwable $e) {
                $lastError = 'Unexpected Together AI error.';
                Log::error('Together AI unexpected failure', [
                    'model' => $model,
                    'requested_at' => $requestTimestamp,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                throw new RuntimeException($lastError, 0, $e);
            }

            if (! $response->successful()) {
                $body = $response->body();
                $code = (string) data_get($response->json(), 'error.code', '');
                $lastError = trim((string) data_get($response->json(), 'error.message', 'Together AI request failed.'));

                Log::error('Together AI request failed', [
                    'status' => $response->status(),
                    'model' => $model,
                    'requested_at' => $requestTimestamp,
                    'code' => $code,
                    'body' => $body,
                ]);

                $canRetryModel = $response->status() === 400 && in_array($code, ['model_not_available', 'invalid_model'], true);
                if ($canRetryModel) {
                    continue;
                }

                throw new RuntimeException($lastError !== '' ? $lastError : 'Together AI request failed.');
            }

            $json = $response->json();
            $content = data_get($json, 'choices.0.message.content');
            if (is_array($content)) {
                $parts = array_map(
                    static fn ($chunk): string => is_array($chunk) ? (string) ($chunk['text'] ?? '') : (string) $chunk,
                    $content
                );
                $content = trim(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
            }

            if (! is_string($content) || trim($content) === '') {
                Log::error('Together AI response missing content', [
                    'model' => $model,
                    'requested_at' => $requestTimestamp,
                    'response' => $json,
                ]);
                $lastError = 'Together AI response content is empty.';

                continue;
            }

            return [
                'raw_content' => $content,
                'response' => $json,
                'model_used' => $model,
                'requested_at' => $requestTimestamp,
            ];
        }

        throw new RuntimeException($lastError !== '' ? $lastError : 'Together AI request failed.');
    }
}
