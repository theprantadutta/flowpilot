<?php

namespace App\Support\Platform;

use App\Enums\OrganizationStatus;
use App\Enums\Plan;
use App\Enums\ReportBucket;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\ActivityLog;
use App\Models\AiBrief;
use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Reports\Chart;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Numbers across every organization, for the FlowPilot team. Crosses tenants
 * on purpose, so every query says so with withoutOrganizationScope().
 */
class PlatformStats
{
    /**
     * @return list<array{key: string, label: string, value: int|float|string|null, format: string, hint: string|null, tone: string|null}>
     */
    public function tiles(): array
    {
        $since = now()->subDays(30);
        $runs = WorkflowRun::withoutOrganizationScope()->where('created_at', '>=', $since);
        $briefs = AiBrief::withoutOrganizationScope()->where('created_at', '>=', $since);
        $subscriptions = Subscription::withoutOrganizationScope();

        // Every figure in one round trip; the database may be a network hop away.
        $counts = array_map(fn (mixed $value): int => (int) $value, (array) DB::query()
            ->selectSub(Organization::query()->where('status', OrganizationStatus::Active->value)->selectRaw('count(*)'), 'organizations')
            ->selectSub(Organization::query()->where('status', OrganizationStatus::Suspended->value)->selectRaw('count(*)'), 'suspended')
            ->selectSub($subscriptions->clone()->where('status', SubscriptionStatus::Active->value)->where('plan', '!=', Plan::Free->value)->selectRaw('count(*)'), 'paying')
            ->selectSub($subscriptions->clone()->where('status', SubscriptionStatus::Trialing->value)->selectRaw('count(*)'), 'trials')
            ->selectSub(ActivityLog::withoutOrganizationScope()->where('created_at', '>=', $since)->where('actor_type', 'user')->selectRaw('count(distinct actor_id)'), 'active_people')
            ->selectSub(User::query()->selectRaw('count(*)'), 'accounts')
            ->selectSub($runs->clone()->selectRaw('count(*)'), 'runs')
            ->selectSub($runs->clone()->where('status', WorkflowRunStatus::Failed->value)->selectRaw('count(*)'), 'failed_runs')
            ->selectSub($briefs->clone()->selectRaw('count(*)'), 'briefs')
            ->selectSub($briefs->clone()->selectRaw('coalesce(sum(coalesce(prompt_tokens, 0) + coalesce(completion_tokens, 0)), 0)'), 'tokens')
            ->selectSub($briefs->clone()->where('used_fallback', true)->selectRaw('count(*)'), 'fallbacks')
            ->selectSub(PlanChangeRequest::withoutOrganizationScope()->where('status', PlanChangeRequest::PENDING)->selectRaw('count(*)'), 'pending')
            ->first());

        $totalRuns = $counts['runs'] ?? 0;
        $failedRuns = $counts['failed_runs'] ?? 0;
        $accounts = $counts['accounts'] ?? 0;
        $pending = $counts['pending'] ?? 0;
        $fallbacks = $counts['fallbacks'] ?? 0;

        return [
            $this->tile('organizations', 'Organizations', $counts['organizations'] ?? 0, hint: ($counts['suspended'] ?? 0).' suspended'),
            $this->tile('paying', 'On paid plans', $counts['paying'] ?? 0, hint: ($counts['trials'] ?? 0).' on a trial'),
            $this->tile('active_people', 'Active people', $counts['active_people'] ?? 0, hint: 'Last 30 days, of '.number_format($accounts).' '.Str::plural('account', $accounts)),
            $this->tile('runs', 'Workflow runs', $totalRuns, hint: 'Last 30 days'.($totalRuns > 0 ? ', '.round($failedRuns / $totalRuns * 100, 1).'% failed' : ''), tone: $totalRuns > 0 && $failedRuns / $totalRuns > 0.05 ? 'warning' : null),
            $this->tile('ai', 'AI briefs', $counts['briefs'] ?? 0, hint: 'Last 30 days, '.number_format($counts['tokens'] ?? 0).' tokens'.($fallbacks > 0 ? ", {$fallbacks} fell back" : '')),
            $this->tile('requests', 'Upgrade requests', $pending, hint: $pending > 0 ? 'Waiting on the team' : 'None waiting', tone: $pending > 0 ? 'warning' : null),
        ];
    }

    /**
     * Twelve weeks of sign-ups and thirty days of automation, as report charts.
     *
     * @return list<array<string, mixed>>
     */
    public function charts(): array
    {
        $weeks = ReportQuery::make('UTC', 'custom', CarbonImmutable::now('UTC')->subWeeks(11)->startOfWeek()->toDateString(), CarbonImmutable::now('UTC')->toDateString(), ReportBucket::Week);
        $weekDates = SqlDates::for($weeks);

        $signUps = Organization::query()
            ->whereBetween('created_at', $weeks->between())
            ->selectRaw($weekDates->bucket('created_at').' as bucket, count(*) as total', $weekDates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $days = ReportQuery::make('UTC', 'last_30_days', bucket: ReportBucket::Day);
        $dayDates = SqlDates::for($days);

        $runs = WorkflowRun::withoutOrganizationScope()
            ->whereBetween('created_at', $days->between())
            ->selectRaw($dayDates->bucket('created_at').' as bucket, status, count(*) as total', $dayDates->bindings())
            ->groupBy('bucket', 'status')
            ->toBase()
            ->get();

        $series = fn (array $statuses): array => Timeline::fill($days, $runs->whereIn('status', $statuses)
            ->groupBy('bucket')
            ->map(fn ($rows): int => (int) $rows->sum('total'))
            ->all());

        return [
            Chart::columns('signups', 'New organizations')
                ->describe('Last 12 weeks')
                ->labels(Timeline::labels($weeks))
                ->series('signups', 'New organizations', 'chart-1', Timeline::fill($weeks, $signUps))
                ->toArray(),
            Chart::columns('runs', 'Workflow runs across FlowPilot')
                ->describe('Last 30 days, by outcome')
                ->stacked()
                ->labels(Timeline::labels($days))
                ->series('completed', 'Completed', 'success', $series([WorkflowRunStatus::Completed->value]))
                ->series('failed', 'Failed', 'danger', $series([WorkflowRunStatus::Failed->value]))
                ->series('cancelled', 'Cancelled', 'neutral', $series([WorkflowRunStatus::Cancelled->value]))
                ->toArray(),
        ];
    }

    /**
     * Organizations per plan, counting ended trials as Free.
     *
     * @return array<string, mixed>
     */
    public function plans(): array
    {
        $counts = array_fill_keys(array_map(fn (Plan $plan): string => $plan->value, Plan::cases()), 0);

        foreach (Subscription::withoutOrganizationScope()->get(['id', 'plan', 'status', 'trial_ends_at']) as $subscription) {
            $counts[$subscription->effectivePlan()->value]++;
        }

        return Chart::bars('plans', 'Organizations by plan')
            ->describe('Trials count as the plan being tried; ended trials as Free')
            ->labels(array_map(fn (Plan $plan): string => $plan->label(), Plan::cases()))
            ->series('organizations', 'Organizations', 'chart-1', array_values($counts))
            ->toArray();
    }

    /**
     * @return array{key: string, label: string, value: int|float|string|null, format: string, hint: string|null, tone: string|null}
     */
    private function tile(string $key, string $label, int|float|string|null $value, string $format = 'number', ?string $hint = null, ?string $tone = null): array
    {
        return compact('key', 'label', 'value', 'format', 'hint', 'tone');
    }
}
