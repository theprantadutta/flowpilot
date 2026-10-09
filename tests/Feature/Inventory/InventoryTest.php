<?php

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\WorkflowRun;
use App\Notifications\InventoryLowNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function locationIn(Organization $organization, string $name = 'Main warehouse'): InventoryLocation
{
    return inTenant($organization, fn () => InventoryLocation::factory()->create(['organization_id' => $organization->id, 'name' => $name]));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function itemIn(Organization $organization, array $attributes = []): InventoryItem
{
    return inTenant($organization, fn () => InventoryItem::factory()->create(['organization_id' => $organization->id, ...$attributes]));
}

/**
 * Record a movement through the HTTP endpoint as the given member.
 *
 * @param  array<string, mixed>  $data
 */
function move($member, Organization $organization, InventoryItem $item, string $type, array $data)
{
    return actingAs($member)->post(route('inventory.items.movements.store', [$organization, $item]), [
        'type' => $type,
        'request_key' => (string) Str::uuid(),
        ...$data,
    ]);
}

function stockOf(Organization $organization, InventoryItem $item): int
{
    return inTenant($organization, fn () => $item->refresh()->current_stock);
}

describe('items', function () {
    it('adds an item with its opening stock booked as a receipt', function () {
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $warehouse = locationIn($organization);
        $category = inTenant($organization, fn () => InventoryCategory::query()->create(['name' => 'Safety']));

        actingAs($organization->owner)
            ->post(route('inventory.items.store', $organization), [
                'sku' => 'glv-100',
                'name' => 'Nitrile gloves (box of 100)',
                'unit' => 'box',
                'category_id' => $category->id,
                'default_location_id' => $warehouse->id,
                'reorder_point' => 10,
                'reorder_quantity' => 40,
                'unit_cost' => '8.40',
                'opening_stock' => 25,
                'opening_location_id' => $warehouse->id,
            ])
            ->assertRedirect();

        $item = inTenant($organization, fn () => InventoryItem::query()->sole());
        $movement = inTenant($organization, fn () => InventoryMovement::query()->sole());

        expect($item->sku)->toBe('GLV-100')
            ->and($item->current_stock)->toBe(25)
            ->and($item->unit_cost_amount)->toBe(840)
            ->and($item->currency)->toBe('USD')
            ->and($movement->type)->toBe(MovementType::Receipt)
            ->and($movement->reference)->toBe('Opening stock')
            ->and($movement->stock_after)->toBe(25);
    });

    it('keeps SKUs unique within an organization only', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        itemIn($organization, ['sku' => 'BOLT-M10']);
        itemIn($other, ['sku' => 'BOLT-M10']);

        actingAs($organization->owner)
            ->post(route('inventory.items.store', $organization), ['sku' => 'bolt-m10', 'name' => 'Duplicate', 'unit' => 'each'])
            ->assertSessionHasErrors(['sku' => 'Another item already uses this SKU.']);
    });

    it('rejects categories and locations from another organization', function () {
        $organization = Organization::factory()->create();
        $foreignLocation = locationIn(Organization::factory()->create());

        actingAs($organization->owner)
            ->post(route('inventory.items.store', $organization), [
                'sku' => 'X-1', 'name' => 'Widget', 'unit' => 'each',
                'default_location_id' => $foreignLocation->id,
            ])
            ->assertSessionHasErrors(['default_location_id' => 'Choose a location from this organization.']);
    });

    it('lists items with what needs attention first', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $plenty = itemIn($organization, ['name' => 'Plenty', 'reorder_point' => 5]);
        $low = itemIn($organization, ['name' => 'Running low', 'reorder_point' => 10]);
        itemIn($organization, ['name' => 'Empty']);
        move($organization->owner, $organization, $plenty, 'receipt', ['quantity' => 50, 'location_id' => $warehouse->id]);
        move($organization->owner, $organization, $low, 'receipt', ['quantity' => 4, 'location_id' => $warehouse->id]);

        actingAs($organization->owner)
            ->get(route('inventory.items.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Index')
                ->where('items.data.0.name', 'Empty')
                ->where('items.data.1.name', 'Running low')
                ->where('items.data.1.status.value', 'low')
                ->where('stats.low', 1)
                ->where('stats.out', 1));
    });
});

