<?php

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\PublishWorkflow;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Workflow;
use App\Notifications\PurchaseRequestUpdatedNotification;
use App\Notifications\WorkflowMessageNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Create and publish a workflow from one of the built-in templates.
 */
function installTemplate(Organization $organization, string $template): Workflow
{
    return inTenant($organization, function () use ($organization, $template) {
        $workflow = app(CreateWorkflow::class)->handle($organization->owner, 'From template', null, 'manual', $template);
        app(PublishWorkflow::class)->handle($workflow, $organization->owner);

        return $workflow->refresh();
    });
}

/**
 * @param  array<string, mixed>  $data
 */
function submitPurchase($member, Organization $organization, array $data)
{
    return actingAs($member)->post(route('purchase-requests.store', $organization), [
        'request_key' => (string) Str::uuid(),
        ...$data,
    ]);
}

function stockedItem(Organization $organization): array
{
    return inTenant($organization, function () use ($organization) {
        $location = InventoryLocation::factory()->create(['organization_id' => $organization->id, 'name' => 'Goods in']);
        $item = InventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Hydraulic press seals',
            'default_location_id' => $location->id,
            'unit_cost_amount' => 4200,
            'currency' => 'USD',
        ]);

        return [$item, $location];
    });
}

describe('submitting', function () {
    it('raises a request once per submission and works out the total', function () {
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $employee = memberIn($organization, Role::Employee);
        [$item] = stockedItem($organization);
        $key = (string) Str::uuid();

        foreach ([1, 2] as $attempt) {
            actingAs($employee)->post(route('purchase-requests.store', $organization), [
                'inventory_item_id' => $item->id,
                'quantity' => 12,
                'unit_cost' => '42.00',
                'reason' => 'Press 3 is leaking',
                'request_key' => $key,
            ]);
        }

        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());

        expect($request->reference())->toBe('PR-1')
            ->and($request->item_name)->toBe('Hydraulic press seals')
            ->and($request->total_amount)->toBe(50400)
            ->and($request->deliver_to_location_id)->toBe($item->default_location_id)
            ->and($request->status)->toBe(PurchaseRequestStatus::Submitted);
    });

    it('tells inventory managers when no approval workflow is switched on', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        $procurement = memberIn($organization, Role::Procurement);

        submitPurchase($employee, $organization, ['item_name' => 'Label printer ribbons', 'quantity' => 4, 'unit_cost' => '15']);

        Notification::assertSentTo($procurement, PurchaseRequestUpdatedNotification::class);
    });

    it('needs an item or a description, and a cost', function () {
        $organization = Organization::factory()->create();

        submitPurchase($organization->owner, $organization, ['quantity' => 0])
            ->assertSessionHasErrors(['item_name' => 'Choose a stocked item or describe what you need.', 'quantity', 'unit_cost' => 'Enter what one costs.']);
    });
});

describe('approval workflow', function () {
    it('runs a purchase request through manager approval, procurement and receipt into stock', function () {
        Notification::fake();
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $employee = memberIn($organization, Role::Employee);
        $manager = memberIn($organization, Role::Manager);
        $procurement = memberIn($organization, Role::Procurement);
        [$item, $location] = stockedItem($organization);
        installTemplate($organization, 'purchase-approval');

        submitPurchase($employee, $organization, ['inventory_item_id' => $item->id, 'quantity' => 10, 'unit_cost' => '42.00']);

        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());
        $approval = inTenant($organization, fn () => Approval::query()->sole());

        expect($approval->title)->toBe('PR-1: 10 × Hydraulic press seals')
            ->and($approval->amount)->toBe(42000)
            ->and($approval->requester_id)->toBe($employee->id)
            ->and($approval->subject_type)->toBe('purchase_request');

        actingAs($manager)->post(route('approvals.decide', [$organization, $approval]), ['decision' => 'approve']);

        expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Approved);
        Notification::assertSentTo($procurement, WorkflowMessageNotification::class, fn ($notification) => str_starts_with($notification->messageTitle, 'Order PR-1'));
        Notification::assertSentTo($employee, PurchaseRequestUpdatedNotification::class, fn ($notification) => $notification->status === 'approved');

        actingAs($procurement)
            ->patch(route('purchase-requests.status.update', [$organization, $request]), ['status' => 'ordered', 'supplier_reference' => 'SO-5521'])
            ->assertRedirect();

        actingAs($procurement)->post(route('purchase-requests.receipts.store', [$organization, $request]), ['quantity' => 4, 'location_id' => $location->id, 'request_key' => (string) Str::uuid()]);
        expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Ordered)
            ->and(inTenant($organization, fn () => $item->refresh()->current_stock))->toBe(4);

        actingAs($procurement)->post(route('purchase-requests.receipts.store', [$organization, $request]), ['quantity' => 6, 'location_id' => $location->id, 'request_key' => (string) Str::uuid()]);

        $request->refresh();

        expect($request->status)->toBe(PurchaseRequestStatus::Received)
            ->and($request->received_quantity)->toBe(10)
            ->and($request->supplier_reference)->toBe('SO-5521')
            ->and(inTenant($organization, fn () => $item->refresh()->current_stock))->toBe(10)
            ->and(inTenant($organization, fn () => $request->movements()->count()))->toBe(2);

        actingAs($employee)
            ->get(route('purchase-requests.show', [$organization, $request]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/PurchaseRequestShow')
                ->where('purchaseRequest.status.value', 'received')
                ->has('approvals.data', 1)
                ->has('receipts.data', 2)
                ->where('receipts.data.0.item.id', $item->id)
                ->where('receipts.data.0.to', $location->name));
    });

    it('also asks finance for anything over 5,000, and records a rejection', function () {
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $employee = memberIn($organization, Role::Employee);
        $manager = memberIn($organization, Role::Manager);
        $finance = memberIn($organization, Role::Finance);
        installTemplate($organization, 'purchase-approval');

        submitPurchase($employee, $organization, ['item_name' => 'Spare motor', 'quantity' => 2, 'unit_cost' => '3100']);

        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());
        actingAs($manager)->post(route('approvals.decide', [$organization, inTenant($organization, fn () => Approval::query()->sole())]), ['decision' => 'approve']);

        $financeApproval = inTenant($organization, fn () => Approval::query()->where('approver_role', 'finance')->sole());
        expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Submitted);

        actingAs($finance)->post(route('approvals.decide', [$organization, $financeApproval]), ['decision' => 'reject', 'note' => 'Repair the old one']);

        expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Rejected)
            ->and($financeApproval->refresh()->status)->toBe(ApprovalStatus::Rejected);
    });

    it('reorders automatically when stock runs low, and sends the request for approval', function () {
        $organization = Organization::factory()->create(['currency' => 'USD']);
        memberIn($organization, Role::Manager);
        [$item, $location] = stockedItem($organization);
        inTenant($organization, fn () => $item->forceFill(['reorder_point' => 5, 'reorder_quantity' => 30])->save());
        installTemplate($organization, 'low-stock-reorder');
        installTemplate($organization, 'purchase-approval');

        actingAs($organization->owner)->post(route('inventory.items.movements.store', [$organization, $item]), ['type' => 'receipt', 'quantity' => 8, 'location_id' => $location->id, 'request_key' => (string) Str::uuid()]);
        actingAs($organization->owner)->post(route('inventory.items.movements.store', [$organization, $item]), ['type' => 'issue', 'quantity' => 4, 'location_id' => $location->id, 'request_key' => (string) Str::uuid()]);

        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());

        expect($request->quantity)->toBe(30)
            ->and($request->requester_id)->toBeNull()
            ->and($request->inventory_item_id)->toBe($item->id)
            ->and(inTenant($organization, fn () => Approval::query()->sole()->subject_id))->toBe($request->id);
    });
});

