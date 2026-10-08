<?php

namespace App\Enums;

/**
 * The building blocks of a workflow. Each type knows its outgoing paths
 * ("handles"), which is how the engine decides where to go next.
 */
enum NodeType: string
{
    case Trigger = 'trigger';
    case Condition = 'condition';
    case Branch = 'branch';
    case Action = 'action';
    case Notification = 'notification';
    case Delay = 'delay';
    case CreateRecord = 'create_record';
    case UpdateRecord = 'update_record';
    case Assign = 'assign';
    case Webhook = 'webhook';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Trigger => 'Trigger',
            self::Condition => 'Condition',
            self::Branch => 'Branch',
            self::Action => 'Action',
            self::Notification => 'Notification',
            self::Delay => 'Delay',
            self::CreateRecord => 'Create record',
            self::UpdateRecord => 'Update record',
            self::Assign => 'Assign',
            self::Webhook => 'Webhook',
            self::End => 'End',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Trigger => 'What starts the workflow.',
            self::Condition => 'Go one way if the rules match, another way if not.',
            self::Branch => 'Choose one of several paths by matching cases in order.',
            self::Action => 'Send an email, tag the record or raise an issue.',
            self::Notification => 'Tell people in FlowPilot (and by email if they want).',
            self::Delay => 'Wait for a set time before continuing.',
            self::CreateRecord => 'Create a task or an issue.',
            self::UpdateRecord => 'Change a field on the record that started the workflow.',
            self::Assign => 'Assign the record to a person or someone in a role.',
            self::Webhook => 'Send the details to another system.',
            self::End => 'Finish the workflow here.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Trigger => 'zap',
            self::Condition => 'git-fork',
            self::Branch => 'split',
            self::Action => 'square-play',
            self::Notification => 'bell',
            self::Delay => 'hourglass',
            self::CreateRecord => 'file-plus',
            self::UpdateRecord => 'file-pen',
            self::Assign => 'user-check',
            self::Webhook => 'webhook',
            self::End => 'flag',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Trigger => 'flow',
            self::Condition, self::Branch => 'info',
            self::Delay => 'neutral',
            self::End => 'success',
            default => 'info',
        };
    }

    /**
     * The fixed outgoing paths for this type. Branch nodes add one handle per
     * case on top of these.
     *
     * @return list<array{id: string, label: string}>
     */
    public function handles(): array
    {
        return match ($this) {
            self::Condition => [['id' => 'true', 'label' => 'Yes'], ['id' => 'false', 'label' => 'No']],
            self::Branch => [['id' => 'otherwise', 'label' => 'Otherwise']],
            self::End => [],
            default => [['id' => 'next', 'label' => 'Next']],
        };
    }

    /**
     * Whether the builder offers this type in its palette (the trigger is
     * always created with the workflow).
     */
    public function isAddable(): bool
    {
        return $this !== self::Trigger;
    }

    /**
     * @return list<array{value: string, label: string, description: string, icon: string, tone: string, handles: list<array{id: string, label: string}>, addable: bool}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'icon' => $type->icon(),
            'tone' => $type->tone(),
            'handles' => $type->handles(),
            'addable' => $type->isAddable(),
        ], self::cases());
    }
}
