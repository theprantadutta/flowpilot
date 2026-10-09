<?php

namespace App\Support\Ai\Brief;

/**
 * A plain brief written from the facts without a model, used when the model
 * cannot be reached or its answer cannot be trusted. Less fluent, still true.
 *
 * @phpstan-import-type Fact from BriefFacts
 * @phpstan-import-type BriefContent from BriefParser
 */
class BriefFallback
{
    private const array ACTIONS = [
        'tasks' => 'Open the task',
        'approvals' => 'Open the request',
        'issues' => 'Open the issue',
        'workflows' => 'Open the run',
        'inventory' => 'Check the stock',
        'projects' => 'Open the project',
    ];

    /**
     * @param  list<Fact>  $facts
     * @return BriefContent
     */
    public function from(array $facts): array
    {
        if ($facts === []) {
            return self::calm();
        }

        $items = array_map(fn (array $fact): array => [
            'title' => $fact['label'],
            'detail' => $this->detail($fact),
            'severity' => $fact['priority'] === 1 ? 'high' : 'medium',
            'facts' => [$fact['id']],
        ], array_slice($facts, 0, 5));

        $count = count($facts);

        return [
            'headline' => $count === 1 ? 'One thing needs your attention.' : "{$count} things need your attention.",
            'items' => $items,
            'actions' => array_map(fn (array $fact): array => [
                'label' => self::ACTIONS[$fact['area']] ?? 'Open it',
                'fact' => $fact['id'],
            ], array_slice($facts, 0, 3)),
        ];
    }

    /**
     * @return BriefContent
     */
    public static function calm(): array
    {
        return [
            'headline' => 'Nothing needs your attention right now.',
            'items' => [],
            'actions' => [],
        ];
    }

    /**
     * @param  Fact  $fact
     */
    private function detail(array $fact): string
    {
        $data = $fact['data'];

        return match (true) {
            isset($data['days_late']) => "Due {$data['due']}, {$data['days_late']} ".((int) $data['days_late'] === 1 ? 'day' : 'days').' late.',
            isset($data['count']) => "{$data['count']} tasks are past their due date.",
            isset($data['waiting_hours'], $data['waiting_for']) => "Waiting {$data['waiting_hours']} hours for {$data['waiting_for']}.",
            isset($data['waiting_hours'], $data['total']) => "{$data['total']}, waiting {$data['waiting_hours']} hours for a decision.",
            isset($data['severity']) => "{$data['severity']} severity, open for {$data['open_days']} days, assigned to {$data['assigned_to']}.",
            isset($data['error']) => "Failed {$data['failed_hours_ago']} hours ago: {$data['error']}",
            isset($data['on_hand']) => "{$data['on_hand']} on hand, reorder point {$data['reorder_point']}.".(($data['purchase_already_requested'] ?? false) ? ' A purchase is already requested.' : ''),
            isset($data['progress_percent']) => ($data['past_due'] ?? false ? "Past its due date of {$data['due']}" : "Due {$data['due']}").", {$data['progress_percent']}% done.",
            default => 'Needs a look.',
        };
    }
}
