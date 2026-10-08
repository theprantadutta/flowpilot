<?php

namespace App\Http\Requests\Issues;

use App\Models\Issue;

class StoreIssueRequest extends IssueRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Issue::class);
    }
}
