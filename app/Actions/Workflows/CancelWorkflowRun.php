<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\WorkflowRun;
use App\Workflows\Engine\WorkflowEngine;
use Illuminate\Validation\ValidationException;

class CancelWorkflowRun
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function handle(WorkflowRun $run, User $actor): WorkflowRun
    {
        if (! $this->engine->cancel($run, $actor)) {
            throw ValidationException::withMessages(['run' => 'This run has already finished.']);
        }

        return $run;
    }
}
