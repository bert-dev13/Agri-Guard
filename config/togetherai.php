<?php

return [
    'api_key' => env('TOGETHER_API_KEY'),
    'model' => env('TOGETHER_MODEL', 'openai/gpt-oss-20b'),
    'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.xyz/v1'),
    'fallback_models' => array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode(',', (string) env('TOGETHER_FALLBACK_MODELS', 'openai/gpt-oss-20b'))
    ))),
];
