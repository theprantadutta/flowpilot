<?php

use App\Enums\Limit;
use App\Enums\OrganizationStatus;
use App\Enums\Plan;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PlanNoticeNotification;
use App\Support\Billing\Entitlements;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/**
 * A platform administrator who confirmed their password a moment ago, as
 * every platform page requires.
 */
function platformAdmin(): User
{
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

function platformSubscription(Organization $organization): Subscription
{
    return Subscription::withoutOrganizationScope()->where('organization_id', $organization->id)->sole();
}

function pendingRequest(Organization $organization, Plan $to, ?string $message = null): PlanChangeRequest
{
    return inTenant($organization, fn () => PlanChangeRequest::query()->create([
        'requested_by' => $organization->owner_id,
        'from_plan' => platformSubscription($organization)->plan,
        'to_plan' => $to,
        'message' => $message,
    ]));
}

describe('access', function () {
    it('hides every platform page from people who are not platform administrators', function (string $method, Closure $url) {
        $organization = Organization::factory()->create();
        $request = pendingRequest($organization, Plan::Enterprise);

        actingAs($organization->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->call($method, $url($organization, $request))
            ->assertNotFound();
    })->with([
        'dashboard' => ['GET', fn () => route('platform.dashboard')],
        'organizations' => ['GET', fn () => route('platform.organizations.index')],
        'organization' => ['GET', fn (Organization $organization) => route('platform.organizations.show', $organization)],
        'change plan' => ['POST', fn (Organization $organization) => route('platform.organizations.plan.update', $organization)],
        'suspend' => ['POST', fn (Organization $organization) => route('platform.organizations.suspension.store', $organization)],
        'decline' => ['POST', fn (Organization $organization, PlanChangeRequest $request) => route('platform.plan-requests.decline', $request)],
        'people' => ['GET', fn () => route('platform.users.index')],
        'health' => ['GET', fn () => route('platform.health')],
        'failed jobs' => ['GET', fn () => route('platform.failed-jobs.index')],
        'audit' => ['GET', fn () => route('platform.audit')],
    ]);

    it('sends guests to log in', function () {
        get(route('platform.dashboard'))->assertRedirect(route('login'));
    });

    it('asks a platform administrator to confirm their password first', function () {
        $admin = User::factory()->platformAdmin()->create();

        actingAs($admin)->get(route('platform.dashboard'))->assertRedirect(route('password.confirm'));
    });

    it('only grants platform administration from the command line', function () {
        $user = User::factory()->create(['email' => 'support@flowpilot.test']);

        artisan('platform:admin', ['email' => 'Support@FlowPilot.test'])->assertSuccessful();
        expect($user->fresh()->is_platform_admin)->toBeTrue();

        artisan('platform:admin', ['email' => 'support@flowpilot.test', '--revoke' => true])->assertSuccessful();
        expect($user->fresh()->is_platform_admin)->toBeFalse();

        artisan('platform:admin', ['email' => 'nobody@flowpilot.test'])->assertFailed();

        $unverified = User::factory()->unverified()->create();
        artisan('platform:admin', ['email' => $unverified->email])->assertFailed();
        expect($unverified->fresh()->is_platform_admin)->toBeFalse();
    });

    it('offers the platform link only to platform administrators', function () {
        $organization = Organization::factory()->create();
        $admin = User::factory()->platformAdmin()->memberOf($organization, Role::Admin)->create();

        actingAs($organization->owner)->get(route('overview', $organization))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_platform_admin', false));
        actingAs($admin)->get(route('overview', $organization))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_platform_admin', true));
    });
});

describe('dashboard', function () {
    it('summarizes every organization and lists upgrade requests oldest first', function () {
        $first = Organization::factory()->onPlan(Plan::Starter)->create(['name' => 'Northwind Fabrication']);
        $second = Organization::factory()->onPlan(Plan::Free)->create(['name' => 'Harbor Logistics']);
        $this->travel(-2)->hours();
        $older = pendingRequest($second, Plan::Business, 'Busy season ahead.');
        $this->travelBack();
        pendingRequest($first, Plan::Business);

        platformAdmin();

        get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/Dashboard')
                ->where('tiles.0.label', 'Organizations')
                ->where('tiles.0.value', 2)
                ->where('tiles.5.value', 2)
                ->where('plans.labels', ['Free', 'Starter', 'Business', 'Enterprise'])
                ->where('plans.series.0.values', [1, 1, 0, 0])
                ->has('requests', 2)
                ->where('requests.0.id', $older->id)
                ->where('requests.0.organization.slug', $second->slug)
                ->where('requests.0.message', 'Busy season ahead.')
                ->missing('charts')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('charts', 2)));
    });
});

