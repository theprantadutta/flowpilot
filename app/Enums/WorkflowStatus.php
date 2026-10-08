<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkflowStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'flow',
            self::Paused => 'warning',
            self::Archived => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'pencil-ruler',
            self::Active => 'play',
            self::Paused => 'pause',
            self::Archived => 'archive',
        };
    }
}
