<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The handler for each node type.
 */
class NodeRegistry
{
    /**
     * @var array<string, class-string<NodeHandler>>
     */
    public const array HANDLERS = [
        'trigger' => TriggerHandler::class,
        'condition' => ConditionHandler::class,
        'branch' => BranchHandler::class,
        'action' => ActionHandler::class,
        'notification' => NotificationHandler::class,
        'delay' => DelayHandler::class,
        'create_record' => CreateRecordHandler::class,
        'update_record' => UpdateRecordHandler::class,
        'assign' => AssignHandler::class,
        'webhook' => WebhookHandler::class,
        'end' => EndHandler::class,
    ];

    /**
     * @var array<string, NodeHandler>
     */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function has(string $type): bool
    {
        return isset(self::HANDLERS[$type]);
    }

    public function get(NodeType|string $type): NodeHandler
    {
        $key = $type instanceof NodeType ? $type->value : $type;
        $class = self::HANDLERS[$key] ?? throw new InvalidArgumentException("No handler for workflow step type [{$key}].");

        return $this->resolved[$key] ??= $this->container->make($class);
    }
}