describe('deciding by hand', function () {
    it('lets inventory managers decide when no workflow does, but never their own', function () {
        $organization = Organization::factory()->create();
        $procurement = memberIn($organization, Role::Procurement);
        $employee = memberIn($organization, Role::Employee);

        submitPurchase($employee, $organization, ['item_name' => 'Ear defenders', 'quantity' => 10, 'unit_cost' => '6']);
        submitPurchase($procurement, $organization, ['item_name' => 'Cable ties', 'quantity' => 5, 'unit_cost' => '2']);

        [$theirs, $own] = inTenant($organization, fn () => PurchaseRequest::query()->orderBy('number')->get()->all());

        actingAs($procurement)->patch(route('purchase-requests.status.update', [$organization, $own]), ['status' => 'approved'])->assertForbidden();
        actingAs($employee)->patch(route('purchase-requests.status.update', [$organization, $theirs]), ['status' => 'approved'])->assertForbidden();
        actingAs($procurement)->patch(route('purchase-requests.status.update', [$organization, $theirs]), ['status' => 'approved'])->assertRedirect();

        expect($theirs->refresh()->status)->toBe(PurchaseRequestStatus::Approved)
            ->and($theirs->decided_by)->toBe($procurement->id);
    });

    it('leaves decisions to the workflow when one is switched on', function () {
        $organization = Organization::factory()->create();
        $procurement = memberIn($organization, Role::Procurement);
        installTemplate($organization, 'purchase-approval');

        submitPurchase(memberIn($organization), $organization, ['item_name' => 'Ear defenders', 'quantity' => 10, 'unit_cost' => '6']);
        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());

        actingAs($procurement)->patch(route('purchase-requests.status.update', [$organization, $request]), ['status' => 'approved'])->assertForbidden();
    });

    it('follows the order of things', function () {
        $organization = Organization::factory()->create();
        $request = inTenant($organization, fn () => PurchaseRequest::factory()->create(['organization_id' => $organization->id]));

        actingAs($organization->owner)->patch(route('purchase-requests.status.update', [$organization, $request]), ['status' => 'ordered'])->assertForbidden();
        actingAs($organization->owner)->post(route('purchase-requests.receipts.store', [$organization, $request]), ['quantity' => 1, 'request_key' => (string) Str::uuid()])->assertForbidden();
    });
});

describe('pages', function () {
    it('shows a request with its approvals and runs', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        submitPurchase($employee, $organization, ['item_name' => 'Ear defenders', 'quantity' => 10, 'unit_cost' => '6']);
        $request = inTenant($organization, fn () => PurchaseRequest::query()->sole());

        actingAs($employee)
            ->get(route('purchase-requests.show', [$organization, $request]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/PurchaseRequestShow')
                ->where('purchaseRequest.reference', 'PR-1')
                ->where('hasApprovalWorkflow', false)
                ->where('can.cancel', true)
                ->where('can.decide', false));
    });

    it('hides other organizations\' requests', function () {
        $organization = Organization::factory()->create();
        $theirs = inTenant($other = Organization::factory()->create(), fn () => PurchaseRequest::factory()->create(['organization_id' => $other->id]));

        actingAs($organization->owner)->get(route('purchase-requests.show', [$organization, $theirs]))->assertNotFound();
        actingAs($organization->owner)->patch(route('purchase-requests.status.update', [$organization, $theirs]), ['status' => 'cancelled'])->assertNotFound();
    });
});
