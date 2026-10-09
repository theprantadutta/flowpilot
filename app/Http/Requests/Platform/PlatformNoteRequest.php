<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A platform action that carries a short explanation: why an organization
 * is suspended or why an upgrade was declined. Both are kept for the record.
 */
class PlatformNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
