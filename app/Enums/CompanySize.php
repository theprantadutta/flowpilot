<?php

namespace App\Enums;

enum CompanySize: string
{
    case Solo = '1';
    case Small = '2-10';
    case Medium = '11-50';
    case Large = '51-200';
    case Enterprise = '201+';

    public function label(): string
    {
        return match ($this) {
            self::Solo => 'Just me',
            self::Small => '2 to 10 people',
            self::Medium => '11 to 50 people',
            self::Large => '51 to 200 people',
            self::Enterprise => 'More than 200 people',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
