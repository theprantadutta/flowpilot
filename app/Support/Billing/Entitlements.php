<?php

namespace App\Support\Billing;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\MembershipStatus;
use App\Enums\Plan;
use App\Enums\WorkflowStatus;
use App\Models\AiBrief;
use App\Models\Attachment;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Subscription;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place that knows what an organization's plan allows. Code asks
 * "is this feature included?" or "is there room for one more?", never which
 * plan an organization is on.
 *
 * Works for any organization, not only the current one, so platform
 * administration and scheduled work can use it too.
 */
class Entitlements
{
    /**
     * @var array<string, Subscription|null>
     */
    private array $subscriptions = [];

    public function __construct(private readonly Tenancy $tenancy) {}

    public function subscription(?Organization $organization = null): ?Subscription
    {
        $organization ??= $this->tenancy->currentOrFail();

        return $this->subscriptions[$organization->id] ??= Subscription::withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->first();
    }

    /**
     * The plan whose features and limits apply now. Without a subscription,
     * or once a trial has run out, that is Free.
     */
    public function plan(?Organization $organization = null): Plan
    {
        return $this->subscription($organization)?->effectivePlan() ?? Plan::Free;
    }

    public function allows(Feature $feature, ?Organization $organization = null): bool
    {
        return $this->plan($organization)->includes($feature);
    }

    /**
     * The cap on something countable, or null for unlimited. Limits agreed
     * for one organization override the plan's.
     */
    public function limit(Limit $limit, ?Organization $organization = null): ?int
    {
        $overrides = $this->subscription($organization)->limit_overrides ?? [];

        if (array_key_exists($limit->value, $overrides)) {
            $override = $overrides[$limit->value];

            return is_int($override) ? $override : null;
        }

        return $this->plan($organization)->limit($limit);
    }

    /**
     * How much of a limit is used right now.
     */
    public function usage(Limit $limit, ?Organization $organization = null): int
    {
        return $this->usages($organization, [$limit])[$limit->value];
    }

    /**
     * How much of each limit is used right now, counted in a single query.
     *
     * @param  list<Limit>|null  $limits  Every limit when null.
     * @return array<string, int> Keyed by limit.
     */
    public function usages(?Organization $organization = null, ?array $limits = null): array
    {
        $organization ??= $this->tenancy->currentOrFail();
        $limits ??= Limit::cases();
        $query = DB::query();

        foreach ($limits as $limit) {
            foreach ($this->usageQueries($limit, $organization) as $alias => $subquery) {
                $query->selectSub($subquery, $alias);
            }
        }

        $row = (array) $query->first();
        $count = fn (string $alias): int => (int) ($row[$alias] ?? 0);
        $usages = [];

        foreach ($limits as $limit) {
            $usages[$limit->value] = match ($limit) {
                Limit::Members => $count('members') + $count('members_invited'),
                Limit::StorageMb => (int) ceil($count('storage_bytes') / 1_048_576),
                default => $count($limit->value),
            };
        }

        return $usages;
    }

    /**
     * The counts behind one limit, as subqueries keyed by column alias.
     *
     * @return array<string, Builder<covariant Model>>
     */
    private function usageQueries(Limit $limit, Organization $organization): array
    {
        $now = CarbonImmutable::now($organization->timezone);

        return match ($limit) {
            Limit::Members => [
                'members' => OrganizationMembership::query()
                    ->where('organization_id', $organization->id)
                    ->where('status', MembershipStatus::Active)
                    ->selectRaw('count(*)'),
                // An open invitation holds a seat until it is accepted or revoked.
                'members_invited' => Invitation::query()
                    ->where('organization_id', $organization->id)
                    ->open()
                    ->selectRaw('count(*)'),
            ],
            Limit::Workflows => ['workflows' => Workflow::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', WorkflowStatus::Archived->value)
                ->selectRaw('count(*)')],
            Limit::WorkflowRunsPerMonth => ['workflow_runs_per_month' => WorkflowRun::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('created_at', '>=', $now->startOfMonth()->utc())
                ->selectRaw('count(*)')],
            Limit::StorageMb => ['storage_bytes' => Attachment::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->selectRaw('coalesce(sum(size), 0)')],
            Limit::AiBriefsPerDay => ['ai_briefs_per_day' => AiBrief::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('created_at', '>=', $now->startOfDay()->utc())
                ->selectRaw('count(*)')],
        };
    }

    public function hasRoom(Limit $limit, int $adding = 1, ?Organization $organization = null): bool
    {
        $cap = $this->limit($limit, $organization);

        return $cap === null || $this->usage($limit, $organization) + $adding <= $cap;
    }

    /**
     * @throws ValidationException when the plan does not include the feature.
     */
    public function ensure(Feature $feature, string $field = 'plan', ?Organization $organization = null): void
    {
        if ($this->allows($feature, $organization)) {
            return;
        }

        $plan = Plan::lowestWith($feature);

        throw ValidationException::withMessages([
            $field => $feature->label().' '.($plan !== null ? "come with the {$plan->label()} plan and above" : 'are not part of any plan').". You are on {$this->plan($organization)->label()}; the owner can upgrade under Plan and billing.",
        ]);
    }

    /**
     * @throws ValidationException when adding would go past the limit.
     */
    public function ensureRoom(Limit $limit, int $adding = 1, string $field = 'plan', ?Organization $organization = null): void
    {
        if ($this->hasRoom($limit, $adding, $organization)) {
            return;
        }

        $plan = $this->plan($organization);

        throw ValidationException::withMessages([
            $field => "Your {$plan->label()} plan includes ".$limit->describe($this->limit($limit, $organization)).', and that is used up. The owner can upgrade under Plan and billing.',
        ]);
    }

    /**
     * Forget cached subscriptions after one changes.
     */
    public function forget(?Organization $organization = null): void
    {
        if ($organization === null) {
            $this->subscriptions = [];

            return;
        }

        unset($this->subscriptions[$organization->id]);
    }

    /**
     * Features the organization's plan includes, for the browser to decide what
     * to show. The server still checks every action.
     *
     * @return list<string>
     */
    public function features(?Organization $organization = null): array
    {
        $plan = $this->plan($organization);

        return array_values(array_map(
            fn (Feature $feature): string => $feature->value,
            array_filter(Feature::cases(), fn (Feature $feature): bool => $plan->includes($feature)),
        ));
    }
}
