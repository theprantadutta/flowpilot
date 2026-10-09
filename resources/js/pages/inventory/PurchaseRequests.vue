<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Search, ShoppingCart } from '@lucide/vue';
import { computed, ref } from 'vue';
import DueDate from '@/components/DueDate.vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InventoryNav from '@/components/inventory/InventoryNav.vue';
import PurchaseRequestDialog from '@/components/inventory/PurchaseRequestDialog.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
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
import { useFilters } from '@/composables/useFilters';
import { useOrganization } from '@/composables/useOrganization';
import { formatNumber, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as itemsIndex } from '@/routes/inventory/items';
import {
    index as purchaseRequestsIndex,
    show,
} from '@/routes/purchase-requests';
import type { PurchaseOptions, PurchaseRequestData } from '@/types/inventory';
import type { EnumOption, PaginatedResource } from '@/types/operations';

const props = defineProps<{
    requests: PaginatedResource<PurchaseRequestData>;
    filters: { view: 'all' | 'mine'; status: string | null; q: string };
    statuses: EnumOption[];
    options?: PurchaseOptions;
    can: { create: boolean; seeAll: boolean };
}>();

const { can: hasPermission } = useOrganization();

setLayoutProps({
    breadcrumbs: hasPermission('inventory.view')
        ? [
              { title: 'Inventory', href: itemsIndex() },
              { title: 'Purchase requests', href: purchaseRequestsIndex() },
          ]
        : [{ title: 'Purchase requests', href: purchaseRequestsIndex() }],
});

const { filters, reset } = useFilters(
    {
        view: props.filters.view,
        status: props.filters.status,
        q: props.filters.q,
    },
    { only: ['requests', 'filters'] },
);

const statusModel = computed({
    get: () => filters.status ?? 'any',
    set: (value: string) => (filters.status = value === 'any' ? null : value),
});

const creating = ref(
    props.can.create &&
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);

const hasFilters = computed(() => !!filters.q || !!filters.status);

/** Compared as local calendar dates, like the date the requester picked. */
function isLate(purchase: PurchaseRequestData): boolean {
    const today = new Date();
    const local = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

    return (
        purchase.is_open && !!purchase.needed_by && purchase.needed_by < local
    );
}
</script>

<template>
    <Head title="Purchase requests" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            :title="
                hasPermission('inventory.view')
                    ? 'Inventory'
                    : 'Purchase requests'
            "
            description="Requests to buy stock or one-off items, from submitted through ordered to received."
        >
            <template #actions>
                <Button v-if="can.create" @click="creating = true">
                    <Plus />
                    Request a purchase
                </Button>
            </template>
        </PageHeader>

        <InventoryNav v-if="hasPermission('inventory.view')" />

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                v-if="can.seeAll"
                class="flex flex-wrap items-center gap-x-1 border-b px-3 pt-2"
                role="tablist"
                aria-label="Whose requests"
            >
                <button
                    v-for="tab in [
                        { value: 'all' as const, label: 'All requests' },
                        { value: 'mine' as const, label: 'My requests' },
                    ]"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.view === tab.value"
                    :class="
                        cn(
                            '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            filters.view === tab.value
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="filters.view = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by item or PR-number"
                        aria-label="Search purchase requests"
                        class="pl-8"
                    />
                </div>
                <Select v-model="statusModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any status</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                            >{{ status.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <ul v-if="requests.data.length" class="divide-y">
                <li v-for="purchase in requests.data" :key="purchase.id">
                    <Link
                        :href="show({ purchaseRequest: purchase.id })"
                        class="grid gap-3 px-4 py-3.5 transition-colors hover:bg-secondary/40 focus-visible:bg-secondary/40 focus-visible:outline-none sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ purchase.reference }}</span
                                >
                                <p class="truncate font-medium">
                                    {{ purchase.item_name }}
                                </p>
                                <span
                                    class="figures text-sm text-muted-foreground"
                                    >×
                                    {{ formatNumber(purchase.quantity) }}</span
                                >
                            </div>
                            <p
                                class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="purchase.requester"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    <MemberAvatar
                                        :name="purchase.requester.name"
                                        :avatar="purchase.requester.avatar"
                                        class="size-4 text-[0.5rem]"
                                    />
                                    {{ purchase.requester.name }}
                                </span>
                                <span v-else>Raised by a workflow</span>
                                <template v-if="purchase.supplier">
                                    <span aria-hidden="true">·</span>
                                    <span>{{ purchase.supplier.name }}</span>
                                </template>
                                <span aria-hidden="true">·</span>
                                <span>{{ timeAgo(purchase.created_at) }}</span>
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-3 sm:justify-end"
                        >
                            <span
                                v-if="purchase.is_open && purchase.needed_by"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                Needed
                                <DueDate
                                    :date="purchase.needed_by"
                                    :overdue="isLate(purchase)"
                                    bare
                                />
                            </span>
                            <span
                                class="font-display figures text-base font-semibold"
                                >{{ purchase.total }}</span
                            >
                            <EnumBadge :option="purchase.status" />
                        </div>
                    </Link>
                </li>
            </ul>

            <EmptyState
                v-else-if="hasFilters"
                :icon="Search"
                title="No requests match"
                description="Try another search or status."
            >
                <Button
                    variant="outline"
                    @click="reset({ q: '', status: null })"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="ShoppingCart"
                :title="
                    filters.view === 'mine'
                        ? 'You have not requested anything yet'
                        : 'No purchase requests yet'
                "
                description="Request stock when it runs low or ask for a one-off purchase. It goes for approval, then procurement orders and receives it."
            >
                <Button v-if="can.create" @click="creating = true"
                    ><Plus />Request a purchase</Button
                >
            </EmptyState>

            <Pagination :paginator="requests" noun="requests" />
        </div>

        <PurchaseRequestDialog
            v-if="can.create && options"
            v-model:open="creating"
            :options="options"
        />
    </div>
</template>