describe('movements', function () {
    it('receives, issues, moves and counts stock per location', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $line = locationIn($organization, 'Line 2 store');
        $item = itemIn($organization, ['reorder_point' => 0]);

        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 30, 'location_id' => $warehouse->id])->assertRedirect();
        move($organization->owner, $organization, $item, 'transfer', ['quantity' => 12, 'from_location_id' => $warehouse->id, 'to_location_id' => $line->id]);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 5, 'location_id' => $line->id]);
        move($organization->owner, $organization, $item, 'adjustment', ['counted' => 16, 'location_id' => $warehouse->id, 'notes' => 'Found a damaged box']);

        $levels = inTenant($organization, fn () => $item->stockLevels()->pluck('quantity', 'inventory_location_id')->all());

        expect(stockOf($organization, $item))->toBe(23)
            ->and($levels)->toBe([$warehouse->id => 16, $line->id => 7])
            ->and(inTenant($organization, fn () => InventoryMovement::query()->orderBy('number')->pluck('stock_after')->all()))->toBe([30, 30, 25, 23]);
    });

    it('refuses to take more than a location holds', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $line = locationIn($organization, 'Line 2 store');
        $item = itemIn($organization, ['unit' => 'box']);
        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 3, 'location_id' => $warehouse->id]);

        move($organization->owner, $organization, $item, 'issue', ['quantity' => 4, 'location_id' => $warehouse->id])
            ->assertSessionHasErrors(['quantity' => 'Only 3 boxes at this location.']);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 1, 'location_id' => $line->id])
            ->assertSessionHasErrors(['quantity' => 'Only 0 boxes at this location.']);
        move($organization->owner, $organization, $item, 'transfer', ['quantity' => 1, 'from_location_id' => $warehouse->id, 'to_location_id' => $warehouse->id])
            ->assertSessionHasErrors(['to_location_id']);
        move($organization->owner, $organization, $item, 'adjustment', ['counted' => 3, 'location_id' => $warehouse->id, 'notes' => 'Recount'])
            ->assertSessionHasErrors(['counted' => 'That matches what is recorded, so there is nothing to change.']);
        move($organization->owner, $organization, $item, 'adjustment', ['counted' => 2, 'location_id' => $warehouse->id])
            ->assertSessionHasErrors(['notes']);

        expect(stockOf($organization, $item))->toBe(3);
    });

    it('records the same submission once', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $item = itemIn($organization);
        $key = (string) Str::uuid();

        foreach ([1, 2] as $attempt) {
            actingAs($organization->owner)->post(route('inventory.items.movements.store', [$organization, $item]), [
                'type' => 'receipt', 'quantity' => 10, 'location_id' => $warehouse->id, 'request_key' => $key,
            ]);
        }

        expect(stockOf($organization, $item))->toBe(10)
            ->and(inTenant($organization, fn () => InventoryMovement::query()->count()))->toBe(1);
    });

    it('never changes a movement after it is recorded', function () {
        $organization = Organization::factory()->create();
        $item = itemIn($organization);
        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 5, 'location_id' => locationIn($organization)->id]);

        inTenant($organization, function () {
            $movement = InventoryMovement::query()->sole();

            expect(fn () => $movement->forceFill(['notes' => 'Edited'])->save())->toThrow(LogicException::class);
        });
    });

    it('shows an item\'s movements and the ledger with who moved what', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $item = itemIn($organization, ['sku' => 'GLV-100', 'unit' => 'box', 'reorder_point' => 0]);
        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 12, 'location_id' => $warehouse->id, 'reference' => 'DN-77']);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 2, 'location_id' => $warehouse->id]);

        actingAs($organization->owner)
            ->get(route('inventory.items.show', [$organization, $item]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Show')
                ->where('item.stock_label', '10 boxes')
                ->where('item.stock_levels.0.location.name', 'Main warehouse')
                ->missing('movements')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('movements.data', 2)
                    ->where('movements.data.0.change_label', '−2 boxes')
                    ->where('movements.data.0.item.sku', 'GLV-100')
                    ->where('movements.data.1.reference_note', 'DN-77')
                    ->where('movements.data.1.performer.name', $organization->owner->name)));

        actingAs($organization->owner)
            ->get(route('inventory.movements.index', [$organization, 'type' => 'receipt']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Movements')
                ->has('movements.data', 1)
                ->where('movements.data.0.to', 'Main warehouse')
                ->where('movements.data.0.stock_after_label', '12 boxes'));
    });

    it('lets members who can only look see stock but not change it', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        $item = itemIn($organization);

        actingAs($employee)->get(route('inventory.items.show', [$organization, $item]))->assertOk();
        move($employee, $organization, $item, 'receipt', ['quantity' => 5, 'location_id' => locationIn($organization)->id])->assertForbidden();
        actingAs($employee)->post(route('inventory.items.store', $organization), ['sku' => 'A', 'name' => 'Nope', 'unit' => 'each'])->assertForbidden();
    });

    it('hides other organizations\' stock', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $theirs = itemIn($other);

        actingAs($organization->owner)->get(route('inventory.items.show', [$organization, $theirs]))->assertNotFound();
        move($organization->owner, $organization, $theirs, 'receipt', ['quantity' => 5, 'location_id' => locationIn($organization)->id])->assertNotFound();
    });
});

describe('low stock', function () {
    it('tells inventory managers once when an item drops to its reorder point', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $procurement = memberIn($organization, Role::Procurement);
        $employee = memberIn($organization, Role::Employee);
        $warehouse = locationIn($organization);
        $item = itemIn($organization, ['reorder_point' => 10]);

        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 15, 'location_id' => $warehouse->id]);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 6, 'location_id' => $warehouse->id]);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 2, 'location_id' => $warehouse->id]);

        Notification::assertSentToTimes($procurement, InventoryLowNotification::class, 1);
        Notification::assertNotSentTo($employee, InventoryLowNotification::class);

        // Restocking above the reorder point means the next drop is news again.
        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 20, 'location_id' => $warehouse->id]);
        expect(inTenant($organization, fn () => $item->refresh()->low_stock_at))->toBeNull();

        move($organization->owner, $organization, $item, 'issue', ['quantity' => 20, 'location_id' => $warehouse->id]);
        Notification::assertSentToTimes($procurement, InventoryLowNotification::class, 2);
    });

    it('starts workflows listening for low stock', function () {
        $organization = Organization::factory()->create();
        $warehouse = locationIn($organization);
        $item = itemIn($organization, ['reorder_point' => 5, 'name' => 'Pallet wrap']);
        publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end', ['summary' => '{{ subject.name }} at {{ subject.current_stock }}'])], [path('trigger', 'done')], trigger: 'inventory.low_stock');

        move($organization->owner, $organization, $item, 'receipt', ['quantity' => 8, 'location_id' => $warehouse->id]);
        move($organization->owner, $organization, $item, 'issue', ['quantity' => 4, 'location_id' => $warehouse->id]);

        $run = inTenant($organization, fn () => WorkflowRun::query()->sole());

        expect($run->subject_type)->toBe('inventory_item')
            ->and($run->context['steps']['done']['summary'])->toBe('Pallet wrap at 4');
    });
});
