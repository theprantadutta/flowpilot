<?php

namespace App\Enums;

enum Industry: string
{
    case Manufacturing = 'manufacturing';
    case Construction = 'construction';
    case Technology = 'technology';
    case Retail = 'retail';
    case ProfessionalServices = 'professional_services';
    case Healthcare = 'healthcare';
    case Education = 'education';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manufacturing => 'Manufacturing',
            self::Construction => 'Construction',
            self::Technology => 'Technology',
            self::Retail => 'Retail',
            self::ProfessionalServices => 'Professional services',
            self::Healthcare => 'Healthcare',
            self::Education => 'Education',
            self::Other => 'Other',
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
