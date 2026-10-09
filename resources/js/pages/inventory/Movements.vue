<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InventoryNav from '@/components/inventory/InventoryNav.vue';
import MovementList from '@/components/inventory/MovementList.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFilters } from '@/composables/useFilters';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as movementsIndex } from '@/routes/inventory/movements';
import type { MovementData, NamedRef } from '@/types/inventory';
import type { EnumOption, PaginatedResource } from '@/types/operations';

const props = defineProps<{
    movements: PaginatedResource<MovementData>;
    filters: { type: string | null; location: string | null };
    types: EnumOption[];
    locations?: NamedRef[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Inventory', href: itemsIndex() },
        { title: 'Movements', href: movementsIndex() },
    ],
});

const { filters, reset } = useFilters(
    { type: props.filters.type, location: props.filters.location },
    { only: ['movements', 'filters'] },
);

const typeModel = computed({
    get: () => filters.type ?? 'any',
    set: (value: string) => (filters.type = value === 'any' ? null : value),
});

const locationModel = computed({
    get: () => filters.location ?? 'any',
    set: (value: string) => (filters.location = value === 'any' ? null : value),
});
</script>

<template>
    <Head title="Stock movements" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Inventory"
            description="Every receipt, issue, move and count, newest first. Movements cannot be edited, so this is the audit trail."
        />

        <InventoryNav />

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <Select v-model="typeModel">
                    <SelectTrigger class="h-9 w-44" aria-label="Movement type"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">All movements</SelectItem>
                        <SelectItem
                            v-for="type in types"
                            :key="type.value"
                            :value="type.value"
                            >{{ type.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="locationModel">
                    <SelectTrigger class="h-9 w-48" aria-label="Location"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">All locations</SelectItem>
                        <SelectItem
                            v-for="location in locations ?? []"
                            :key="location.id"
                            :value="location.id"
                            >{{ location.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <MovementList
                v-if="movements.data.length"
                :movements="movements.data"
                show-item
            />
            <EmptyState
                v-else-if="filters.type || filters.location"
                :icon="History"
                title="No movements match"
                description="Try another type or location."
            >
                <Button
                    variant="outline"
                    @click="reset({ type: null, location: null })"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="History"
                title="No stock has moved yet"
                description="Receipts, issues, moves and counts you book on an item appear here."
            />

            <Pagination :paginator="movements" noun="movements" />
        </div>
    </div>
</template>
