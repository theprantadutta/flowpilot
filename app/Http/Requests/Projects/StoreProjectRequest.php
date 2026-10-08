<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;

class StoreProjectRequest extends ProjectRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Project::class);
    }
}
