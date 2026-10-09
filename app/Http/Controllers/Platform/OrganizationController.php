<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Billing\ChangePlan;
use App\Actions\Platform\DeclinePlanRequest;
use App\Actions\Platform\ExtendTrial;
use App\Actions\Platform\SetOrganizationSuspension;
use App\Enums\Limit;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\Plan;
use App\Enums\WorkflowRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangeOrganizationPlanRequest;
use App\Http\Requests\Platform\ExtendTrialRequest;
use App\Http\Requests\Platform\PlatformNoteRequest;
use App\Models\ActivityLog;
use App\Models\AiBrief;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Support\Activity\ActivityPresenter;
use App\Support\Billing\BillingSummary;
use App\Support\Search\Contains;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every organization, for the FlowPilot team: who runs it, what plan it is
 * on, what it uses, and the levers support needs.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->limit(80, '')->toString();
        $plan = Plan::tryFrom($request->string('plan')->toString());
        $status = OrganizationStatus::tryFrom($request->string('status')->toString());

        $organizations = Organization::query()
            ->with(['owner:id,name,email', 'subscription'])
            ->withCount(['memberships as members_count' => fn (Builder $memberships) => $memberships->where('status', MembershipStatus::Active)])
            ->when($q !== '', fn (Builder $query) => Contains::any($query, ['name', 'slug'], $q))
            ->when($plan, fn (Builder $query, Plan $plan) => $query->whereIn('id', Subscription::withoutOrganizationScope()->where('plan', $plan->value)->select('organization_id')))
            ->when($status, fn (Builder $query, OrganizationStatus $status) => $query->where('status', $status->value))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'owner' => ['name' => $organization->owner->name, 'email' => $organization->owner->email],
                'members' => (int) $organization->getAttribute('members_count'),
                'plan' => $organization->subscription !== null ? [
                    'label' => $organization->subscription->effectivePlan()->label(),
                    'status' => $organization->subscription->status->toOption(),
                    'trial_days_left' => $organization->subscription->trialDaysLeft(),
                ] : null,
                'created_at' => $organization->created_at?->toIso8601String(),
            ]);

        // Not "organizations": that shared prop lists the admin's own organizations.
        return Inertia::render('platform/Organizations', [
            'tenants' => $organizations,
            'filters' => ['q' => $q, 'plan' => $plan?->value, 'status' => $status?->value],
            'plans' => array_map(fn (Plan $plan): array => ['value' => $plan->value, 'label' => $plan->label()], Plan::cases()),
        ]);
    }

    public function show(Organization $organization, BillingSummary $billing, ActivityPresenter $presenter): Response
    {
        $organization->load(['owner:id,name,email', 'subscription']);
        $since = now()->subDays(30);

        $runs = WorkflowRun::withoutOrganizationScope()->where('organization_id', $organization->id)->where('created_at', '>=', $since);

        // One round trip for every figure in the side panel.
        $activity = (array) DB::query()
            ->selectSub(Workflow::withoutOrganizationScope()->where('organization_id', $organization->id)->selectRaw('count(*)'), 'workflows')
            ->selectSub($runs->clone()->selectRaw('count(*)'), 'runs')
            ->selectSub($runs->clone()->where('status', WorkflowRunStatus::Failed->value)->selectRaw('count(*)'), 'failed_runs')
            ->selectSub(AiBrief::withoutOrganizationScope()->where('organization_id', $organization->id)->where('created_at', '>=', $since)->selectRaw('count(*)'), 'ai_briefs')
            ->selectSub(Invitation::query()->where('organization_id', $organization->id)->open()->selectRaw('count(*)'), 'open_invitations')
            ->first();

        // Not "organization": that shared prop is the organization being worked in.
        return Inertia::render('platform/OrganizationShow', [
            'tenant' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'industry' => $organization->industry?->label(),
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'owner' => ['name' => $organization->owner->name, 'email' => $organization->owner->email],
                'created_at' => $organization->created_at?->toIso8601String(),
            ],
            'billing' => $billing->for($organization),
            'limits' => array_map(fn (Limit $limit): array => [
                'key' => $limit->value,
                'label' => $limit === Limit::StorageMb ? "{$limit->capLabel()} in MB" : $limit->capLabel(),
            ], Limit::cases()),
            'limitOverrides' => $organization->subscription?->limit_overrides,
            'plans' => array_map(fn (Plan $plan): array => ['value' => $plan->value, 'label' => $plan->label()], Plan::cases()),
            'activity' => [
                'workflows' => (int) ($activity['workflows'] ?? 0),
                'runs' => (int) ($activity['runs'] ?? 0),
                'failed_runs' => (int) ($activity['failed_runs'] ?? 0),
                'ai_briefs' => (int) ($activity['ai_briefs'] ?? 0),
            ],
            'openInvitations' => (int) ($activity['open_invitations'] ?? 0),
            'members' => OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->with('user:id,name,email,two_factor_confirmed_at')
                ->orderBy('created_at')
                ->get()
                ->map(fn (OrganizationMembership $membership): array => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->label(),
                    'status' => $membership->status->value,
                    'two_factor' => $membership->user->two_factor_confirmed_at !== null,
                    'last_active_at' => $membership->last_active_at?->toIso8601String(),
                ]),
            'requests' => PlanChangeRequest::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->with('requester:id,name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (PlanChangeRequest $request): array => [
                    'id' => $request->id,
                    'from' => $request->from_plan->label(),
                    'to' => ['value' => $request->to_plan->value, 'label' => $request->to_plan->label()],
                    'status' => $request->status,
                    'message' => $request->message,
                    'requester' => $request->requester?->name,
                    'decision_note' => $request->decision_note,
                    'created_at' => $request->created_at?->toIso8601String(),
                ]),
            'audit' => Inertia::defer(fn (): array => ActivityLog::withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))
                ->all()),
        ]);
    }

    public function changePlan(ChangeOrganizationPlanRequest $request, Organization $organization, ChangePlan $changePlan): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $plan = $request->plan();
        $note = $request->validated('note');

        $changePlan->handle(
            $organization,
            $plan,
            $admin,
            "The FlowPilot team moved {$organization->name} to the {$plan->label()} plan.".(is_string($note) && $note !== '' ? " {$note}" : ''),
            $request->limitOverrides(),
            actorType: 'platform',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$organization->name} is now on {$plan->label()}."]);

        return back();
    }

    public function extendTrial(ExtendTrialRequest $request, Organization $organization, ExtendTrial $extendTrial): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $subscription = $extendTrial->handle($organization, $admin, (int) $request->validated('days'), $request->plan());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Trial runs until {$subscription->trial_ends_at?->format('M j, Y')}."]);

        return back();
    }

    public function suspend(PlatformNoteRequest $request, Organization $organization, SetOrganizationSuspension $suspension): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $suspension->handle($organization, $admin, true, $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$organization->name} is suspended."]);

        return back();
    }

    public function reactivate(Request $request, Organization $organization, SetOrganizationSuspension $suspension): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $suspension->handle($organization, $admin, false);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$organization->name} is active again."]);

        return back();
    }

    public function declineRequest(PlatformNoteRequest $request, PlanChangeRequest $platformPlanRequest, DeclinePlanRequest $decline): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $decline->handle($platformPlanRequest, $admin, $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request declined and the owner told why.']);

        return back();
    }
}
