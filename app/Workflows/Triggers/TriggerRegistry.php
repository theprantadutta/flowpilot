<?php

namespace App\Workflows\Triggers;

use InvalidArgumentException;

/**
 * Every trigger a workflow can start from.
 */
class TriggerRegistry
{
    /**
     * @var array<string, Trigger>
     */
    private array $triggers = [];

    public function __construct()
    {
        foreach ([
            new ManualTrigger,
            new TaskCreatedTrigger,
            new TaskCompletedTrigger,
            new IssueCreatedTrigger,
            new PurchaseRequestSubmittedTrigger,
            new InventoryLowStockTrigger,
        ] as $trigger) {
            $this->triggers[$trigger->key()] = $trigger;
        }
    }

    /**
     * @return list<Trigger>
     */
    public function all(): array
    {
        return array_values($this->triggers);
    }

    public function find(string $key): ?Trigger
    {
        return $this->triggers[$key] ?? null;
    }

    public function get(string $key): Trigger
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown workflow trigger [{$key}].");
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->triggers);
    }

    public function manual(): ManualTrigger
    {
        /** @var ManualTrigger */
        return $this->triggers['manual'];
    }

    /**
     * @return list<array{value: string, label: string, description: string, subject: string|null, fields: list<array{path: string, label: string, type: string, options: list<array{value: string, label: string}>}>}>
     */
    public function options(): array
    {
        return array_map(fn (Trigger $trigger): array => $trigger->toOption(), $this->all());
    }
}
