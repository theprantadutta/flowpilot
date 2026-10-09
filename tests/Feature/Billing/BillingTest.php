<?php

use App\Actions\Workflows\PublishWorkflow;
use App\Enums\Limit;
use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Jobs\StartTriggeredWorkflows;
use App\Models\BillingCustomer;
use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Notifications\PlanChangeRequestedNotification;
use App\Notifications\PlanNoticeNotification;
use App\Support\Billing\Entitlements;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function subscriptionOf(Organization $organization): Subscription
{
    return Subscription::withoutOrganizationScope()->where('organization_id', $organization->id)->sole();
}

describe('plans', function () {
    it('starts every new organization on a Business trial', function () {
        $user = User::factory()->create();

        actingAs($user)->post(route('onboarding.store'), [
            'name' => 'Northwind Fabrication',
            'industry' => 'manufacturing',
            'company_size' => '51-200',
            'primary_use_case' => 'purchasing',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $organization = Organization::query()->where('name', 'Northwind Fabrication')->sole();
        $subscription = subscriptionOf($organization);

        expect($subscription->plan)->toBe(Plan::Business)
            ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
            ->and((int) round(now()->diffInDays($subscription->trial_ends_at)))->toBe(14);

        actingAs($user)->get(route('overview', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.plan.value', 'business')
                ->where('organization.plan.status', 'trialing')
                ->where('organization.plan.trial_days_left', 14));
    });

    it('treats an ended trial and a canceled subscription as Free', function (SubscriptionStatus $status, ?string $trialEnds) {
        $organization = Organization::factory()->create();
        subscriptionOf($organization)->forceFill(['status' => $status, 'trial_ends_at' => $trialEnds !== null ? now()->modify($trialEnds) : null])->save();

        expect(inTenant($organization, fn () => app(Entitlements::class)->plan()))->toBe(Plan::Free);
    })->with([
        'trial ended' => [SubscriptionStatus::Trialing, '-1 hour'],
        'canceled' => [SubscriptionStatus::Canceled, null],
    ]);

    it('uses limits agreed for one organization', function () {
        $organization = Organization::factory()->onPlan(Plan::Enterprise)->create();
        subscriptionOf($organization)->forceFill(['limit_overrides' => ['members' => 1]])->save();

        actingAs($organization->owner)
            ->post(route('members.invitations.store', $organization), ['email' => 'new@northwind.test', 'role' => 'employee'])
            ->assertSessionHasErrors(['email' => 'Your Enterprise plan includes 1 member, and that is used up. The owner can upgrade under Plan and billing.']);
    });
});

describe('limits', function () {
    it('counts open invitations as seats', function () {
        $organization = Organization::factory()->onPlan(Plan::Free)->create();
        memberIn($organization, Role::Employee);

        actingAs($organization->owner)->post(route('members.invitations.store', $organization), ['email' => 'third@northwind.test', 'role' => 'employee'])->assertSessionHasNoErrors();
        actingAs($organization->owner)->post(route('members.invitations.store', $organization), ['email' => 'fourth@northwind.test', 'role' => 'employee'])->assertSessionHasErrors('email');
        // Re-inviting someone already invited takes no new seat.
        actingAs($organization->owner)->post(route('members.invitations.store', $organization), ['email' => 'third@northwind.test', 'role' => 'employee'])->assertSessionHasNoErrors();
    });

    it('caps workflows on Free', function () {
        $organization = Organization::factory()->onPlan(Plan::Free)->create();
        inTenant($organization, fn () => Workflow::factory()->count(3)->create(['organization_id' => $organization->id]));

        actingAs($organization->owner)
            ->post(route('workflows.store', $organization), ['name' => 'One too many', 'trigger' => 'manual'])
            ->assertSessionHasErrors('name');
    });

    it('keeps approvals and approval steps to plans that include them', function () {
        $organization = Organization::factory()->onPlan(Plan::Free)->create();

        actingAs($organization->owner)
            ->post(route('approvals.store', $organization), ['title' => 'New laptop', 'approver_type' => 'role', 'approver_role' => 'finance', 'priority' => 'medium'])
            ->assertSessionHasErrors(['title' => 'Approvals and approval steps come with the Starter plan and above. You are on Free; the owner can upgrade under Plan and billing.']);

        $workflow = inTenant($organization, fn () => Workflow::factory()->for($organization)->withGraph(['nodes' => [
            step('start', 'trigger'),
            step('ask', 'approval', ['title' => 'Approve it', 'approver' => ['type' => 'role', 'role' => 'manager']]),
            step('approved', 'end', ['summary' => 'Approved']),
            step('rejected', 'end', ['summary' => 'Rejected']),
        ], 'edges' => [path('start', 'ask'), path('ask', 'approved', 'approved'), path('ask', 'rejected', 'rejected')]])->create());

        try {
            inTenant($organization, fn () => app(PublishWorkflow::class)->handle($workflow, $organization->owner));
            $this->fail('Publishing should have been refused.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['definition'][0])->toContain('Approvals and approval steps come with the Starter plan');
        }

        // The same workflow publishes once the plan includes approvals.
        subscriptionOf($organization)->forceFill(['plan' => Plan::Starter])->save();
        app(Entitlements::class)->forget();

        expect(inTenant($organization, fn () => app(PublishWorkflow::class)->handle($workflow->refresh(), $organization->owner))->version)->toBe(1);
    });

    it('stops starting triggered workflows once the month\'s runs are used, and says so once', function () {
        Notification::fake();
        $organization = Organization::factory()->onPlan(Plan::Free)->create();
        subscriptionOf($organization)->forceFill(['plan' => Plan::Enterprise, 'limit_overrides' => ['workflow_runs_per_month' => 0]])->save();

        $task = inTenant($organization, function () use ($organization) {
            publishedWorkflow($organization, [step('start', 'trigger'), step('done', 'end')], [path('start', 'done')], 'task.created');

            return Task::factory()->create(['organization_id' => $organization->id]);
        });

        foreach ([1, 2] as $attempt) {
            dispatch_sync(new StartTriggeredWorkflows($organization->id, 'task.created', 'task', $task->id, occurrence: (string) $attempt));
        }

        expect(inTenant($organization, fn () => WorkflowRun::query()->count()))->toBe(0);
        Notification::assertSentToTimes($organization->owner, PlanNoticeNotification::class, 1);
    });
});

describe('features', function () {
    it('locks reports and exports the plan does not include', function () {
        $organization = Organization::factory()->onPlan(Plan::Free)->create();

        actingAs($organization->owner)->get(route('reports.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.0.value', 'project-progress')
                ->where('reports.0.locked', false)
                ->where('reports.2.value', 'approval-turnaround')
                ->where('reports.2.locked', true)
                ->where('reports.2.plan', 'Starter')
                ->where('can.export', false));

        actingAs($organization->owner)->get(route('reports.show', [$organization, 'approval-turnaround']))->assertRedirect(route('reports.index', $organization));
        actingAs($organization->owner)->get(route('reports.show', [$organization, 'task-completion']))
            ->assertInertia(fn (Assert $page) => $page->where('can.export', false)->where('can.exportLocked', true));
        actingAs($organization->owner)->post(route('reports.exports.store', [$organization, 'task-completion']), ['range' => 'last_30_days'])->assertForbidden();
    });

    it('turns AI off on plans without it', function () {
        config(['ai.providers.freeway.key' => 'fw_test', 'ai.providers.freeway.url' => 'https://freeway.test']);
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();

        actingAs($organization->owner)->get(route('overview', $organization))->assertInertia(fn (Assert $page) => $page->where('ai.enabled', false));
        actingAs($organization->owner)->post(route('ai.brief.store', $organization))->assertSessionHasErrors('brief');
    });
});

describe('billing page', function () {
    it('shows the plan and usage to owners and admins, but only owners change it', function () {
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();
        $admin = memberIn($organization, Role::Admin);

        actingAs($organization->owner)->get(route('billing.show', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organization-settings/Billing')
                ->where('billing.plan.value', 'starter')
                ->where('billing.usage.0.key', Limit::Members->value)
                ->where('billing.usage.0.used', 2)
                ->where('billing.usage.0.limit', 10)
                ->has('plans', 4)
                ->where('can.manage', true));

        actingAs($admin)->get(route('billing.show', $organization))->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
        actingAs($admin)->post(route('billing.plan-requests.store', $organization), ['plan' => 'business'])->assertForbidden();
        actingAs(memberIn($organization, Role::Employee))->get(route('billing.show', $organization))->assertForbidden();
    });

    it('records an upgrade request and tells the FlowPilot team', function () {
        Notification::fake();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();

        actingAs($organization->owner)->post(route('billing.plan-requests.store', $organization), ['plan' => 'business', 'message' => 'Twelve people from next month.'])->assertSessionHasNoErrors();
        actingAs($organization->owner)->post(route('billing.plan-requests.store', $organization), ['plan' => 'enterprise'])->assertSessionHasNoErrors();

        $requests = inTenant($organization, fn () => PlanChangeRequest::query()->orderBy('created_at')->get());

        expect($requests->firstWhere('to_plan', Plan::Business)?->status)->toBe(PlanChangeRequest::WITHDRAWN)
            ->and($requests->firstWhere('to_plan', Plan::Enterprise)?->status)->toBe(PlanChangeRequest::PENDING);
        Notification::assertSentTo($platformAdmin, PlanChangeRequestedNotification::class);

        actingAs($organization->owner)->post(route('billing.plan-requests.store', $organization), ['plan' => 'starter'])->assertSessionHasErrors('plan');
        actingAs($organization->owner)->post(route('billing.plan-requests.store', $organization), ['plan' => 'free'])->assertSessionHasErrors('plan');
    });

    it('lets the owner withdraw a request, but not another organization\'s', function () {
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();
        $other = Organization::factory()->onPlan(Plan::Starter)->create();
        $theirs = inTenant($other, fn () => PlanChangeRequest::query()->create(['from_plan' => Plan::Starter, 'to_plan' => Plan::Business]));

        actingAs($organization->owner)->delete(route('billing.plan-requests.destroy', [$organization, $theirs]))->assertNotFound();
    });

    it('moves to Free straight away, keeping everything', function () {
        Notification::fake();
        $organization = Organization::factory()->onPlan(Plan::Business)->create();

        actingAs($organization->owner)->post(route('billing.free', $organization))->assertRedirect();

        expect(subscriptionOf($organization)->plan)->toBe(Plan::Free);
        Notification::assertSentTo($organization->owner, PlanNoticeNotification::class);
    });

    it('saves billing details', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->patch(route('billing.details.update', $organization), ['legal_name' => 'Northwind Fabrication Ltd', 'email' => 'invoices@northwind.test', 'country' => 'bd'])->assertSessionHasNoErrors();
        actingAs($organization->owner)->patch(route('billing.details.update', $organization), ['email' => 'not an email', 'country' => 'Bangladesh'])->assertSessionHasErrors(['email', 'country']);

        expect(inTenant($organization, fn () => BillingCustomer::query()->sole()->country))->toBe('BD');
    });
});

describe('trials', function () {
    it('reminds owners once before a trial ends, then moves the organization to Free', function () {
        Notification::fake();
        $organization = Organization::factory()->onPlan(Plan::Business, SubscriptionStatus::Trialing)->create();
        subscriptionOf($organization)->forceFill(['trial_ends_at' => now()->addDays(2)])->save();

        artisan('billing:check-trials')->assertSuccessful();
        artisan('billing:check-trials')->assertSuccessful();

        Notification::assertSentToTimes($organization->owner, PlanNoticeNotification::class, 1);
        expect(subscriptionOf($organization)->status)->toBe(SubscriptionStatus::Trialing);

        $this->travel(3)->days();
        artisan('billing:check-trials')->assertSuccessful();

        $subscription = subscriptionOf($organization);

        expect($subscription->plan)->toBe(Plan::Free)
            ->and($subscription->status)->toBe(SubscriptionStatus::Active);
        Notification::assertSentToTimes($organization->owner, PlanNoticeNotification::class, 2);
    });
});
