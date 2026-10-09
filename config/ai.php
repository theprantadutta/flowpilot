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
    | How often one member may ask for a brief, and how much is put in front of
    | the model. How many briefs an organization gets each day is set by its
    | plan (config/billing.php).
    |
    */

    'brief' => [
        'per_member_per_hour' => (int) env('AI_BRIEF_PER_MEMBER_PER_HOUR', 6),
        // Facts sent per area, so a prompt never grows with the size of the organization.
        'facts_per_area' => 6,
        // Reasoning models spend part of this thinking, so leave room for the answer.
        'max_tokens' => 2000,
        'keep_days' => 30,
    ],

];
