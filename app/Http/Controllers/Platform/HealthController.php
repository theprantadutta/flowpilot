<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\Platform\SystemHealth;
use Illuminate\Foundation\Application;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    public function __invoke(SystemHealth $health): Response
    {
        return Inertia::render('platform/Health', [
            'checks' => Inertia::defer(fn (): array => $health->checks()),
            'versions' => [
                ['label' => 'FlowPilot', 'value' => (string) config('flowpilot.version', 'development')],
                ['label' => 'Laravel', 'value' => Application::VERSION],
                ['label' => 'PHP', 'value' => PHP_VERSION],
                ['label' => 'Environment', 'value' => (string) config('app.env')],
            ],
        ]);
    }
}
