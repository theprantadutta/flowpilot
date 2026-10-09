<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version
    |--------------------------------------------------------------------------
    |
    | The release that is running, shown to platform administrators. The
    | deployment sets it (for example to the git commit being shipped).
    |
    */

    'version' => env('FLOWPILOT_VERSION') ?: 'development',

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | How long an invitation link stays valid before it has to be re-sent.
    |
    */

    'invitations' => [
        'expires_after_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    |
    | Currencies an organization can work in, with the number of decimal
    | places in their minor unit. Money is always stored in minor units.
    |
    */

    'currencies' => [
        'USD' => ['name' => 'US dollar', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'decimals' => 2],
        'GBP' => ['name' => 'British pound', 'decimals' => 2],
        'CAD' => ['name' => 'Canadian dollar', 'decimals' => 2],
        'AUD' => ['name' => 'Australian dollar', 'decimals' => 2],
        'SGD' => ['name' => 'Singapore dollar', 'decimals' => 2],
        'AED' => ['name' => 'UAE dirham', 'decimals' => 2],
        'INR' => ['name' => 'Indian rupee', 'decimals' => 2],
        'BDT' => ['name' => 'Bangladeshi taka', 'decimals' => 2],
        'JPY' => ['name' => 'Japanese yen', 'decimals' => 0],
    ],

    'date_formats' => [
        'M j, Y' => 'Oct 6, 2026',
        'j M Y' => '6 Oct 2026',
        'd/m/Y' => '06/10/2026',
        'm/d/Y' => '10/06/2026',
        'Y-m-d' => '2026-10-06',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Organization Settings
    |--------------------------------------------------------------------------
    |
    | Each organization stores only the settings it has changed. Anything it
    | has not set falls back to these values.
    |
    */

    'organization_settings' => [
        'notifications' => [
            // Per notification type: true/false overrides the type's own email default.
            'email' => [],
        ],
        'workflows' => [
            'approval_due_hours' => 48,
            'max_step_attempts' => 3,
            'notify_on_failure' => true,
        ],
        'security' => [
            'require_two_factor' => false,
            // Sign members out after this many idle minutes. 0 keeps the account-wide session lifetime.
            'idle_timeout_minutes' => 0,
        ],
        'members' => [
            'default_role' => 'employee',
            // Let any member invite colleagues as Employee, not only admins.
            'allow_member_invites' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Limits applied to every file a member uploads. Sizes are in kilobytes.
    |
    */

    'uploads' => [
        // Private disk for attachments. Use "s3" (or any private cloud disk) in production.
        'disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_file_kb' => 20 * 1024,
        'max_image_kb' => 2 * 1024,
        'allowed_extensions' => [
            'pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'txt', 'csv',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report exports
    |--------------------------------------------------------------------------
    |
    | Exports are written in the background to a private disk and can be
    | downloaded by the member who asked for them until they expire.
    |
    */

    'exports' => [
        'disk' => env('EXPORTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'keep_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing webhooks
    |--------------------------------------------------------------------------
    |
    | Workflows can send signed JSON to other systems. Addresses that resolve
    | to private or local networks are always refused. Plain http is refused
    | unless allowed here (for local development against a test receiver).
    |
    */

    'webhooks' => [
        'allow_http' => (bool) env('WEBHOOKS_ALLOW_HTTP', false),
        'timeout_seconds' => 10,
        'connect_timeout_seconds' => 5,
        'max_response_bytes' => 2000,
    ],

];
