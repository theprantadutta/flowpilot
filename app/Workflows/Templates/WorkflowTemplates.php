<?php

namespace App\Workflows\Templates;

/**
 * Ready-made workflows to start from. Each one is an ordinary definition:
 * once created, it is the organization's own workflow to change.
 */
class WorkflowTemplates
{
    /**
     * @return list<array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}>
     */
    public function all(): array
    {
        return [
            $this->blank(),
            $this->criticalIssueEscalation(),
            $this->newStarterOnboarding(),
            $this->urgentTaskAlert(),
            $this->completedTaskWebhook(),
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * A new workflow with just a trigger of the chosen type.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function starter(string $trigger): array
    {
        return [
            'nodes' => [self::node('trigger', 'trigger', 'Start', 0, 0, $trigger === 'manual' ? ['inputs' => []] : [])],
            'edges' => [],
        ];
    }

    /**
     * @return list<array{key: string, name: string, description: string, category: string, trigger: string, steps: int}>
     */
    public function options(): array
    {
        return array_map(fn (array $template): array => [
            'key' => $template['key'],
            'name' => $template['name'],
            'description' => $template['description'],
            'category' => $template['category'],
            'trigger' => $template['trigger'],
            'steps' => count($template['definition']['nodes']),
        ], $this->all());
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function blank(): array
    {
        return [
            'key' => 'blank',
            'name' => 'Start from scratch',
            'description' => 'A trigger and an empty canvas.',
            'category' => 'Basics',
            'trigger' => 'manual',
            'definition' => $this->starter('manual'),
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function criticalIssueEscalation(): array
    {
        return [
            'key' => 'critical-issue-escalation',
            'name' => 'Escalate critical issues',
            'description' => 'When a critical issue is reported, assign it to operations, alert managers and tag it.',
            'category' => 'Operations',
            'trigger' => 'issue.created',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Issue reported', 0, 0),
                    self::node('is_critical', 'condition', 'Is it critical?', 0, 150, [
                        'match' => 'all',
                        'rules' => [['field' => 'subject.severity', 'operator' => 'equals', 'value' => 'critical']],
                    ]),
                    self::node('assign_operations', 'assign', 'Assign to operations', -220, 300, [
                        'assignee' => ['type' => 'role', 'role' => 'operations'],
                    ]),
                    self::node('alert_managers', 'notification', 'Alert managers', -220, 450, [
                        'recipients' => [['type' => 'role', 'role' => 'manager'], ['type' => 'role', 'role' => 'admin']],
                        'title' => 'Critical issue {{ subject.reference }}: {{ subject.title }}',
                        'message' => 'Reported by {{ subject.reporter_id }}. Assigned to {{ steps.assign_operations.assignee }}.',
                    ]),
                    self::node('tag_escalated', 'action', 'Tag as escalated', -220, 600, [
                        'action' => 'add_tag',
                        'tag' => 'escalated',
                    ]),
                    self::node('escalated', 'end', 'Escalated', -220, 750, ['summary' => 'Escalated to operations']),
                    self::node('routine', 'end', 'Handled normally', 220, 300, ['summary' => 'Not critical, no escalation needed']),
                ],
                'edges' => [
                    self::edge('trigger', 'is_critical'),
                    self::edge('is_critical', 'assign_operations', 'true'),
                    self::edge('is_critical', 'routine', 'false'),
                    self::edge('assign_operations', 'alert_managers'),
                    self::edge('alert_managers', 'tag_escalated'),
                    self::edge('tag_escalated', 'escalated'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function newStarterOnboarding(): array
    {
        return [
            'key' => 'new-starter-onboarding',
            'name' => 'New starter onboarding',
            'description' => 'Collect a new starter’s details, create the setup tasks for operations and finance, and brief their manager.',
            'category' => 'People',
            'trigger' => 'manual',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'New starter', 0, 0, [
                        'inputs' => [
                            ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'options' => []],
                            ['key' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true, 'options' => []],
                            ['key' => 'department', 'label' => 'Department', 'type' => 'select', 'required' => true, 'options' => [
                                ['value' => 'operations', 'label' => 'Operations'],
                                ['value' => 'finance', 'label' => 'Finance'],
                                ['value' => 'procurement', 'label' => 'Procurement'],
                                ['value' => 'hr', 'label' => 'HR'],
                            ]],
                            ['key' => 'manager', 'label' => 'Manager', 'type' => 'person', 'required' => true, 'options' => []],
                        ],
                    ]),
                    self::node('equipment', 'create_record', 'Prepare equipment', 0, 150, [
                        'record' => 'task',
                        'title' => 'Prepare equipment and access for {{ input.name }}',
                        'description' => '{{ input.name }} joins {{ input.department }} on {{ input.start_date }}. Set up a workstation, safety gear and site access.',
                        'priority' => 'high',
                        'assignee' => ['type' => 'role', 'role' => 'operations'],
                        'project' => null,
                        'due_in_days' => 3,
                        'tags' => ['onboarding'],
                    ]),
                    self::node('payroll', 'create_record', 'Set up payroll', 0, 300, [
                        'record' => 'task',
                        'title' => 'Add {{ input.name }} to payroll',
                        'description' => 'Start date {{ input.start_date }}, department {{ input.department }}.',
                        'priority' => 'medium',
                        'assignee' => ['type' => 'role', 'role' => 'finance'],
                        'project' => null,
                        'due_in_days' => 5,
                        'tags' => ['onboarding'],
                    ]),
                    self::node('brief_manager', 'notification', 'Brief the manager', 0, 450, [
                        'recipients' => [['type' => 'field', 'field' => 'input.manager']],
                        'title' => '{{ input.name }} starts on {{ input.start_date }}',
                        'message' => 'Setup is under way: {{ steps.equipment.reference }} (equipment) and {{ steps.payroll.reference }} (payroll).',
                    ]),
                    self::node('done', 'end', 'Onboarding started', 0, 600),
                ],
                'edges' => [
                    self::edge('trigger', 'equipment'),
                    self::edge('equipment', 'payroll'),
                    self::edge('payroll', 'brief_manager'),
                    self::edge('brief_manager', 'done'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function urgentTaskAlert(): array
    {
        return [
            'key' => 'urgent-task-alert',
            'name' => 'Flag urgent tasks',
            'description' => 'When an urgent task is added, tell managers and make sure someone owns it.',
            'category' => 'Operations',
            'trigger' => 'task.created',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Task created', 0, 0),
                    self::node('is_urgent', 'condition', 'Is it urgent?', 0, 150, [
                        'match' => 'all',
                        'rules' => [['field' => 'subject.priority', 'operator' => 'equals', 'value' => 'urgent']],
                    ]),
                    self::node('has_owner', 'condition', 'Already assigned?', -220, 300, [
                        'match' => 'all',
                        'rules' => [['field' => 'subject.assignee_id', 'operator' => 'is_not_empty', 'value' => null]],
                    ]),
                    self::node('assign_manager', 'assign', 'Give it to a manager', -40, 450, [
                        'assignee' => ['type' => 'role', 'role' => 'manager'],
                    ]),
                    self::node('tell_managers', 'notification', 'Tell managers', -220, 600, [
                        'recipients' => [['type' => 'role', 'role' => 'manager']],
                        'title' => 'Urgent: {{ subject.reference }} {{ subject.title }}',
                        'message' => 'Added by {{ subject.reporter_id }}. Open the task to see who has it.',
                    ]),
                    self::node('done', 'end', 'Done', -220, 750),
                    self::node('not_urgent', 'end', 'Not urgent', 220, 300),
                ],
                'edges' => [
                    self::edge('trigger', 'is_urgent'),
                    self::edge('is_urgent', 'has_owner', 'true'),
                    self::edge('is_urgent', 'not_urgent', 'false'),
                    self::edge('has_owner', 'tell_managers', 'true'),
                    self::edge('has_owner', 'assign_manager', 'false'),
                    self::edge('assign_manager', 'tell_managers'),
                    self::edge('tell_managers', 'done'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function completedTaskWebhook(): array
    {
        return [
            'key' => 'completed-task-webhook',
            'name' => 'Send completed tasks to another system',
            'description' => 'Post each completed task to a webhook, signed so the receiver can trust it.',
            'category' => 'Integrations',
            'trigger' => 'task.completed',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Task completed', 0, 0),
                    self::node('send', 'webhook', 'Send to webhook', 0, 150, [
                        'url' => '',
                        'fields' => [
                            ['key' => 'reference', 'value' => '{{ subject.reference }}'],
                            ['key' => 'title', 'value' => '{{ subject.title }}'],
                            ['key' => 'project', 'value' => '{{ subject.project }}'],
                        ],
                    ]),
                    self::node('done', 'end', 'Sent', 0, 300),
                ],
                'edges' => [
                    self::edge('trigger', 'send'),
                    self::edge('send', 'done'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{id: string, type: string, position: array{x: int, y: int}, data: array{label: string, config: array<string, mixed>}}
     */
    private static function node(string $id, string $type, string $label, int $x, int $y, array $config = []): array
    {
        return ['id' => $id, 'type' => $type, 'position' => ['x' => $x, 'y' => $y], 'data' => ['label' => $label, 'config' => $config]];
    }

    /**
     * @return array{id: string, source: string, target: string, sourceHandle: string}
     */
    private static function edge(string $source, string $target, string $handle = 'next'): array
    {
        return ['id' => "{$source}-{$handle}-{$target}", 'source' => $source, 'target' => $target, 'sourceHandle' => $handle];
    }
}
