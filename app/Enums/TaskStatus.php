<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskStatus: string
{
    use HasOptions;

    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Review = 'review';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Blocked => 'Blocked',
            self::Review => 'In review',
            self::Done => 'Done',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Backlog => 'neutral',
            self::Todo => 'info',
            self::InProgress => 'flow',
            self::Blocked => 'danger',
            self::Review => 'warning',
            self::Done => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Backlog => 'circle-dashed',
            self::Todo => 'circle',
            self::InProgress => 'circle-dot',
            self::Blocked => 'circle-slash',
            self::Review => 'eye',
            self::Done => 'circle-check',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! $status->isDone()));
    }
}
