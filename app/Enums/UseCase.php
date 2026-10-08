<?php

namespace App\Enums;

/**
 * What an organization mainly wants FlowPilot for. Chosen during onboarding and
 * used to suggest a first workflow.
 */
enum UseCase: string
{
    case Approvals = 'approvals';
    case Purchasing = 'purchasing';
    case Projects = 'projects';
    case Inventory = 'inventory';
    case PeopleOperations = 'people_operations';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Approvals => 'Approvals and sign-offs',
            self::Purchasing => 'Purchasing and spend control',
            self::Projects => 'Projects and task tracking',
            self::Inventory => 'Inventory and reordering',
            self::PeopleOperations => 'Leave, onboarding and HR requests',
            self::Other => 'Something else',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Approvals => 'Route requests to the right people and keep a record of every decision.',
            self::Purchasing => 'Take purchase requests from submission to receipt without email chains.',
            self::Projects => 'Plan work, assign it, and see what is slipping.',
            self::Inventory => 'Track stock levels and reorder before you run out.',
            self::PeopleOperations => 'Handle time off, expenses and other staff requests consistently.',
            self::Other => 'Start with a blank workspace and build your own process.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
