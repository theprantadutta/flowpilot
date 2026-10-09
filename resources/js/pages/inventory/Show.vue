<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ClipboardCheck,
    History,
    MapPin,
    PackageMinus,
    PackagePlus,
    Pencil,
    ShoppingCart,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import ItemFormDialog from '@/components/inventory/ItemFormDialog.vue';
import MovementDialog from '@/components/inventory/MovementDialog.vue';
import MovementList from '@/components/inventory/MovementList.vue';
import PurchaseRequestDialog from '@/components/inventory/PurchaseRequestDialog.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, formatNumber, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as itemsIndex, show } from '@/routes/inventory/items';
import { show as showPurchase } from '@/routes/purchase-requests';
import type {
    InventoryItemData,
    InventoryOptions,
    MovementData,
    MovementKind,
    PurchaseOptions,
    PurchaseRequestData,
} from '@/types/inventory';
import type { EnumOption, ResourceCollection } from '@/types/operations';

const props = defineProps<{
    item: InventoryItemData;
    movements?: ResourceCollection<MovementData>;
    purchaseRequests: ResourceCollection<PurchaseRequestData>;
    movementTypes: EnumOption[];
    options: InventoryOptions;
    can: { update: boolean; move: boolean; request: boolean };
}>();

const { organization } = useOrganization();

setLayoutProps({
    breadcrumbs: [
        { title: 'Inventory', href: itemsIndex() },
        { title: props.item.name, href: show({ item: props.item.id }) },
    ],
});

const editing = ref(false);
const requesting = ref(false);
const moving = ref(false);
const movementKind = ref<MovementKind>('receipt');

function startMovement(kind: MovementKind) {
    movementKind.value = kind;
    moving.value = true;
}

const hasStock = computed(() => (props.item.stock_levels ?? []).length > 0);

const actions = computed(() => [
    {
        kind: 'receipt' as const,
        label: 'Receive',
        icon: PackagePlus,
        disabled: !props.options.locations.length,
    },
    {
        kind: 'issue' as const,
        label: 'Issue',
        icon: PackageMinus,
        disabled: !hasStock.value,
    },
    {
        kind: 'transfer' as const,
        label: 'Move',
        icon: ArrowLeftRight,
        disabled: !hasStock.value || props.options.locations.length < 2,
    },
    {
        kind: 'adjustment' as const,
        label: 'Count',
        icon: ClipboardCheck,
        disabled: !props.options.locations.length,
    },
]);

/** How full the shelf is against the reorder point, for the gauge. */
const gauge = computed(() => {
    const ceiling = Math.max(
        props.item.reorder_point * 2,
        props.item.reorder_point + props.item.reorder_quantity,
        props.item.current_stock,
        1,
    );

    return {
        ceiling,
        stock: Math.min(100, (props.item.current_stock / ceiling) * 100),
        reorder: Math.min(100, (props.item.reorder_point / ceiling) * 100),
    };
});

const purchaseOptions = computed<PurchaseOptions>(() => ({
    items: [
        {
            id: props.item.id,
            label: `${props.item.sku} ${props.item.name}`,
            unit_cost: props.item.unit_cost?.input ?? null,
            supplier_id: props.item.supplier?.id ?? null,
            reorder_quantity: props.item.reorder_quantity,
        },
    ],
    suppliers: props.options.suppliers,
    locations: props.options.locations,
}));

const gaugeTone: Record<string, string> = {
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
};
</script>

