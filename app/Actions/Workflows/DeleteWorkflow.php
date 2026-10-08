<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\Workflow;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteWorkflow
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Only workflows that never ran can be deleted. Ones with history are
     * archived instead, so past runs stay explainable.
     */
    public function handle(Workflow $workflow, User $actor): void
    {
        if ($workflow->runs()->exists()) {
            throw ValidationException::withMessages([
                'workflow' => 'This workflow has runs. Archive it instead, so its history is kept.',
            ]);
        }

        DB::transaction(function () use ($workflow, $actor): void {
            $this->activity->log('workflow.deleted', $workflow, ['name' => $workflow->name], actor: $actor);

            $workflow->forceFill(['current_version_id' => null])->save();
            $workflow->delete();
        });
    }
}
