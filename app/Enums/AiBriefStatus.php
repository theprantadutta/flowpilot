<?php

namespace App\Enums;

enum AiBriefStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Writing',
            self::Completed => 'Ready',
            self::Failed => 'Could not be written',
        };
    }
}
