<?php

/*
|--------------------------------------------------------------------------
| Plans
|--------------------------------------------------------------------------
|
| The single source of truth for what each plan includes. Code never asks
| which plan an organization is on; it asks the entitlements service whether
| a feature is included or a limit has room (App\Support\Billing\Entitlements).
|
| Limits: null means unlimited. Prices are in minor units of "currency" and
| are what the pricing page shows; a payment provider, when one is added,
| holds the prices it charges.
|
*/

return [

    'currency' => 'USD',

    // New organizations try the trial plan for this many days, then move to Free.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => 'business',

    // Where "Talk to us" goes for Enterprise.
    'sales_email' => env('BILLING_SALES_EMAIL', 'sales@flowpilot.app'),

    'plans' => [
        'free' => [
            'label' => 'Free',
            'tagline' => 'For a small team trying out automation.',
            'monthly_price' => 0,
            'limits' => [
                'members' => 3,
                'workflows' => 3,
                'workflow_runs_per_month' => 500,
                'storage_mb' => 1024,
                'ai_briefs_per_day' => 0,
            ],
            'features' => [],
        ],

        'starter' => [
            'label' => 'Starter',
            'tagline' => 'For a team running its daily operations in FlowPilot.',
            'monthly_price' => 2900,
            'limits' => [
                'members' => 10,
                'workflows' => 20,
                'workflow_runs_per_month' => 5000,
                'storage_mb' => 10240,
                'ai_briefs_per_day' => 0,
            ],
            'features' => ['approvals', 'all_reports'],
        ],

        'business' => [
            'label' => 'Business',
            'tagline' => 'For a growing company that runs on automation.',
            'monthly_price' => 9900,
            'limits' => [
                'members' => 50,
                'workflows' => null,
                'workflow_runs_per_month' => 50000,
                'storage_mb' => 102400,
                'ai_briefs_per_day' => 100,
            ],
            'features' => ['approvals', 'all_reports', 'report_exports', 'ai_insights', 'webhooks'],
        ],

        'enterprise' => [
            'label' => 'Enterprise',
            'tagline' => 'For larger organizations that need custom limits and a direct line to us.',
            'monthly_price' => null,
            'limits' => [
                'members' => null,
                'workflows' => null,
                'workflow_runs_per_month' => null,
                'storage_mb' => null,
                'ai_briefs_per_day' => 500,
            ],
            'features' => ['approvals', 'all_reports', 'report_exports', 'ai_insights', 'webhooks'],
        ],
    ],

];
