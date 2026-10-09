<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ChangePlan;
use App\Actions\Billing\RequestPlanChange;
use App\Actions\Billing\UpdateBillingDetails;
use App\Enums\Permission;
use App\Enums\Plan;
use App\Http\Requests\Billing\RequestPlanChangeRequest;
use App\Http\Requests\Billing\UpdateBillingDetailsRequest;
use App\Models\BillingCustomer;
use App\Models\PlanChangeRequest;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\BillingSummary;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan and billing. Owners and admins can see the plan and usage; only
 * members with billing.manage (the owner) change it.
 */
class BillingController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function show(Request $request, BillingSummary $summary): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can(Permission::SettingsManage->value) || $user->can(Permission::BillingManage->value), 403);

        $organization = $this->tenancy->currentOrFail();
        $customer = BillingCustomer::query()->first();
        $pending = PlanChangeRequest::query()
            ->where('status', PlanChangeRequest::PENDING)
            ->with('requester:id,name')
            ->latest()
            ->first();

        return Inertia::render('organization-settings/Billing', [
            'billing' => $summary->for($organization),
            'plans' => $summary->catalog(),
            'pendingRequest' => $pending !== null ? [
                'id' => $pending->id,
                'to' => ['value' => $pending->to_plan->value, 'label' => $pending->to_plan->label()],
                'requester' => $pending->requester?->name,
                'created_at' => $pending->created_at?->toIso8601String(),
            ] : null,
            'details' => [
                'legal_name' => $customer?->legal_name,
                'email' => $customer?->email,
                'address' => $customer?->address,
                'country' => $customer?->country,
                'tax_id' => $customer?->tax_id,
            ],
            'salesEmail' => (string) config('billing.sales_email'),
            'can' => ['manage' => $user->can(Permission::BillingManage->value)],
        ]);
    }

    public function requestPlan(RequestPlanChangeRequest $request, RequestPlanChange $requestPlanChange): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $planRequest = $requestPlanChange->handle($user, $request->plan(), $request->validated('message'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Upgrade to {$planRequest->to_plan->label()} requested. The FlowPilot team will confirm it with you."]);

        return back();
    }

    public function withdrawRequest(Request $request, PlanChangeRequest $planRequest, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize(Permission::BillingManage->value);

        abort_unless($planRequest->status === PlanChangeRequest::PENDING, 404);

        $planRequest->forceFill(['status' => PlanChangeRequest::WITHDRAWN, 'decided_at' => now()])->save();

        /** @var User $user */
        $user = $request->user();
        $activity->log('billing.plan_change_withdrawn', $planRequest, ['to' => $planRequest->to_plan->value], actor: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Upgrade request withdrawn.']);

        return back();
    }

    /**
     * Move to Free straight away. Nothing is deleted; anything over the Free
     * limits stays, but nothing new can be added past them.
     */
    public function moveToFree(Request $request, ChangePlan $changePlan): RedirectResponse
    {
        Gate::authorize(Permission::BillingManage->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $this->tenancy->currentOrFail();

        $changePlan->handle($organization, Plan::Free, $user, "{$user->name} moved {$organization->name} to the Free plan.");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You are now on the Free plan.']);

        return back();
    }

    public function updateDetails(UpdateBillingDetailsRequest $request, UpdateBillingDetails $updateDetails): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateDetails->handle($user, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Billing details saved.']);

        return back();
    }
}
