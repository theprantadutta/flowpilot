<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\WebhookSecretController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowDefinitionController;
use App\Http\Controllers\WorkflowRunController;
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
Route::get('settings/webhook-secret', [WebhookSecretController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('organization-settings.webhook-secret.show');
Route::post('settings/webhook-secret', [WebhookSecretController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('organization-settings.webhook-secret.rotate');
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

Route::get('activity', ActivityController::class)->name('activity.index');

Route::resource('projects', ProjectController::class)->except(['create', 'edit']);
Route::post('projects/{project}/attachments', [AttachmentController::class, 'storeForProject'])
    ->middleware('throttle:30,1')
    ->name('projects.attachments.store');

Route::resource('tasks', TaskController::class)->except(['create', 'edit']);
Route::patch('tasks/{task}/move', [TaskController::class, 'move'])
    ->middleware('throttle:240,1')
    ->name('tasks.move');
Route::post('tasks/{task}/checklist', [TaskChecklistController::class, 'store'])->name('tasks.checklist.store');
Route::patch('tasks/{task}/checklist/{checklistItem}', [TaskChecklistController::class, 'update'])->scopeBindings()->name('tasks.checklist.update');
Route::delete('tasks/{task}/checklist/{checklistItem}', [TaskChecklistController::class, 'destroy'])->scopeBindings()->name('tasks.checklist.destroy');
Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])->name('tasks.dependencies.store');
Route::delete('tasks/{task}/dependencies/{blocker}', [TaskDependencyController::class, 'destroy'])->name('tasks.dependencies.destroy');
Route::post('tasks/{task}/comments', [CommentController::class, 'storeForTask'])
    ->middleware('throttle:60,1')
    ->name('tasks.comments.store');
Route::post('tasks/{task}/attachments', [AttachmentController::class, 'storeForTask'])
    ->middleware('throttle:30,1')
    ->name('tasks.attachments.store');

Route::resource('issues', IssueController::class)->except(['create', 'edit']);
Route::post('issues/{issue}/comments', [CommentController::class, 'storeForIssue'])
    ->middleware('throttle:60,1')
    ->name('issues.comments.store');
Route::post('issues/{issue}/attachments', [AttachmentController::class, 'storeForIssue'])
    ->middleware('throttle:30,1')
    ->name('issues.attachments.store');

Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

Route::get('workflows', [WorkflowController::class, 'index'])->name('workflows.index');
Route::post('workflows', [WorkflowController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('workflows.store');
Route::get('workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');
Route::patch('workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
Route::put('workflows/{workflow}/draft', [WorkflowDefinitionController::class, 'saveDraft'])
    ->middleware('throttle:120,1')
    ->name('workflows.draft.update');
Route::post('workflows/{workflow}/publish', [WorkflowDefinitionController::class, 'publish'])
    ->middleware('throttle:30,1')
    ->name('workflows.publish');
Route::patch('workflows/{workflow}/status', [WorkflowDefinitionController::class, 'updateStatus'])->name('workflows.status.update');
Route::post('workflows/{workflow}/versions/{version}/restore', [WorkflowDefinitionController::class, 'restoreVersion'])
    ->scopeBindings()
    ->name('workflows.versions.restore');
Route::post('workflows/{workflow}/runs', [WorkflowRunController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('workflows.runs.store');

Route::get('runs', [WorkflowRunController::class, 'index'])->name('workflow-runs.index');
Route::get('runs/{run}', [WorkflowRunController::class, 'show'])->name('workflow-runs.show');
Route::post('runs/{run}/cancel', [WorkflowRunController::class, 'cancel'])->name('workflow-runs.cancel');
Route::post('runs/{run}/retry', [WorkflowRunController::class, 'retry'])
    ->middleware('throttle:30,1')
    ->name('workflow-runs.retry');

Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::post('approvals', [ApprovalController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('approvals.store');
Route::get('approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
Route::post('approvals/{approval}/decision', [ApprovalController::class, 'decide'])
    ->middleware('throttle:60,1')
    ->name('approvals.decide');
Route::post('approvals/{approval}/resubmit', [ApprovalController::class, 'resubmit'])
    ->middleware('throttle:30,1')
    ->name('approvals.resubmit');
Route::post('approvals/{approval}/withdraw', [ApprovalController::class, 'withdraw'])->name('approvals.withdraw');
Route::post('approvals/{approval}/comments', [CommentController::class, 'storeForApproval'])
    ->middleware('throttle:60,1')
    ->name('approvals.comments.store');
Route::post('approvals/{approval}/attachments', [AttachmentController::class, 'storeForApproval'])
    ->middleware('throttle:30,1')
    ->name('approvals.attachments.store');
