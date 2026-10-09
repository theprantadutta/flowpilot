<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Which AI provider FlowPilot talks to. Every AI feature goes through
    | App\Support\Ai\AiOperationsService, so the provider can be replaced
    | without touching features. AI features stay off until the provider has
    | its credentials.
    |
    */

    'provider' => env('AI_PROVIDER', 'freeway'),

    'providers' => [
        'freeway' => [
            'url' => rtrim((string) env('FREEWAY_URL', 'https://freeway.pranta.dev'), '/'),
            // Project API key, sent as the X-Api-Key header. Never logged or sent to the browser.
            'key' => env('FREEWAY_API_KEY'),
            'model' => env('FREEWAY_MODEL', 'paid:premium'),
            'reasoning_effort' => env('FREEWAY_REASONING_EFFORT', 'low'),
            // Freeway may try several models before answering; give it time.
            'timeout' => (int) env('FREEWAY_TIMEOUT', 180),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Operations brief
    |--------------------------------------------------------------------------
    |
    | Limits on how often briefs are written, per member and per organization,
    | and how much is put in front of the model.
    |
    */

    'brief' => [
        'per_member_per_hour' => (int) env('AI_BRIEF_PER_MEMBER_PER_HOUR', 6),
        'per_organization_per_day' => (int) env('AI_BRIEF_PER_ORGANIZATION_PER_DAY', 100),
        // Facts sent per area, so a prompt never grows with the size of the organization.
        'facts_per_area' => 6,
        // Reasoning models spend part of this thinking, so leave room for the answer.
        'max_tokens' => 2000,
        'keep_days' => 30,
    ],

];
