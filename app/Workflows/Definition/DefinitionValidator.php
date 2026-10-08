<?php

namespace App\Workflows\Definition;

use App\Enums\MembershipStatus;
use App\Enums\NodeType;
use App\Models\Organization;
use App\Models\Project;
use App\Workflows\Nodes\NodeRegistry;
use App\Workflows\Triggers\TriggerRegistry;

/**
 * Checks a workflow before it can be published: one trigger, every step
 * connected, no loops, valid paths, and every step's settings make sense for
 * this organization. Drafts may be saved with problems; publishing may not.
 */
class DefinitionValidator
{
    public const int MAX_EDGES = 300;

    public function __construct(
        private readonly NodeRegistry $nodes,
        private readonly TriggerRegistry $triggers,
    ) {}

    /**
     * @return list<array{node: string|null, message: string}>
     */
    public function validate(WorkflowDefinition $definition, string $triggerType, Organization $organization): array
    {
        $issues = [];
        $add = function (?string $node, string $message) use (&$issues): void {
            $issues[] = ['node' => $node, 'message' => $message];
        };

        $trigger = $this->triggers->find($triggerType);

        if ($trigger === null) {
            $add(null, 'This workflow has a trigger FlowPilot does not know.');

            return $issues;
        }

        if (count($definition->nodes) > WorkflowDefinition::MAX_NODES) {
            $add(null, 'A workflow can have at most '.WorkflowDefinition::MAX_NODES.' steps.');

            return $issues;
        }

        if (count($definition->edges) > self::MAX_EDGES) {
            $add(null, 'A workflow can have at most '.self::MAX_EDGES.' connections.');

            return $issues;
        }

        $ids = [];
        $triggers = [];

        foreach ($definition->nodes as $node) {
            if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $node['id'])) {
                $add(null, 'A step has an invalid id.');
            }

            if (isset($ids[$node['id']])) {
                $add($node['id'], 'Two steps share the same id.');
            }

            $ids[$node['id']] = $node;

            if (! $this->nodes->has($node['type'])) {
                $add($node['id'], 'This step type is not available.');
            }

            if ($node['type'] === NodeType::Trigger->value) {
                $triggers[] = $node['id'];
            }
        }

        if ($triggers === []) {
            $add(null, 'Add a trigger to say what starts the workflow.');

            return $issues;
        }

        if (count($triggers) > 1) {
            $add($triggers[1], 'A workflow has exactly one trigger.');
        }

        $triggerId = $triggers[0];
        $taken = [];

        foreach ($definition->edges as $edge) {
            $source = $ids[$edge['source']] ?? null;

            if ($source === null || ! isset($ids[$edge['target']])) {
                $add(null, 'A connection points at a step that no longer exists.');

                continue;
            }

            if ($edge['source'] === $edge['target']) {
                $add($edge['source'], 'A step cannot lead back to itself.');
            }

            if ($edge['target'] === $triggerId) {
                $add($edge['source'], 'Nothing can lead back into the trigger.');
            }

            if ($this->nodes->has($source['type']) && ! in_array($edge['sourceHandle'], $this->nodes->get($source['type'])->handles($source['data']['config']), true)) {
                $add($source['id'], 'A connection leaves this step from a path it does not have.');
            }

            $slot = $edge['source'].'|'.$edge['sourceHandle'];

            if (isset($taken[$slot])) {
                $add($source['id'], 'Each path out of a step can lead to only one next step.');
            }

            $taken[$slot] = true;
        }

        if ($definition->outgoing($triggerId) === []) {
            $add($triggerId, 'Connect the trigger to the first step.');
        }

        if ($this->hasCycle($definition)) {
            $add(null, 'Workflows cannot loop back to an earlier step.');
        }

        $reachable = $this->reachableFrom($triggerId, $definition);

        foreach ($definition->nodes as $node) {
            if (! isset($reachable[$node['id']]) && $node['id'] !== $triggerId) {
                $add($node['id'], 'Connect this step, or remove it.');
            }
        }

        $scope = $this->scope($definition, $triggerType, $organization);

        foreach ($definition->nodes as $node) {
            if (! $this->nodes->has($node['type'])) {
                continue;
            }

            foreach ($this->nodes->get($node['type'])->validate($node['data']['config'], $scope) as $message) {
                $add($node['id'], $message);
            }
        }

        return $this->unique($issues);
    }

    public function scope(WorkflowDefinition $definition, string $triggerType, Organization $organization): ValidationScope
    {
        $trigger = $this->triggers->get($triggerType);

        return new ValidationScope(
            trigger: $trigger,
            fields: $trigger->fields($definition->triggerConfig()),
            currency: $organization->currency,
            memberIds: array_values(array_map(intval(...), $organization->memberships()->where('status', MembershipStatus::Active)->pluck('user_id')->all())),
            projectIds: array_values(array_map(strval(...), Project::query()->pluck('id')->all())),
            definition: $definition,
        );
    }

    private function hasCycle(WorkflowDefinition $definition): bool
    {
        $adjacency = [];

        foreach ($definition->edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        // 0 = unvisited, 1 = on the current path, 2 = done.
        $state = [];

        $visit = function (string $node) use (&$visit, &$state, $adjacency): bool {
            $state[$node] = 1;

            foreach ($adjacency[$node] ?? [] as $next) {
                $nextState = $state[$next] ?? 0;

                if ($nextState === 1 || ($nextState === 0 && $visit($next))) {
                    return true;
                }
            }

            $state[$node] = 2;

            return false;
        };

        foreach ($definition->nodes as $node) {
            if (($state[$node['id']] ?? 0) === 0 && $visit($node['id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function reachableFrom(string $start, WorkflowDefinition $definition): array
    {
        $seen = [$start => true];
        $queue = [$start];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($definition->outgoing($current) as $edge) {
                if (! isset($seen[$edge['target']])) {
                    $seen[$edge['target']] = true;
                    $queue[] = $edge['target'];
                }
            }
        }

        return $seen;
    }

    /**
     * @param  list<array{node: string|null, message: string}>  $issues
     * @return list<array{node: string|null, message: string}>
     */
    private function unique(array $issues): array
    {
        $seen = [];

        return array_values(array_filter($issues, function (array $issue) use (&$seen): bool {
            $key = ($issue['node'] ?? '').'|'.$issue['message'];

            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }
}
