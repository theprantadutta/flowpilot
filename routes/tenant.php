<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OverviewController;
use Illuminate\Support\Facades\Route;

/*
| Routes that run inside one organization: /app/{organization}/…
|
| The organization parameter is consumed by the tenant middleware, so
| controllers never receive it and route() fills it in automatically.
*/

Route::get('/', OverviewController::class)->name('overview');

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
