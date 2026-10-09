<?php

use App\Actions\Approvals\DecideApproval;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Organization;
use App\Notifications\ApprovalDecidedNotification;
use App\Notifications\ApprovalRequiredNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

/**
 * @param  array<string, mixed>  $attributes
 */
function approvalIn(Organization $organization, array $attributes = []): Approval
{
    return inTenant($organization, fn () => Approval::factory()->create([
        'organization_id' => $organization->id,
        ...$attributes,
    ]));
}

describe('requesting', function () {
    it('raises a request and tells everyone in the approver role except the requester', function () {
        Notification::fake();
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $employee = memberIn($organization, Role::Employee);
        $finance = memberIn($organization, Role::Finance);
        $otherFinance = memberIn($organization, Role::Finance);

        $response = actingAs($employee)->post(route('approvals.store', $organization), [
            'title' => 'Safety boots for the night shift',
            'description' => 'Twelve pairs, sizes attached.',
            'approver_type' => 'role',
            'approver_role' => 'finance',
            'amount' => '1,240.50',
            'priority' => 'high',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $approval = inTenant($organization, fn () => Approval::query()->sole());
        $response->assertRedirect(route('approvals.show', [$organization, $approval]));

        expect($approval->reference())->toBe('A-1')
            ->and($approval->status)->toBe(ApprovalStatus::Pending)
            ->and($approval->amount)->toBe(124050)
            ->and($approval->currency)->toBe('USD')
            ->and($approval->approver_role)->toBe(Role::Finance)
            ->and($approval->requester_id)->toBe($employee->id);

        Notification::assertSentTo([$finance, $otherFinance], ApprovalRequiredNotification::class, fn ($notification) => $notification->amount === '$1,240.50');
        Notification::assertNotSentTo($employee, ApprovalRequiredNotification::class);
    });

    it('will not let people name themselves as the approver', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);

        actingAs($employee)
            ->post(route('approvals.store', $organization), [
                'title' => 'Mine',
                'approver_type' => 'member',
                'approver_id' => $employee->id,
                'priority' => 'medium',
                'amount' => '12.345',
            ])
            ->assertSessionHasErrors(['approver_id' => 'Someone else has to approve your request.', 'amount']);
    });

    it('keeps auditors from raising requests', function () {
        $organization = Organization::factory()->create();

        actingAs(memberIn($organization, Role::Auditor))
            ->post(route('approvals.store', $organization), ['title' => 'Nope', 'approver_type' => 'role', 'approver_role' => 'finance', 'priority' => 'low'])
            ->assertForbidden();
    });
});

describe('deciding', function () {
    it('approves, records who decided and tells the requester', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $requester = memberIn($organization, Role::Employee);
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => $requester->id]);

        actingAs($finance)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve', 'note' => 'Within budget.'])
            ->assertRedirect();

        $approval->refresh();

        expect($approval->status)->toBe(ApprovalStatus::Approved)
            ->and($approval->decided_by)->toBe($finance->id)
            ->and($approval->decision_note)->toBe('Within budget.')
            ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'approval.approved')->value('actor_id')))->toBe($finance->id);

        Notification::assertSentTo($requester, ApprovalDecidedNotification::class, fn ($notification) => $notification->body() === "{$finance->name} approved your request. “Within budget.”");
    });

    it('needs a reason to reject or ask for changes', function () {
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => memberIn($organization)->id]);

        actingAs($finance)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'reject'])
            ->assertSessionHasErrors(['note' => 'Add a note so the requester knows why.']);

        expect($approval->refresh()->status)->toBe(ApprovalStatus::Pending);
    });

    it('never lets requesters decide their own request, even owners', function () {
        $organization = Organization::factory()->create();
        $approval = approvalIn($organization, ['requester_id' => $organization->owner_id, 'approver_role' => Role::Owner]);

        actingAs($organization->owner)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve'])
            ->assertForbidden();
    });

    it('only lets the people it waits on decide, unless they may decide any request', function () {
        $organization = Organization::factory()->create();
        $named = memberIn($organization, Role::Manager);
        $otherManager = memberIn($organization, Role::Manager);
        $admin = memberIn($organization, Role::Admin);
        $approval = approvalIn($organization, ['requester_id' => memberIn($organization)->id, 'approver_role' => null, 'approver_id' => $named->id]);

        actingAs($otherManager)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve'])
            ->assertForbidden();

        actingAs($admin)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve'])
            ->assertRedirect();

        $log = inTenant($organization, fn () => ActivityLog::query()->where('action', 'approval.approved')->sole());

        expect($approval->refresh()->decided_by)->toBe($admin->id)
            ->and($log->properties['on_behalf'])->toBe($named->name);
    });

    it('keeps people without the right from deciding', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        $approval = approvalIn($organization, ['approver_role' => Role::Employee, 'requester_id' => memberIn($organization)->id]);

        actingAs($employee)
            ->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve'])
            ->assertForbidden();
    });

    it('decides only once, however many times the button is pressed', function () {
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => memberIn($organization)->id]);

        actingAs($finance)->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve']);

        // The second click arrives after the first was saved.
        $stale = $approval->replicate()->forceFill(['id' => $approval->id, 'status' => ApprovalStatus::Pending]);
        $stale->exists = true;

        expect(fn () => inTenant($organization, fn () => app(DecideApproval::class)->handle($stale, $finance, 'reject', 'Too late')))
            ->toThrow(ValidationException::class);

        expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved)
            ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'like', 'approval.%')->where('action', '!=', 'approval.requested')->count()))->toBe(1);
    });

    it('sends a request back for changes, then takes it again once resubmitted', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $requester = memberIn($organization, Role::Employee);
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => $requester->id]);

        actingAs($finance)->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'request_changes', 'note' => 'Attach the supplier quote.']);
        expect($approval->refresh()->status)->toBe(ApprovalStatus::ChangesRequested);

        actingAs($finance)
            ->post(route('approvals.resubmit', [$organization, $approval]), ['note' => 'Done'])
            ->assertForbidden();

        actingAs($requester)
            ->post(route('approvals.resubmit', [$organization, $approval]), ['note' => 'Quote attached.', 'amount' => '980'])
            ->assertRedirect();

        $approval->refresh();

        expect($approval->status)->toBe(ApprovalStatus::Pending)
            ->and($approval->decided_by)->toBeNull()
            ->and($approval->amount)->toBe(98000);

        Notification::assertSentToTimes($finance, ApprovalRequiredNotification::class, 1);
        Notification::assertSentTo($requester, ApprovalDecidedNotification::class);
    });

    it('lets requesters withdraw their own open request', function () {
        $organization = Organization::factory()->create();
        $requester = memberIn($organization, Role::Employee);
        $approval = approvalIn($organization, ['requester_id' => $requester->id]);

        actingAs(memberIn($organization, Role::Finance))
            ->post(route('approvals.withdraw', [$organization, $approval]))
            ->assertForbidden();

        actingAs($requester)->post(route('approvals.withdraw', [$organization, $approval]))->assertRedirect();

        expect($approval->refresh()->status)->toBe(ApprovalStatus::Cancelled);
    });
});

