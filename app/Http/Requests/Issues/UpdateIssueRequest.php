<?php

namespace App\Http\Requests\Issues;

use App\Models\Issue;

class UpdateIssueRequest extends IssueRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue && (bool) $this->user()?->can('update', $issue);
    }
}
