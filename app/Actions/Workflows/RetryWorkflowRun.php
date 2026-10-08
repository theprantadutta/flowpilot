<?php

namespace App\Actions\Workflows;

use App\Models\User;
use App\Models\WorkflowRun;
use App\Workflows\Engine\WorkflowEngine;
use Illuminate\Validation\ValidationException;

class RetryWorkflowRun
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function handle(WorkflowRun $run, User $actor): WorkflowRun
    {
        if (! $this->engine->retry($run, $actor)) {
            throw ValidationException::withMessages(['run' => 'Only failed runs can be tried again.']);
        }

        return $run;
    }
}
