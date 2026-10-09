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
            $this->purchaseApproval(),
            $this->leaveRequest(),
            $this->expenseApproval(),
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
     * The flagship flow: manager approval, finance for anything over 5,000,
     * then procurement orders it.
     *
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function purchaseApproval(): array
    {
        return [
            'key' => 'purchase-approval',
            'name' => 'Purchase approval',
            'description' => 'A manager approves every purchase, finance also approves anything over 5,000, then procurement is asked to order it.',
            'category' => 'Finance',
            'trigger' => 'manual',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Purchase requested', 0, 0, [
                        'inputs' => [
                            ['key' => 'item', 'label' => 'Item', 'type' => 'text', 'required' => true, 'options' => []],
                            ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'required' => true, 'options' => []],
                            ['key' => 'amount', 'label' => 'Total amount', 'type' => 'money', 'required' => true, 'options' => []],
                            ['key' => 'supplier', 'label' => 'Supplier', 'type' => 'text', 'required' => true, 'options' => []],
                            ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => true, 'options' => []],
                            ['key' => 'reason', 'label' => 'Why it is needed', 'type' => 'text', 'required' => false, 'options' => []],
                        ],
                    ]),
                    self::node('manager_approval', 'approval', 'Manager approval', 0, 160, [
                        'title' => 'Purchase: {{ input.quantity }} × {{ input.item }}',
                        'description' => 'From {{ input.supplier }}, needed by {{ input.needed_by }}. {{ input.reason }}',
                        'approver' => ['type' => 'role', 'role' => 'manager'],
                        'amount_field' => 'input.amount',
                        'priority' => 'medium',
                        'due_in_hours' => 48,
                        'when_overdue' => 'remind',
                    ]),
                    self::node('over_limit', 'condition', 'Over 5,000?', -240, 330, [
                        'match' => 'all',
                        'rules' => [['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '5000']],
                    ]),
                    self::node('finance_approval', 'approval', 'Finance approval', -480, 500, [
                        'title' => 'Finance check: {{ input.item }} ({{ input.amount }})',
                        'description' => 'Approved by {{ steps.manager_approval.decided_by }}. From {{ input.supplier }}, needed by {{ input.needed_by }}.',
                        'approver' => ['type' => 'role', 'role' => 'finance'],
                        'amount_field' => 'input.amount',
                        'priority' => 'high',
                        'due_in_hours' => 48,
                        'when_overdue' => 'remind',
                    ]),
                    self::node('order_task', 'create_record', 'Ask procurement to order', -240, 680, [
                        'record' => 'task',
                        'title' => 'Order {{ input.quantity }} × {{ input.item }} from {{ input.supplier }}',
                        'description' => 'Approved purchase of {{ input.amount }}, needed by {{ input.needed_by }}. Requested by {{ actor.name }}.',
                        'priority' => 'high',
                        'assignee' => ['type' => 'role', 'role' => 'procurement'],
                        'project' => null,
                        'due_in_days' => 2,
                        'tags' => ['purchase'],
                    ]),
                    self::node('tell_approved', 'notification', 'Tell the requester', -240, 850, [
                        'recipients' => [['type' => 'starter']],
                        'title' => 'Your purchase of {{ input.item }} is approved',
                        'message' => 'Procurement will order it: {{ steps.order_task.reference }}.',
                    ]),
                    self::node('approved', 'end', 'Approved', -240, 1020, ['summary' => 'Approved and sent to procurement']),
                    self::node('tell_rejected', 'notification', 'Tell the requester', 260, 500, [
                        'recipients' => [['type' => 'starter']],
                        'title' => 'Your purchase of {{ input.item }} was not approved',
                        'message' => 'Open the request to see the reason.',
                    ]),
                    self::node('rejected', 'end', 'Not approved', 260, 680, ['summary' => 'Not approved']),
                ],
                'edges' => [
                    self::edge('trigger', 'manager_approval'),
                    self::edge('manager_approval', 'over_limit', 'approved'),
                    self::edge('manager_approval', 'tell_rejected', 'rejected'),
                    self::edge('over_limit', 'finance_approval', 'true'),
                    self::edge('over_limit', 'order_task', 'false'),
                    self::edge('finance_approval', 'order_task', 'approved'),
                    self::edge('finance_approval', 'tell_rejected', 'rejected'),
                    self::edge('order_task', 'tell_approved'),
                    self::edge('tell_approved', 'approved'),
                    self::edge('tell_rejected', 'rejected'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function leaveRequest(): array
    {
        return [
            'key' => 'leave-request',
            'name' => 'Leave request',
            'description' => 'Someone asks for time off, a manager decides, and they hear back either way.',
            'category' => 'People',
            'trigger' => 'manual',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Leave requested', 0, 0, [
                        'inputs' => [
                            ['key' => 'leave_type', 'label' => 'Type of leave', 'type' => 'select', 'required' => true, 'options' => [
                                ['value' => 'annual', 'label' => 'Annual leave'],
                                ['value' => 'sick', 'label' => 'Sick leave'],
                                ['value' => 'unpaid', 'label' => 'Unpaid leave'],
                                ['value' => 'other', 'label' => 'Other'],
                            ]],
                            ['key' => 'first_day', 'label' => 'First day', 'type' => 'date', 'required' => true, 'options' => []],
                            ['key' => 'last_day', 'label' => 'Last day', 'type' => 'date', 'required' => true, 'options' => []],
                            ['key' => 'cover', 'label' => 'Who covers', 'type' => 'person', 'required' => false, 'options' => []],
                            ['key' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false, 'options' => []],
                        ],
                    ]),
                    self::node('manager_approval', 'approval', 'Manager decides', 0, 160, [
                        'title' => '{{ input.leave_type }}: {{ input.first_day }} to {{ input.last_day }}',
                        'description' => 'Requested by {{ actor.name }}. Cover: {{ input.cover }}. {{ input.notes }}',
                        'approver' => ['type' => 'role', 'role' => 'manager'],
                        'amount_field' => null,
                        'priority' => 'medium',
                        'due_in_hours' => 24,
                        'when_overdue' => 'remind',
                    ]),
                    self::node('tell_approved', 'notification', 'Confirm the leave', -220, 330, [
                        'recipients' => [['type' => 'starter'], ['type' => 'field', 'field' => 'input.cover']],
                        'title' => 'Leave approved: {{ input.first_day }} to {{ input.last_day }}',
                        'message' => 'Approved by {{ steps.manager_approval.decided_by }}. {{ steps.manager_approval.note }}',
                    ]),
                    self::node('approved', 'end', 'Approved', -220, 500, ['summary' => 'Leave approved']),
                    self::node('tell_declined', 'notification', 'Explain the decision', 220, 330, [
                        'recipients' => [['type' => 'starter']],
                        'title' => 'Leave not approved: {{ input.first_day }} to {{ input.last_day }}',
                        'message' => '{{ steps.manager_approval.decided_by }} said: {{ steps.manager_approval.note }}',
                    ]),
                    self::node('declined', 'end', 'Declined', 220, 500, ['summary' => 'Leave declined']),
                ],
                'edges' => [
                    self::edge('trigger', 'manager_approval'),
                    self::edge('manager_approval', 'tell_approved', 'approved'),
                    self::edge('manager_approval', 'tell_declined', 'rejected'),
                    self::edge('tell_approved', 'approved'),
                    self::edge('tell_declined', 'declined'),
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, description: string, category: string, trigger: string, definition: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    private function expenseApproval(): array
    {
        return [
            'key' => 'expense-approval',
            'name' => 'Expense approval',
            'description' => 'Small expenses go to a manager, anything over 1,000 goes to finance, and the claimant hears the outcome.',
            'category' => 'Finance',
            'trigger' => 'manual',
            'definition' => [
                'nodes' => [
                    self::node('trigger', 'trigger', 'Expense claimed', 0, 0, [
                        'inputs' => [
                            ['key' => 'what', 'label' => 'What it was for', 'type' => 'text', 'required' => true, 'options' => []],
                            ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [
                                ['value' => 'travel', 'label' => 'Travel'],
                                ['value' => 'meals', 'label' => 'Meals'],
                                ['value' => 'equipment', 'label' => 'Equipment'],
                                ['value' => 'training', 'label' => 'Training'],
                                ['value' => 'other', 'label' => 'Other'],
                            ]],
                            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'required' => true, 'options' => []],
                            ['key' => 'spent_on', 'label' => 'Date spent', 'type' => 'date', 'required' => true, 'options' => []],
                        ],
                    ]),
                    self::node('large', 'condition', 'Over 1,000?', 0, 160, [
                        'match' => 'all',
                        'rules' => [['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '1000']],
                    ]),
                    self::node('finance_approval', 'approval', 'Finance approval', -240, 330, [
                        'title' => '{{ input.category }} expense: {{ input.what }}',
                        'description' => 'Spent on {{ input.spent_on }} by {{ actor.name }}.',
                        'approver' => ['type' => 'role', 'role' => 'finance'],
                        'amount_field' => 'input.amount',
                        'priority' => 'medium',
                        'due_in_hours' => 72,
                        'when_overdue' => 'remind',
                    ]),
                    self::node('manager_approval', 'approval', 'Manager approval', 240, 330, [
                        'title' => '{{ input.category }} expense: {{ input.what }}',
                        'description' => 'Spent on {{ input.spent_on }} by {{ actor.name }}.',
                        'approver' => ['type' => 'role', 'role' => 'manager'],
                        'amount_field' => 'input.amount',
                        'priority' => 'low',
                        'due_in_hours' => 72,
                        'when_overdue' => 'remind',
                    ]),
                    self::node('tell_approved', 'notification', 'Tell the claimant', -240, 520, [
                        'recipients' => [['type' => 'starter']],
                        'title' => 'Expense approved: {{ input.amount }} for {{ input.what }}',
                        'message' => 'It will be paid back with the next payroll.',
                    ]),
                    self::node('approved', 'end', 'Approved', -240, 690, ['summary' => 'Expense approved']),
                    self::node('tell_rejected', 'notification', 'Tell the claimant', 240, 520, [
                        'recipients' => [['type' => 'starter']],
                        'title' => 'Expense not approved: {{ input.what }}',
                        'message' => 'Open the request to see the reason.',
                    ]),
                    self::node('rejected', 'end', 'Not approved', 240, 690, ['summary' => 'Expense not approved']),
                ],
                'edges' => [
                    self::edge('trigger', 'large'),
                    self::edge('large', 'finance_approval', 'true'),
                    self::edge('large', 'manager_approval', 'false'),
                    self::edge('finance_approval', 'tell_approved', 'approved'),
                    self::edge('finance_approval', 'tell_rejected', 'rejected'),
                    self::edge('manager_approval', 'tell_approved', 'approved'),
                    self::edge('manager_approval', 'tell_rejected', 'rejected'),
                    self::edge('tell_approved', 'approved'),
                    self::edge('tell_rejected', 'rejected'),
                ],
            ],
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
