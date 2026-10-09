<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    Archive,
    Boxes,
    PackageX,
    Plus,
    Search,
    ShoppingCart,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InventoryNav from '@/components/inventory/InventoryNav.vue';
import ItemFormDialog from '@/components/inventory/ItemFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useFilters } from '@/composables/useFilters';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as itemsIndex, show } from '@/routes/inventory/items';
import { index as purchaseRequestsIndex } from '@/routes/purchase-requests';
import type { InventoryItemData, InventoryOptions } from '@/types/inventory';
import type { PaginatedResource } from '@/types/operations';

type StockFilter = 'low' | 'out' | 'archived';

const props = defineProps<{
    items: PaginatedResource<InventoryItemData>;
    filters: {
        q: string;
        stock: StockFilter | null;
        category: string | null;
        supplier: string | null;
    };
    stats: { items: number; low: number; out: number; value: string };
    options?: InventoryOptions;
    can: { manage: boolean; request: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Inventory', href: itemsIndex() }] });

const { filters, reset } = useFilters(
    {
        q: props.filters.q,
        stock: props.filters.stock,
        category: props.filters.category,
        supplier: props.filters.supplier,
    },
    { only: ['items', 'filters', 'stats'] },
);

const creating = ref(
    props.can.manage &&
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);

const stockModel = computed({
    get: () => filters.stock ?? 'all',
    set: (value: string) =>
        (filters.stock = value === 'all' ? null : (value as StockFilter)),
});

const categoryModel = computed({
    get: () => filters.category ?? 'any',
    set: (value: string) => (filters.category = value === 'any' ? null : value),
});

const supplierModel = computed({
    get: () => filters.supplier ?? 'any',
    set: (value: string) => (filters.supplier = value === 'any' ? null : value),
});

const hasFilters = computed(
    () =>
        !!filters.q ||
        !!filters.stock ||
        !!filters.category ||
        !!filters.supplier,
);

function toggleStock(value: StockFilter) {
    filters.stock = filters.stock === value ? null : value;
}

function clearFilters() {
    reset({ q: '', stock: null, category: null, supplier: null });
}

const requestHref = purchaseRequestsIndex({}, { query: { create: 1 } });
</script>

<template>
    <Head title="Inventory" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Inventory"
            description="What you keep in stock, where it is, and what is running low."
        >
            <template #actions>
                <Button v-if="can.request" variant="outline" as-child>
                    <Link :href="requestHref"
                        ><ShoppingCart />Request a purchase</Link
                    >
                </Button>
                <Button v-if="can.manage" @click="creating = true">
                    <Plus />
                    New item
                </Button>
            </template>
        </PageHeader>

        <InventoryNav />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                class="flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-xs"
            >
                <span
                    class="flex items-center gap-1.5 text-sm font-medium text-muted-foreground"
                >
                    <Boxes class="size-4" aria-hidden="true" />
                    Stocked items
                </span>
                <span class="font-display figures text-2xl font-semibold">{{
                    formatNumber(stats.items)
                }}</span>
            </div>
            <button
                type="button"
                :aria-pressed="filters.stock === 'low'"
                :class="
                    cn(
                        'flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition-[box-shadow] hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        stats.low
                            ? 'border-warning/30 bg-warning-soft/50 text-warning-text'
                            : 'bg-card',
                        filters.stock === 'low' && 'ring-2 ring-ring',
                    )
                "
                @click="toggleStock('low')"
            >
                <span class="flex items-center gap-1.5 text-sm font-medium">
                    <TriangleAlert class="size-4" aria-hidden="true" />
                    Running low
                </span>
                <span class="font-display figures text-2xl font-semibold">{{
                    formatNumber(stats.low)
                }}</span>
            </button>
            <button
                type="button"
                :aria-pressed="filters.stock === 'out'"
                :class="
                    cn(
                        'flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition-[box-shadow] hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        stats.out
                            ? 'border-danger/30 bg-danger-soft/50 text-danger-text'
                            : 'bg-card',
                        filters.stock === 'out' && 'ring-2 ring-ring',
                    )
                "
                @click="toggleStock('out')"
            >
                <span class="flex items-center gap-1.5 text-sm font-medium">
                    <PackageX class="size-4" aria-hidden="true" />
                    Out of stock
                </span>
                <span class="font-display figures text-2xl font-semibold">{{
                    formatNumber(stats.out)
                }}</span>
            </button>
            <div
                class="flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-xs"
            >
                <span
                    class="flex items-center gap-1.5 text-sm font-medium text-muted-foreground"
                >
                    <Wallet class="size-4" aria-hidden="true" />
                    Stock value
                </span>
                <span class="font-display figures text-2xl font-semibold">{{
                    stats.value
                }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by name or SKU"
                        aria-label="Search items"
                        class="pl-8"
                    />
                </div>
                <Select v-model="stockModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Stock level"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Any stock level</SelectItem>
                        <SelectItem value="low">Running low</SelectItem>
                        <SelectItem value="out">Out of stock</SelectItem>
                        <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                </Select>
                <template v-if="options">
                    <Select
                        v-if="options.categories.length"
                        v-model="categoryModel"
                    >
                        <SelectTrigger class="h-9 w-44" aria-label="Category"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">All categories</SelectItem>
                            <SelectItem
                                v-for="category in options.categories"
                                :key="category.id"
                                :value="category.id"
                                >{{ category.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Select
                        v-if="options.suppliers.length"
                        v-model="supplierModel"
                    >
                        <SelectTrigger class="h-9 w-44" aria-label="Supplier"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">All suppliers</SelectItem>
                            <SelectItem
                                v-for="supplier in options.suppliers"
                                :key="supplier.id"
                                :value="supplier.id"
                                >{{ supplier.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </template>
                <template v-else>
                    <Skeleton class="h-9 w-44" />
                    <Skeleton class="h-9 w-44" />
                </template>
            </div>

            <template v-if="items.data.length">
                <table class="hidden w-full text-sm md:table">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th
                                scope="col"
                                class="w-[34%] py-2.5 pr-3 pl-5 font-medium"
                            >
                                Item
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Stock
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2.5 text-right font-medium"
                            >
                                On hand
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2.5 text-right font-medium"
                            >
                                Reorder at
                            </th>
                            <th
                                scope="col"
                                class="w-[18%] px-3 py-2.5 font-medium"
                            >
                                Supplier
                            </th>
                            <th
                                scope="col"
                                class="py-2.5 pr-5 pl-3 text-right font-medium"
                            >
                                Value
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items.data"
                            :key="item.id"
                            class="group border-b last:border-0 hover:bg-accent/40"
                        >
                            <td class="max-w-0 py-3 pr-3 pl-5">
                                <Link
                                    :href="show({ item: item.id })"
                                    class="flex min-w-0 items-baseline gap-2"
                                >
                                    <span
                                        class="shrink-0 figures text-xs text-muted-foreground"
                                        >{{ item.sku }}</span
                                    >
                                    <span
                                        class="truncate font-medium group-hover:text-primary"
                                        >{{ item.name }}</span
                                    >
                                </Link>
                                <p
                                    v-if="
                                        item.category || item.default_location
                                    "
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        [
                                            item.category?.name,
                                            item.default_location?.name,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <EnumBadge :option="item.status" />
                            </td>
                            <td
                                class="px-3 py-3 text-right figures whitespace-nowrap"
                            >
                                {{ item.stock_label }}
                            </td>
                            <td
                                class="px-3 py-3 text-right figures text-muted-foreground"
                            >
                                {{ formatNumber(item.reorder_point) }}
                            </td>
                            <td
                                class="max-w-0 truncate px-3 py-3 text-muted-foreground"
                            >
                                {{ item.supplier?.name ?? '—' }}
                            </td>
                            <td class="py-3 pr-5 pl-3 text-right figures">
                                {{ item.stock_value ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y md:hidden">
                    <li v-for="item in items.data" :key="item.id">
                        <Link
                            :href="show({ item: item.id })"
                            class="grid gap-1.5 px-4 py-3 hover:bg-accent/40"
                        >
                            <span class="flex items-baseline gap-2">
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ item.sku }}</span
                                >
                                <span class="truncate font-medium">{{
                                    item.name
                                }}</span>
                            </span>
                            <span
                                class="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <EnumBadge :option="item.status" />
                                <span class="figures">{{
                                    item.stock_label
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >reorder at
                                    {{ formatNumber(item.reorder_point) }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
            </template>

            <EmptyState
                v-else-if="hasFilters"
                :icon="filters.stock === 'archived' ? Archive : Search"
                title="No items match"
                description="Try another search or clear the filters."
            >
                <Button variant="outline" @click="clearFilters"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="Boxes"
                title="Nothing in stock yet"
                description="Add the parts, materials and supplies you keep. FlowPilot tracks every movement and warns you before anything runs out."
            >
                <Button v-if="can.manage" @click="creating = true"
                    ><Plus />New item</Button
                >
            </EmptyState>

            <Pagination :paginator="items" noun="items" />
        </div>

        <ItemFormDialog
            v-if="can.manage && options"
            v-model:open="creating"
            :options="options"
        />
    </div>
</template>
