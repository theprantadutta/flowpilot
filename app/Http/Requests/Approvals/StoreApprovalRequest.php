<?php

namespace App\Http\Requests\Approvals;

use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Approval;
use App\Rules\ActiveMember;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Approval::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'approver_type' => ['required', Rule::in(['member', 'role'])],
            'approver_id' => ['required_if:approver_type,member', 'nullable', 'integer', new ActiveMember('Choose an active member to approve.'), Rule::notIn([$this->user()?->id])],
            'approver_role' => ['required_if:approver_type,role', 'nullable', Rule::enum(Role::class)->except([Role::Owner])],
            'amount' => ['nullable', 'string', 'max:20', 'regex:'.Money::PATTERN],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Say what needs approving.',
            'approver_id.required_if' => 'Choose who approves.',
            'approver_id.not_in' => 'Someone else has to approve your request.',
            'approver_role.required_if' => 'Choose which role approves.',
            'amount.regex' => 'Enter an amount, like 1250 or 1,250.00.',
            'due_date.after_or_equal' => 'The decision cannot be due in the past.',
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
     * What to create: the amount in minor units, and the due date at the end
     * of that day in the organization's timezone.
     *
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        $organization = app(Tenancy::class)->currentOrFail();
        $amount = $this->validated('amount');
        $due = $this->validated('due_date');
        $isRole = $this->validated('approver_type') === 'role';

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'priority' => Priority::from((string) $this->validated('priority')),
            'approver_id' => $isRole ? null : (int) $this->validated('approver_id'),
            'approver_role' => $isRole ? Role::from((string) $this->validated('approver_role')) : null,
            'amount' => is_string($amount) && $amount !== '' ? Money::toMinorUnits($amount, $organization->currency) : null,
            'currency' => is_string($amount) && $amount !== '' ? $organization->currency : null,
            'due_at' => is_string($due) ? now($organization->timezone)->setDateFrom($due)->endOfDay()->utc() : null,
        ];
    }
}
