<?php

return [
    'seed_profile' => env('EVENT_SEED_PROFILE', 'dev'),

    'seed_profiles' => [
        'smoke' => 500,
        'dev' => 10_000,
        'full' => 1_250_000,
    ],

    'seed' => env('EVENT_SEED', 'eventradar-v1'),
    'seed_reference_at' => env('EVENT_SEED_REFERENCE_AT', '2026-07-20T12:00:00Z'),
    'allow_full_seed' => filter_var(env('EVENT_SEED_ALLOW_FULL', false), FILTER_VALIDATE_BOOL),
    'seed_demo_admin' => filter_var(env('EVENT_SEED_DEMO_ADMIN', false), FILTER_VALIDATE_BOOL),
    'seed_owner_count' => 128,
    'seed_payload_bytes' => 1_500,
    'seed_placeholder_budget' => 60_000,
];
