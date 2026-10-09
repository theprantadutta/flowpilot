<?php

namespace App\Http\Requests\Approvals;

use App\Actions\Approvals\DecideApproval;
use App\Models\Approval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        if (! $approval instanceof Approval) {
            return false;
        }

        return (bool) $this->user()?->can(match ($this->input('decision')) {
            'approve' => 'approve',
            'reject' => 'reject',
            default => 'requestChanges',
        }, $approval);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(DecideApproval::DECISIONS)],
            // Saying no, or not yet, always comes with a reason.
            'note' => ['nullable', 'required_unless:decision,approve', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_unless' => 'Add a note so the requester knows why.',
        ];
    }

    /**
     * @return 'approve'|'reject'|'request_changes'
     */
    public function decision(): string
    {
        return match ($this->validated('decision')) {
            'approve' => 'approve',
            'reject' => 'reject',
            default => 'request_changes',
        };
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
