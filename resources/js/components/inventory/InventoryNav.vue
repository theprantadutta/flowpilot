<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useOrganization } from '@/composables/useOrganization';
import { cn } from '@/lib/utils';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as locationsIndex } from '@/routes/inventory/locations';
import { index as movementsIndex } from '@/routes/inventory/movements';
import { index as suppliersIndex } from '@/routes/inventory/suppliers';
import { index as purchaseRequestsIndex } from '@/routes/purchase-requests';

/**
 * Tabs across the inventory pages.
 */
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { can } = useOrganization();

const tabs = computed(() =>
    [
        {
            label: 'Items',
            href: itemsIndex(),
            exact: true,
            show: can('inventory.view'),
        },
        {
            label: 'Movements',
            href: movementsIndex(),
            exact: false,
            show: can('inventory.view'),
        },
        {
            label: 'Purchase requests',
            href: purchaseRequestsIndex(),
            exact: false,
            show: can('inventory.view') || can('inventory.request'),
        },
        {
            label: 'Suppliers',
            href: suppliersIndex(),
            exact: false,
            show: can('inventory.view'),
        },
        {
            label: 'Locations',
            href: locationsIndex(),
            exact: false,
            show: can('inventory.view'),
        },
    ].filter((tab) => tab.show),
);

function active(tab: {
    href: NonNullable<InertiaLinkProps['href']>;
    exact: boolean;
}): boolean {
    return tab.exact ? isCurrentUrl(tab.href) : isCurrentOrParentUrl(tab.href);
}
</script>

<template>
    <nav
        aria-label="Inventory"
        class="-mb-px flex scrollbar-thin gap-1 overflow-x-auto border-b"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="tab.href"
            :aria-current="active(tab) ? 'page' : undefined"
            :class="
                cn(
                    'border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    active(tab)
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                )
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
