<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\ChangeWorkflowStatus;
use App\Actions\Workflows\PublishWorkflow;
use App\Actions\Workflows\RestoreWorkflowVersion;
use App\Actions\Workflows\SaveWorkflowDraft;
use App\Enums\WorkflowStatus;
use App\Http\Requests\Workflows\PublishWorkflowRequest;
use App\Http\Requests\Workflows\SaveWorkflowDraftRequest;
use App\Http\Requests\Workflows\UpdateWorkflowStatusRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The builder's lifecycle: save the draft, publish it as a version, turn the
 * workflow on or off, and roll the draft back to an earlier version.
 */
class WorkflowDefinitionController extends Controller
{
    public function saveDraft(SaveWorkflowDraftRequest $request, Workflow $workflow, SaveWorkflowDraft $saveDraft): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $saveDraft->handle($workflow, $user, $request->definition(), (string) $request->validated('trigger'));

        return back();
    }

    public function publish(PublishWorkflowRequest $request, Workflow $workflow, PublishWorkflow $publishWorkflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $previous = $workflow->current_version_id;

        $version = $publishWorkflow->handle($workflow, $user, $request->validated('notes'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $version->id === $previous
                ? 'No changes since the last version. The workflow is live.'
                : "Version {$version->version} published. New runs use it from now on.",
        ]);

        return back();
    }

    public function updateStatus(UpdateWorkflowStatusRequest $request, Workflow $workflow, ChangeWorkflowStatus $changeStatus): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->status();

        $changeStatus->handle($workflow, $user, $status);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($status) {
            WorkflowStatus::Active => "{$workflow->name} is on.",
            WorkflowStatus::Paused => "{$workflow->name} is paused. Runs under way will finish.",
            default => "{$workflow->name} archived.",
        }]);

        return back();
    }

    public function restoreVersion(Request $request, Workflow $workflow, WorkflowVersion $version, RestoreWorkflowVersion $restore): RedirectResponse
    {
        Gate::authorize('update', $workflow);

        /** @var User $user */
        $user = $request->user();

        $restore->handle($workflow, $version, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$version->version} copied into the draft. Publish to make it live."]);

        return back();
    }
}
