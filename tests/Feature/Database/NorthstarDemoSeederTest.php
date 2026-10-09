<?php

use App\Enums\ApprovalStatus;
use App\Enums\Plan;
use App\Enums\PurchaseRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStatus;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Database\Seeders\NorthstarDemoSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

/**
 * @return array{organization: Organization}
 */
function seedNorthstar(): array
{
    config(['flowpilot.demo.password' => 'demo-password-123']);
    seed(NorthstarDemoSeeder::class);

    return ['organization' => Organization::query()->where('slug', NorthstarDemoSeeder::SLUG)->sole()];
}

it('refuses to run in production', function () {
    config(['flowpilot.demo.password' => 'demo-password-123']);
    app()->detectEnvironment(fn () => 'production');

    // db:seed itself asks for confirmation in production; the seeder must refuse even when run directly.
    expect(fn () => app(NorthstarDemoSeeder::class)->run())->toThrow(RuntimeException::class, 'never runs in production');
    expect(Organization::query()->count())->toBe(0);
});

it('refuses to run without a demo password', function () {
    config(['flowpilot.demo.password' => null]);

    expect(fn () => seed(NorthstarDemoSeeder::class))->toThrow(RuntimeException::class, 'DEMO_PASSWORD');
    expect(User::query()->count())->toBe(0);
});

it('builds Northstar Manufacturing through the real workflows', function () {
    ['organization' => $organization] = seedNorthstar();

    expect($organization->memberships()->count())->toBe(7);

    $subscription = Subscription::withoutOrganizationScope()->where('organization_id', $organization->id)->sole();
    expect($subscription->plan)->toBe(Plan::Business)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);

    inTenant($organization, function () {
        expect(Workflow::query()->where('status', WorkflowStatus::Active->value)->pluck('name')->sort()->values()->all())
            ->toBe(['Critical issue escalation', 'Expense approval', 'Leave request', 'Low stock alert', 'Purchase approval']);

        expect(Project::query()->pluck('name')->sort()->values()->all())->toBe(['Factory Expansion', 'Quality Improvement', 'Warehouse Upgrade'])
            ->and(Task::query()->count())->toBe(14)
            ->and(Issue::query()->count())->toBe(4);

        $requests = PurchaseRequest::query()->with('item')->get()->keyBy(fn (PurchaseRequest $request) => $request->item?->sku);

        // Small purchase: manager only, then ordered and received in full.
        expect($requests['EYE-01']->status)->toBe(PurchaseRequestStatus::Received);
        // Over 5,000: manager and finance, then a partial delivery.
        expect($requests['SNS-200']->status)->toBe(PurchaseRequestStatus::Ordered)
            ->and($requests['SNS-200']->received_quantity)->toBe(24);
        expect($requests['BLT-L2']->status)->toBe(PurchaseRequestStatus::Rejected);
        // Raised by the low stock workflow, waiting on a manager.
        expect($requests['GLV-100']->requester_id)->toBeNull()
            ->and($requests['GLV-100']->status)->toBe(PurchaseRequestStatus::Submitted);
        // Approved by the manager, waiting on finance.
        expect($requests['HYD-46']->status)->toBe(PurchaseRequestStatus::Submitted);

        $waiting = Approval::query()->where('status', ApprovalStatus::Pending->value)->get();
        expect($waiting->where('approver_role.value', 'finance')->count())->toBeGreaterThanOrEqual(2)
            ->and($waiting->where('approver_role.value', 'manager')->count())->toBeGreaterThanOrEqual(2);

        expect(WorkflowRun::query()->where('status', WorkflowRunStatus::Completed->value)->count())->toBeGreaterThanOrEqual(4)
            ->and(WorkflowRun::query()->where('status', WorkflowRunStatus::Failed->value)->count())->toBe(0);

        expect(InventoryItem::query()->where('sku', 'GLV-100')->sole()->current_stock)->toBe(20);

        // History reads as a month of work, none of it in the future.
        expect(ActivityLog::query()->min('created_at'))->toBeLessThan(now()->subDays(25))
            ->and(ActivityLog::query()->max('created_at'))->toBeLessThanOrEqual(now());
    });

    $tom = User::query()->where('email', 'tom.becker@northstar.test')->sole();
    expect(Notification::query()->where('notifiable_id', $tom->id)->where('organization_id', $organization->id)->count())->toBeGreaterThan(0);

    $admin = User::query()->where('email', NorthstarDemoSeeder::PLATFORM_ADMIN_EMAIL)->sole();
    expect($admin->is_platform_admin)->toBeTrue()
        ->and(Hash::check('demo-password-123', $admin->password))->toBeTrue();
});

it('lets the demo people sign in and see their work', function () {
    ['organization' => $organization] = seedNorthstar();
    $priya = User::query()->where('email', 'priya.nair@northstar.test')->sole();

    actingAs($priya)->get(route('approvals.index', $organization))->assertOk();
    actingAs($priya)->get(route('overview', $organization))->assertOk();
});

it('does nothing the second time', function () {
    seedNorthstar();
    seed(NorthstarDemoSeeder::class);

    expect(Organization::query()->where('slug', NorthstarDemoSeeder::SLUG)->count())->toBe(1)
        ->and(User::query()->where('email', 'like', '%@northstar.test')->count())->toBe(7);
});
