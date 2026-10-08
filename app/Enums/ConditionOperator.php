<?php

namespace App\Enums;

enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'is',
            self::NotEquals => 'is not',
            self::GreaterThan => 'is greater than',
            self::LessThan => 'is less than',
            self::Contains => 'contains',
            self::StartsWith => 'starts with',
            self::IsEmpty => 'is empty',
            self::IsNotEmpty => 'is not empty',
        };
    }

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsEmpty, self::IsNotEmpty], true);
    }

    /**
     * Operators that make sense for a field type.
     *
     * @return list<self>
     */
    public static function forFieldType(string $type): array
    {
        return match ($type) {
            'number', 'money', 'date' => [self::Equals, self::NotEquals, self::GreaterThan, self::LessThan, self::IsEmpty, self::IsNotEmpty],
            'select', 'boolean' => [self::Equals, self::NotEquals, self::IsEmpty, self::IsNotEmpty],
            default => self::cases(),
        };
    }

    /**
     * @return list<array{value: string, label: string, needs_value: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $operator): array => [
            'value' => $operator->value,
            'label' => $operator->label(),
            'needs_value' => $operator->needsValue(),
        ], self::cases());
    }
}
