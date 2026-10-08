<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;

class UpdateProjectRequest extends ProjectRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && (bool) $this->user()?->can('update', $project);
    }
}
