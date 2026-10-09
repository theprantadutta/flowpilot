<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\EnforceOrganizationSecurity;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Support\Facades\Route;

/*
| The public site. Everything else is behind sign-in and is never indexed.
*/
Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('pricing', [MarketingController::class, 'pricing'])->name('pricing');
Route::get('terms', [MarketingController::class, 'terms'])->name('legal.terms');
Route::get('privacy', [MarketingController::class, 'privacy'])->name('legal.privacy');
Route::get('sitemap.xml', [MarketingController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [MarketingController::class, 'robots'])->name('robots');

/*
| Invitation links. The token is the credential, so these are throttled and
| reachable while signed out; accepting requires an account.
*/
Route::middleware('throttle:30,1')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('auth')
        ->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('onboarding.store');

    /*
    | Everything inside an organization. SetCurrentOrganization resolves
    | the tenant from the URL and checks membership before any binding happens.
    */
    Route::prefix('app/{organization}')
        ->middleware([SetCurrentOrganization::class, EnforceOrganizationSecurity::class])
        ->group(base_path('routes/tenant.php'));

    /*
    | Platform administration, for the FlowPilot team. Recent password
    | confirmation keeps a stolen session from reaching it.
    */
    Route::prefix('platform')
        ->name('platform.')
        ->middleware([EnsurePlatformAdmin::class, 'password.confirm'])
        ->group(base_path('routes/platform.php'));
});

require __DIR__.'/settings.php';