describe('overdue', function () {
    it('reminds approvers once, or expires requests set to reject when overdue', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $requester = memberIn($organization, Role::Employee);
        $remind = approvalIn($organization, ['requester_id' => $requester->id, 'due_at' => now()->addHour()]);
        $expire = approvalIn($organization, ['requester_id' => $requester->id, 'due_at' => now()->addHour(), 'when_overdue' => Approval::WHEN_OVERDUE_REJECT]);

        artisan('approvals:check-overdue');
        Notification::assertNothingSent();

        travel(2)->hours();
        artisan('approvals:check-overdue');
        artisan('approvals:check-overdue');

        expect($remind->refresh()->status)->toBe(ApprovalStatus::Pending)
            ->and($remind->reminded_at)->not->toBeNull()
            ->and($expire->refresh()->status)->toBe(ApprovalStatus::Expired);

        Notification::assertSentToTimes($finance, ApprovalRequiredNotification::class, 1);
        Notification::assertSentTo($requester, ApprovalDecidedNotification::class, fn ($notification) => $notification->status === 'expired');
    });
});

describe('pages', function () {
    it('lists what is waiting on the member, and what they asked for', function () {
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $requester = memberIn($organization, Role::Employee);
        approvalIn($organization, ['title' => 'For finance', 'requester_id' => $requester->id]);
        approvalIn($organization, ['title' => 'For managers', 'approver_role' => Role::Manager, 'requester_id' => $requester->id]);
        approvalIn($organization, ['title' => 'Asked by finance', 'requester_id' => $finance->id]);

        actingAs($finance)
            ->get(route('approvals.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('approvals/Index')
                ->where('filters.view', 'waiting')
                ->has('approvals.data', 1)
                ->where('approvals.data.0.title', 'For finance')
                ->where('counts.waiting', 1)
                ->where('counts.mine', 1)
                ->where('pendingApprovals', 1));

        actingAs($requester)
            ->get(route('approvals.index', [$organization, 'view' => 'mine']))
            ->assertInertia(fn (Assert $page) => $page->has('approvals.data', 2));
    });

    it('shows a request with what the viewer may do', function () {
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => memberIn($organization)->id, 'amount' => 500000, 'currency' => 'USD']);

        actingAs($finance)
            ->get(route('approvals.show', [$organization, $approval]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('approvals/Show')
                ->where('approval.reference', $approval->reference())
                ->where('approval.amount.formatted', '$5,000.00')
                ->where('can.approve', true)
                ->where('can.resubmit', false));
    });

    it('hides other organizations\' requests', function () {
        $organization = Organization::factory()->create();
        $theirs = approvalIn(Organization::factory()->create());

        actingAs($organization->owner)->get(route('approvals.show', [$organization, $theirs]))->assertNotFound();
        actingAs($organization->owner)->post(route('approvals.decide', [$organization, $theirs]), ['decision' => 'approve'])->assertNotFound();
        actingAs($organization->owner)->post(route('approvals.comments.store', [$organization, $theirs]), ['body' => 'Hi'])->assertNotFound();
    });

    it('takes comments from people who can see the request', function () {
        $organization = Organization::factory()->create();
        $finance = memberIn($organization, Role::Finance);
        $approval = approvalIn($organization, ['requester_id' => memberIn($organization)->id]);

        actingAs($finance)
            ->post(route('approvals.comments.store', [$organization, $approval]), ['body' => 'Which supplier is this from?'])
            ->assertRedirect();

        expect(inTenant($organization, fn () => $approval->comments()->value('body')))->toBe('Which supplier is this from?');
    });
});
