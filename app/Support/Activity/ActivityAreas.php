<?php

namespace App\Support\Activity;

/**
 * Activity log actions are named "{area}.{event}". These are the areas, for
 * filtering the activity feed, and coarser groups for charting it.
 */
final class ActivityAreas
{
    /**
     * Action prefix => label.
     *
     * @var array<string, string>
     */
    public const array AREAS = [
        'project' => 'Projects',
        'task' => 'Tasks',
        'issue' => 'Issues',
        'approval' => 'Approvals',
        'workflow' => 'Workflows',
        'inventory' => 'Inventory',
        'purchase_request' => 'Purchase requests',
        'member' => 'Members',
        'settings' => 'Settings',
        'file' => 'Files',
        'organization' => 'Organization',
        'report' => 'Reports',
        'billing' => 'Plan and billing',
    ];

    /**
     * At most five groups, so each keeps its own chart colour.
     *
     * @var array<string, array{label: string, prefixes: list<string>}>
     */
    public const array GROUPS = [
        'work' => ['label' => 'Work', 'prefixes' => ['project', 'task', 'issue', 'file']],
        'approvals' => ['label' => 'Approvals', 'prefixes' => ['approval']],
        'automation' => ['label' => 'Automation', 'prefixes' => ['workflow']],
        'inventory' => ['label' => 'Inventory', 'prefixes' => ['inventory', 'purchase_request']],
        'team' => ['label' => 'Team and settings', 'prefixes' => ['member', 'settings', 'organization', 'report', 'billing']],
    ];

    /**
     * SQL that names the group of an action column. Anything unlisted counts
     * as "team".
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function groupExpression(string $column): string
    {
        return 'case'
            ." when {$column} like 'project.%' or {$column} like 'task.%' or {$column} like 'issue.%' or {$column} like 'file.%' then 'work'"
            ." when {$column} like 'approval.%' then 'approvals'"
            ." when {$column} like 'workflow.%' then 'automation'"
            ." when {$column} like 'inventory.%' or {$column} like 'purchase_request.%' then 'inventory'"
            ." else 'team' end";
    }
}
