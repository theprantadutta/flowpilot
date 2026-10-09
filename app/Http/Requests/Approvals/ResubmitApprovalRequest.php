<?php

namespace App\Http\Requests\Approvals;

use App\Models\Approval;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class ResubmitApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        return $approval instanceof Approval && (bool) $this->user()?->can('resubmit', $approval);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'amount' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:'.Money::PATTERN],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Say what you changed.',
            'amount.regex' => 'Enter an amount, like 1250 or 1,250.00.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $amount = $this->input('amount');

                if (is_string($amount) && $amount !== '') {
                    try {
                        Money::toMinorUnits($amount, app(Tenancy::class)->currentOrFail()->currency);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('amount', $exception->getMessage());
                    }
                }
            },
        ];
    }

    /**
     * @return array{title?: string, description?: string|null, amount?: int|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('title')) {
            $changes['title'] = (string) $this->validated('title');
        }

        if ($this->has('description')) {
            $description = $this->validated('description');
            $changes['description'] = is_string($description) ? $description : null;
        }

        if ($this->has('amount')) {
            $amount = $this->validated('amount');
            $changes['amount'] = is_string($amount) && $amount !== ''
                ? Money::toMinorUnits($amount, app(Tenancy::class)->currentOrFail()->currency)
                : null;
        }

        return $changes;
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
