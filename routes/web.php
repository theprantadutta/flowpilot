<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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
        ->middleware(SetCurrentOrganization::class)
        ->group(base_path('routes/tenant.php'));
});

require __DIR__.'/settings.php';
