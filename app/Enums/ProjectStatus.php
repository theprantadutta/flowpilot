<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planning => 'info',
            self::Active => 'flow',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Archived => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planning => 'pencil-ruler',
            self::Active => 'play',
            self::OnHold => 'pause',
            self::Completed => 'circle-check',
            self::Archived => 'archive',
        };
    }

    /**
     * Statuses where work is still expected to happen.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Planning, self::Active, self::OnHold];
    }
}
