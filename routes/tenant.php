<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\SearchController;
use App\Http\Requests\Organizations\UpdateOrganizationSettingsRequest;
use Illuminate\Support\Facades\Route;

/*
| Routes that run inside one organization: /app/{organization}/…
|
| The organization parameter is consumed by the tenant middleware, so
| controllers never receive it and route() fills it in automatically.
*/

Route::get('/', OverviewController::class)->name('overview');

Route::get('search', SearchController::class)
    ->middleware('throttle:120,1')
    ->name('search');

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])
    ->whereUuid('notification')
    ->name('notifications.open');
Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
    ->whereUuid('notification')
    ->name('notifications.read');

Route::post('settings/logo', [OrganizationSettingsController::class, 'updateLogo'])
    ->middleware('throttle:10,1')
    ->name('organization-settings.logo.update');
Route::delete('settings/logo', [OrganizationSettingsController::class, 'destroyLogo'])->name('organization-settings.logo.destroy');
Route::get('settings/{section?}', [OrganizationSettingsController::class, 'show'])
    ->whereIn('section', UpdateOrganizationSettingsRequest::SECTIONS)
    ->name('organization-settings.show');
Route::patch('settings/{section}', [OrganizationSettingsController::class, 'update'])
    ->whereIn('section', UpdateOrganizationSettingsRequest::SECTIONS)
    ->name('organization-settings.update');

Route::get('members', [MemberController::class, 'index'])->name('members.index');
Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

Route::post('invitations', [InvitationController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('members.invitations.store');
Route::patch('invitations/{invitation}', [InvitationController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('members.invitations.resend');
Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('members.invitations.destroy');