<template>
    <Head :title="`${item.sku} ${item.name}`" />

    <div
        class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:py-8"
    >
        <main class="grid min-w-0 content-start gap-6">
            <header class="grid gap-3">
                <p class="figures text-sm text-muted-foreground">
                    {{ item.sku }}
                </p>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h1
                        class="text-2xl leading-tight font-semibold text-balance"
                    >
                        {{ item.name }}
                    </h1>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="can.request && item.is_active"
                            variant="outline"
                            @click="requesting = true"
                        >
                            <ShoppingCart />
                            Request purchase
                        </Button>
                        <Button
                            v-if="can.update"
                            variant="outline"
                            @click="editing = true"
                        >
                            <Pencil />
                            Edit item
                        </Button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <EnumBadge :option="item.status" />
                    <span
                        v-if="!item.is_active"
                        class="rounded-full bg-neutral-soft px-2 py-0.5 text-xs font-medium text-neutral-text"
                        >Archived</span
                    >
                </div>
                <p
                    v-if="item.description"
                    class="max-w-2xl text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ item.description }}
                </p>
            </header>

            <section
                aria-labelledby="stock-heading"
                class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs"
            >
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2
                            id="stock-heading"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            On hand
                        </h2>
                        <p class="font-display figures text-3xl font-semibold">
                            {{ item.stock_label }}
                        </p>
                    </div>
                    <div
                        v-if="can.move && item.is_active"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="action in actions"
                            :key="action.kind"
                            variant="outline"
                            size="sm"
                            :disabled="action.disabled"
                            @click="startMovement(action.kind)"
                        >
                            <component :is="action.icon" />
                            {{ action.label }}
                        </Button>
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <div
                        class="relative h-2.5 overflow-hidden rounded-full bg-secondary"
                        role="meter"
                        :aria-valuenow="item.current_stock"
                        aria-valuemin="0"
                        :aria-valuemax="gauge.ceiling"
                        :aria-valuetext="`${item.stock_label}, reorder at ${formatNumber(item.reorder_point)}`"
                        aria-label="Stock against reorder point"
                    >
                        <div
                            :class="
                                cn(
                                    'h-full rounded-full transition-[width]',
                                    gaugeTone[item.status.tone] ?? 'bg-primary',
                                )
                            "
                            :style="{ width: `${gauge.stock}%` }"
                        />
                        <div
                            v-if="item.reorder_point > 0"
                            class="absolute inset-y-0 w-0.5 bg-foreground/60"
                            :style="{ left: `${gauge.reorder}%` }"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Reorder at {{ formatNumber(item.reorder_point) }}, order
                        {{ formatNumber(item.reorder_quantity) }} at a time,
                        never below {{ formatNumber(item.minimum_stock) }}.
                    </p>
                </div>

                <div
                    v-if="item.stock_levels?.length"
                    class="grid gap-2 sm:grid-cols-2"
                >
                    <div
                        v-for="level in item.stock_levels"
                        :key="level.location.id"
                        class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <MapPin
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                level.location.name
                            }}</span>
                        </span>
                        <span class="figures font-medium whitespace-nowrap">{{
                            level.label
                        }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    None on any shelf.
                    {{
                        can.move && item.is_active
                            ? 'Receive stock to book where it is kept.'
                            : ''
                    }}
                </p>
                <p
                    v-if="can.move && !options.locations.length"
                    class="rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning-text"
                >
                    Add a location before booking stock movements.
                </p>
            </section>

            <section
                v-if="purchaseRequests.data.length"
                aria-labelledby="requests-heading"
                class="grid gap-3"
            >
                <h2 id="requests-heading" class="text-sm font-semibold">
                    On order
                </h2>
                <ul
                    class="divide-y overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <li
                        v-for="purchase in purchaseRequests.data"
                        :key="purchase.id"
                    >
                        <Link
                            :href="
                                showPurchase({ purchaseRequest: purchase.id })
                            "
                            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-accent/40"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ purchase.reference }}</span
                                >
                                <span class="truncate text-sm font-medium"
                                    >{{ formatNumber(purchase.quantity) }} for
                                    {{ purchase.total }}</span
                                >
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="text-xs text-muted-foreground"
                                    >{{ purchase.requester?.name }} ·
                                    {{ timeAgo(purchase.created_at) }}</span
                                >
                                <EnumBadge :option="purchase.status" />
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section aria-labelledby="movements-heading" class="grid gap-3">
                <h2 id="movements-heading" class="text-sm font-semibold">
                    Movements
                </h2>
                <div
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div v-if="movements === undefined" class="grid gap-3 p-4">
                        <Skeleton v-for="n in 4" :key="n" class="h-10 w-full" />
                    </div>
                    <MovementList
                        v-else-if="movements.data.length"
                        :movements="movements.data"
                    />
                    <EmptyState
                        v-else
                        compact
                        :icon="History"
                        title="No movements yet"
                        description="Every receipt, issue, move and count is recorded here, with who did it."
                    />
                </div>
            </section>
        </main>

        <aside
            class="grid h-fit gap-5 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6"
        >
            <dl class="grid gap-4 text-sm">
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Unit cost
                    </dt>
                    <dd class="figures">
                        {{ item.unit_cost?.formatted ?? 'Not set' }}
                        <span class="text-muted-foreground"
                            >per {{ item.unit.label.toLowerCase() }}</span
                        >
                    </dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Stock value
                    </dt>
                    <dd class="figures">
                        {{ item.stock_value ?? 'Set a unit cost to see it' }}
                    </dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Category
                    </dt>
                    <dd>{{ item.category?.name ?? 'None' }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Supplier
                    </dt>
                    <dd>{{ item.supplier?.name ?? 'None' }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Usually kept at
                    </dt>
                    <dd>
                        {{ item.default_location?.name ?? 'No usual location' }}
                    </dd>
                </div>
                <div v-if="item.low_stock_at" class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Ran low
                    </dt>
                    <dd
                        :title="
                            formatDateTime(
                                item.low_stock_at,
                                organization?.timezone,
                            )
                        "
                    >
                        {{ timeAgo(item.low_stock_at) }}
                    </dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Last movement
                    </dt>
                    <dd
                        :title="
                            item.last_movement_at
                                ? formatDateTime(
                                      item.last_movement_at,
                                      organization?.timezone,
                                  )
                                : undefined
                        "
                    >
                        {{
                            item.last_movement_at
                                ? timeAgo(item.last_movement_at)
                                : 'Never'
                        }}
                    </dd>
                </div>
            </dl>
        </aside>

        <ItemFormDialog
            v-if="can.update"
            v-model:open="editing"
            :options="options"
            :item="item"
        />
        <MovementDialog
            v-if="can.move"
            v-model:open="moving"
            :item="item"
            :kind="movementKind"
            :locations="options.locations"
        />
        <PurchaseRequestDialog
            v-if="can.request"
            v-model:open="requesting"
            :options="purchaseOptions"
            :item-id="item.id"
        />
    </div>
</template>