describe('organizations', function () {
    it('lists organizations with their plan and finds them by name, plan and status', function () {
        Organization::factory()->onPlan(Plan::Starter)->create(['name' => 'Northwind Fabrication']);
        Organization::factory()->onPlan(Plan::Business)->create(['name' => 'Harbor Logistics']);
        Organization::factory()->suspended()->create(['name' => 'Cedar Clinic']);

        platformAdmin();

        get(route('platform.organizations.index'))->assertInertia(fn (Assert $page) => $page
            ->component('platform/Organizations')
            ->has('tenants.data', 3)
            ->has('plans', 4));

        get(route('platform.organizations.index', ['q' => 'north']))->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Northwind Fabrication')
            ->where('tenants.data.0.plan.label', 'Starter')
            ->where('tenants.data.0.members', 1));

        get(route('platform.organizations.index', ['plan' => 'business']))->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 2));

        get(route('platform.organizations.index', ['status' => 'suspended']))->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Cedar Clinic'));
    });

    it('shows an organization with its usage, members, requests and activity', function () {
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();
        memberIn($organization, Role::Manager);
        pendingRequest($organization, Plan::Business, 'Twelve people from next month.');

        platformAdmin();

        get(route('platform.organizations.show', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/OrganizationShow')
                ->where('tenant.slug', $organization->slug)
                ->where('billing.plan.value', 'starter')
                ->where('billing.usage.0.key', 'members')
                ->where('billing.usage.0.used', 2)
                ->has('members', 2)
                ->has('requests', 1)
                ->where('requests.0.status', 'pending')
                ->has('limits', count(Limit::cases()))
                // The admin's own organizations stay in the shared prop.
                ->where('organization', null)
                ->missing('audit')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('audit')));
    });

    it('moves an organization to Enterprise with custom limits and settles the request', function () {
        Notification::fake();
        $organization = Organization::factory()->onPlan(Plan::Business, SubscriptionStatus::Trialing)->create();
        $request = pendingRequest($organization, Plan::Enterprise);
        $admin = platformAdmin();

        $this->post(route('platform.organizations.plan.update', $organization), [
            'plan' => 'enterprise',
            'note' => 'Agreed on the call today.',
            'limits' => ['members' => '250', 'workflows' => '', 'workflow_runs_per_month' => '100000', 'storage_mb' => null, 'ai_briefs_per_day' => '50'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $subscription = platformSubscription($organization);

        expect($subscription->plan)->toBe(Plan::Enterprise)
            ->and($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->trial_ends_at)->toBeNull()
            ->and($subscription->limit_overrides)->toBe(['members' => 250, 'workflows' => null, 'workflow_runs_per_month' => 100000, 'storage_mb' => null, 'ai_briefs_per_day' => 50])
            ->and(app(Entitlements::class)->limit(Limit::Members, $organization))->toBe(250)
            ->and($request->fresh()->status)->toBe(PlanChangeRequest::APPROVED)
            ->and($request->fresh()->decided_by)->toBe($admin->id);

        $log = ActivityLog::withoutOrganizationScope()->where('organization_id', $organization->id)->where('action', 'billing.plan_changed')->sole();
        expect($log->actor_type)->toBe('platform')
            ->and($log->actor_id)->toBe($admin->id);

        Notification::assertSentTo($organization->owner, PlanNoticeNotification::class);
    });

    it('drops custom limits for plans other than Enterprise and rejects bad input', function () {
        $organization = Organization::factory()->onPlan(Plan::Enterprise)->create();
        platformSubscription($organization)->forceFill(['limit_overrides' => ['members' => 5]])->save();
        platformAdmin();

        $this->post(route('platform.organizations.plan.update', $organization), ['plan' => 'starter', 'limits' => ['members' => '999']])
            ->assertSessionHasNoErrors();

        expect(platformSubscription($organization)->plan)->toBe(Plan::Starter)
            ->and(platformSubscription($organization)->limit_overrides)->toBeNull();

        $this->post(route('platform.organizations.plan.update', $organization), ['plan' => 'platinum'])->assertSessionHasErrors('plan');
        $this->post(route('platform.organizations.plan.update', $organization), ['plan' => 'enterprise', 'limits' => ['members' => '-1']])->assertSessionHasErrors('limits.members');
    });

    it('extends a running trial and starts one where none is running', function () {
        Notification::fake();
        $trialing = Organization::factory()->onPlan(Plan::Business, SubscriptionStatus::Trialing)->create();
        $endsAt = platformSubscription($trialing)->trial_ends_at;
        $free = Organization::factory()->onPlan(Plan::Free)->create();
        platformAdmin();

        $this->post(route('platform.organizations.trial.update', $trialing), ['days' => 10, 'plan' => 'business'])->assertSessionHasNoErrors();
        $this->post(route('platform.organizations.trial.update', $free), ['days' => 7, 'plan' => 'starter'])->assertSessionHasNoErrors();

        expect(platformSubscription($trialing)->trial_ends_at?->toDateTimeString())->toBe($endsAt?->addDays(10)->toDateTimeString())
            ->and(platformSubscription($free)->plan)->toBe(Plan::Starter)
            ->and(platformSubscription($free)->status)->toBe(SubscriptionStatus::Trialing)
            ->and((int) round(now()->diffInDays(platformSubscription($free)->trial_ends_at)))->toBe(7)
            ->and(app(Entitlements::class)->plan($free))->toBe(Plan::Starter);

        Notification::assertSentTo($free->owner, PlanNoticeNotification::class);

        $this->post(route('platform.organizations.trial.update', $free), ['days' => 91, 'plan' => 'starter'])->assertSessionHasErrors('days');
        $this->post(route('platform.organizations.trial.update', $free), ['days' => 7, 'plan' => 'free'])->assertSessionHasErrors('plan');
    });

    it('suspends an organization with a reason, locking members out until it is reactivated', function () {
        $organization = Organization::factory()->create();
        platformAdmin();

        $this->post(route('platform.organizations.suspension.store', $organization), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('platform.organizations.suspension.store', $organization), ['note' => 'Chargeback under review.'])->assertSessionHasNoErrors();

        expect($organization->fresh()->status)->toBe(OrganizationStatus::Suspended);
        expect(ActivityLog::withoutOrganizationScope()->where('action', 'platform.organization_suspended')->sole()->properties)
            ->toBe(['reason' => 'Chargeback under review.']);

        $this->post(route('platform.organizations.suspension.store', $organization), ['note' => 'Again.'])->assertSessionHasErrors('status');

        actingAs($organization->owner)->get(route('overview', $organization))->assertForbidden();

        platformAdmin();
        $this->delete(route('platform.organizations.suspension.destroy', $organization))->assertSessionHasNoErrors();

        expect($organization->fresh()->status)->toBe(OrganizationStatus::Active);
        actingAs($organization->owner->fresh())->get(route('overview', $organization))->assertOk();
    });

    it('declines an upgrade request with a reply to the owner', function () {
        Notification::fake();
        $organization = Organization::factory()->onPlan(Plan::Starter)->create();
        $request = pendingRequest($organization, Plan::Enterprise);
        $admin = platformAdmin();

        $this->post(route('platform.plan-requests.decline', $request), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('platform.plan-requests.decline', $request), ['note' => 'Business covers what you described; happy to talk.'])->assertSessionHasNoErrors();

        $request->refresh();
        expect($request->status)->toBe(PlanChangeRequest::DECLINED)
            ->and($request->decided_by)->toBe($admin->id)
            ->and($request->decision_note)->toBe('Business covers what you described; happy to talk.')
            ->and(platformSubscription($organization)->plan)->toBe(Plan::Starter);

        Notification::assertSentTo($organization->owner, PlanNoticeNotification::class);

        $this->post(route('platform.plan-requests.decline', $request), ['note' => 'Twice.'])->assertSessionHasErrors('note');
        $this->post(route('platform.plan-requests.decline', Str::uuid7()->toString()), ['note' => 'Missing.'])->assertNotFound();
    });
});

describe('people', function () {
    it('lists accounts with their organizations and finds them', function () {
        $organization = Organization::factory()->create(['name' => 'Northwind Fabrication']);
        memberIn($organization, Role::Finance, ['name' => 'Priya Nair', 'email' => 'priya@northwind.test']);
        platformAdmin();

        get(route('platform.users.index', ['q' => 'priya']))->assertInertia(fn (Assert $page) => $page
            ->component('platform/Users')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'priya@northwind.test')
            ->where('users.data.0.organizations.0.name', 'Northwind Fabrication')
            ->where('users.data.0.organizations.0.role', 'Finance'));

        get(route('platform.users.index', ['admins' => 1]))->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.is_platform_admin', true));
    });

    it('sends a locked-out person a password reset link', function () {
        Notification::fake();
        $user = User::factory()->create();
        platformAdmin();

        $this->post(route('platform.users.password-reset', $user))->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('system', function () {
    it('checks what FlowPilot depends on', function () {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['status' => 'ok'])]);
        config(['ai.providers.freeway.url' => 'https://freeway.test', 'ai.providers.freeway.key' => 'test-key']);
        platformAdmin();

        get(route('platform.health'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/Health')
                ->where('versions.1.label', 'Laravel')
                ->missing('checks')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('checks', 7)
                    ->where('checks.0.key', 'database')
                    ->where('checks.0.status', 'ok')
                    ->where('checks.1.status', 'ok')
                    ->where('checks.3.key', 'scheduler')
                    ->where('checks.3.status', 'failing')));
    });

    it('lists, retries and clears failed jobs', function () {
        $uuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\GenerateReportExport', 'attempts' => 3, 'data' => []]);
        app('queue.failer')->log('database', 'long', (string) $payload, new RuntimeException('Freeway timed out'));
        platformAdmin();

        get(route('platform.failed-jobs.index'))->assertInertia(fn (Assert $page) => $page
            ->component('platform/FailedJobs')
            ->has('jobs', 1)
            ->where('jobs.0.id', $uuid)
            ->where('jobs.0.job', 'GenerateReportExport')
            ->where('jobs.0.error', fn (string $error) => str_starts_with($error, 'RuntimeException: Freeway timed out') && ! str_contains($error, 'Stack trace')));

        $this->post(route('platform.failed-jobs.retry', $uuid))->assertRedirect();

        expect(DB::table('failed_jobs')->count())->toBe(0)
            ->and(DB::table('jobs')->where('queue', 'long')->count())->toBe(1);

        $second = (string) Str::uuid();
        app('queue.failer')->log('database', 'default', (string) json_encode(['uuid' => $second, 'displayName' => 'App\\Jobs\\SendWebhook', 'data' => []]), new RuntimeException('Gone'));

        $this->delete(route('platform.failed-jobs.destroy', $second))->assertRedirect();
        expect(DB::table('failed_jobs')->count())->toBe(0);

        $this->post(route('platform.failed-jobs.retry', (string) Str::uuid()))->assertNotFound();
        $this->delete(route('platform.failed-jobs.destroy', (string) Str::uuid()))->assertNotFound();
    });

    it('shows the activity of every organization and filters it', function () {
        $northwind = Organization::factory()->create(['name' => 'Northwind Fabrication']);
        $harbor = Organization::factory()->create(['name' => 'Harbor Logistics']);
        $admin = platformAdmin();
        $this->post(route('platform.organizations.suspension.store', $harbor), ['note' => 'Unpaid invoices.']);

        get(route('platform.audit'))->assertInertia(fn (Assert $page) => $page
            ->component('platform/Audit')
            ->where('entries.data.0.action', 'platform.organization_suspended')
            ->where('entries.data.0.actor', 'FlowPilot support')
            ->where('entries.data.0.staff', $admin->name)
            ->where('entries.data.0.organization.slug', $harbor->slug));

        get(route('platform.audit', ['organization' => 'harbor']))->assertInertia(fn (Assert $page) => $page
            ->where('filters.organization', 'harbor')
            ->has('entries.data', 1)
            ->where('entries.data.0.organization.slug', $harbor->slug));

        get(route('platform.audit', ['organization' => $northwind->slug]))->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0));

        get(route('platform.audit', ['actor' => 'platform']))->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1));

        get(route('platform.audit', ['organization' => 'nobody-by-this-name']))->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0));
    });
});
