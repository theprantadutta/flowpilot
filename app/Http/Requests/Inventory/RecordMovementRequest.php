<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Rules\BelongsToCurrentOrganization;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof InventoryItem && (bool) $this->user()?->can('move', $item);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $location = fn (): BelongsToCurrentOrganization => new BelongsToCurrentOrganization(InventoryLocation::class, 'Choose a location from this organization.');

        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => ['exclude_if:type,adjustment', 'required', 'integer', 'min:1', 'max:10000000'],
            'counted' => ['exclude_unless:type,adjustment', 'required', 'integer', 'min:0', 'max:10000000'],
            'location_id' => ['exclude_if:type,transfer', 'required', $location()],
            'from_location_id' => ['exclude_unless:type,transfer', 'required', $location()],
            'to_location_id' => ['exclude_unless:type,transfer', 'required', 'different:from_location_id', $location()],
            'unit_cost' => ['exclude_unless:type,receipt', 'nullable', 'string', 'max:20', 'regex:'.Money::PATTERN],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:type,adjustment'],
            'request_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_location_id.different' => 'Choose a different location to move the stock to.',
            'notes.required_if' => 'Say why the count is different, for the audit trail.',
            'unit_cost.regex' => 'Enter a cost, like 12.50.',
        ];
    }

    public function type(): MovementType
    {
        return MovementType::from((string) $this->validated('type'));
    }

    /**
     * @return array{quantity?: int, counted?: int, location_id?: string, from_location_id?: string, to_location_id?: string, unit_cost_amount?: int|null, reference?: string|null, notes?: string|null, idempotency_key: string}
     */
    public function movement(): array
    {
        $data = $this->validated();
        $cost = $data['unit_cost'] ?? null;

        return array_filter([
            'quantity' => isset($data['quantity']) ? (int) $data['quantity'] : null,
            'counted' => isset($data['counted']) ? (int) $data['counted'] : null,
            'location_id' => $data['location_id'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'unit_cost_amount' => is_string($cost) && $cost !== '' ? Money::toMinorUnits($cost, app(Tenancy::class)->currentOrFail()->currency) : null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => 'movement:'.$data['request_key'],
        ], fn (mixed $value): bool => $value !== null);
    }
}
