<?php

/**
 * Per-crop stage lengths in days (sequential, cumulative timeline).
 * Extend by adding keys under `crops` and mapping display names in CropTimelineService::cropConfigKey().
 *
 * Rice bands align roughly with agronomic windows:
 * planting + early_growth ≈ seedling establishment, then vegetative, reproductive (flowering), harvest.
 */
return [
    'crops' => [
        'rice' => [
            'planting' => 7,
            'early_growth' => 14,
            'vegetative' => 30,
            'flowering' => 30,
            'harvest' => 60,
        ],
        'corn' => [
            'planting' => 8,
            'early_growth' => 16,
            'vegetative' => 20,
            'flowering' => 18,
            'harvest' => 30,
        ],
    ],
    'default_crop_key' => 'rice',
];
