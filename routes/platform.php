<?php

use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\FailedJobController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\OrganizationController;
use App\Http\Controllers\Platform\UserController;
use Illuminate\Support\Facades\Route;

/*
| Platform administration: /platform/…, for the FlowPilot team only.
| Separate from every organization; nothing here runs inside a tenant.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
Route::post('organizations/{organization}/plan', [OrganizationController::class, 'changePlan'])
    ->middleware('throttle:20,1')
    ->name('organizations.plan.update');
Route::post('organizations/{organization}/trial', [OrganizationController::class, 'extendTrial'])
    ->middleware('throttle:20,1')
    ->name('organizations.trial.update');
Route::post('organizations/{organization}/suspension', [OrganizationController::class, 'suspend'])
    ->middleware('throttle:20,1')
    ->name('organizations.suspension.store');
Route::delete('organizations/{organization}/suspension', [OrganizationController::class, 'reactivate'])
    ->middleware('throttle:20,1')
    ->name('organizations.suspension.destroy');
Route::post('plan-requests/{platformPlanRequest}/decline', [OrganizationController::class, 'declineRequest'])
    ->middleware('throttle:20,1')
    ->name('plan-requests.decline');

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
    ->middleware('throttle:10,1')
    ->name('users.password-reset');

Route::get('health', HealthController::class)->name('health');

Route::get('failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
Route::post('failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])
    ->whereUuid('uuid')
    ->middleware('throttle:30,1')
    ->name('failed-jobs.retry');
Route::delete('failed-jobs/{uuid}', [FailedJobController::class, 'destroy'])
    ->whereUuid('uuid')
    ->name('failed-jobs.destroy');

Route::get('audit', AuditController::class)->name('audit');
