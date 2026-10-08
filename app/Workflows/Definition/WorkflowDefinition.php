<?php

namespace App\Workflows\Definition;

use App\Enums\NodeType;
use InvalidArgumentException;

/**
 * A workflow graph: nodes with their configuration, and the edges between
 * them. Edges leave a node through a named handle (next, true/false,
 * approved/rejected, a branch case), which is how paths are chosen.
 *
 * The shape is what the visual builder edits and what versions store:
 *
 *   nodes: [{ id, type, position: {x, y}, data: { label, config: {...} } }]
 *   edges: [{ id, source, target, sourceHandle }]
 */
final readonly class WorkflowDefinition
{
    public const int MAX_NODES = 100;

    /**
     * @param  list<array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}>  $nodes
     * @param  list<array{id: string, source: string, target: string, sourceHandle: string}>  $edges
     */
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {}

    /**
     * Read a definition from stored or submitted JSON, normalizing shapes so
     * the rest of the engine can rely on them. Unknown keys are dropped.
     *
     * @param  array<mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $nodes = [];

        foreach (is_array($raw['nodes'] ?? null) ? $raw['nodes'] : [] as $node) {
            if (! is_array($node) || ! is_string($node['id'] ?? null) || ! is_string($node['type'] ?? null)) {
                throw new InvalidArgumentException('Every step needs an id and a type.');
            }

            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $position = is_array($node['position'] ?? null) ? $node['position'] : [];

            $nodes[] = [
                'id' => mb_substr($node['id'], 0, 64),
                'type' => $node['type'],
                'position' => [
                    'x' => is_numeric($position['x'] ?? null) ? round((float) $position['x'], 1) : 0,
                    'y' => is_numeric($position['y'] ?? null) ? round((float) $position['y'], 1) : 0,
                ],
                'data' => [
                    'label' => is_string($data['label'] ?? null) ? mb_substr(trim($data['label']), 0, 120) : '',
                    'config' => is_array($data['config'] ?? null) ? $data['config'] : [],
                ],
            ];
        }

        $edges = [];

        foreach (is_array($raw['edges'] ?? null) ? $raw['edges'] : [] as $edge) {
            if (! is_array($edge) || ! is_string($edge['source'] ?? null) || ! is_string($edge['target'] ?? null)) {
                continue;
            }

            $handle = is_string($edge['sourceHandle'] ?? null) && $edge['sourceHandle'] !== '' ? $edge['sourceHandle'] : 'next';

            $edges[] = [
                'id' => is_string($edge['id'] ?? null) ? mb_substr($edge['id'], 0, 120) : "{$edge['source']}-{$handle}-{$edge['target']}",
                'source' => $edge['source'],
                'target' => $edge['target'],
                'sourceHandle' => mb_substr($handle, 0, 64),
            ];
        }

        return new self($nodes, $edges);
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['nodes' => $this->nodes, 'edges' => $this->edges];
    }

    /**
     * A stable fingerprint of what the workflow does (positions excluded), so
     * publishing an unchanged graph does not create a new version.
     */
    public function checksum(): string
    {
        $logic = [
            'nodes' => array_map(fn (array $node): array => [$node['id'], $node['type'], $node['data']], $this->nodes),
            'edges' => array_map(fn (array $edge): array => [$edge['source'], $edge['sourceHandle'], $edge['target']], $this->edges),
        ];

        return hash('sha256', (string) json_encode($logic));
    }

    /**
     * @return array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}|null
     */
    public function node(string $id): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}|null
     */
    public function trigger(): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['type'] === NodeType::Trigger->value) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The node reached by leaving $nodeId through $handle, if any.
     */
    public function next(string $nodeId, string $handle): ?string
    {
        foreach ($this->edges as $edge) {
            if ($edge['source'] === $nodeId && $edge['sourceHandle'] === $handle) {
                return $edge['target'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, source: string, target: string, sourceHandle: string}>
     */
    public function outgoing(string $nodeId): array
    {
        return array_values(array_filter($this->edges, fn (array $edge): bool => $edge['source'] === $nodeId));
    }

    /**
     * @return list<array{id: string, source: string, target: string, sourceHandle: string}>
     */
    public function incoming(string $nodeId): array
    {
        return array_values(array_filter($this->edges, fn (array $edge): bool => $edge['target'] === $nodeId));
    }

    /**
     * @return array<string, mixed>
     */
    public function triggerConfig(): array
    {
        return $this->trigger()['data']['config'] ?? [];
    }
}
