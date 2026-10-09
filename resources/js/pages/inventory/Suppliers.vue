<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Clock, Globe, Mail, Pencil, Phone, Plus, Truck } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InventoryNav from '@/components/inventory/InventoryNav.vue';
import SupplierDialog from '@/components/inventory/SupplierDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { plural } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as suppliersIndex } from '@/routes/inventory/suppliers';
import type { SupplierData } from '@/types/inventory';

defineProps<{
    suppliers: SupplierData[];
    can: { manage: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Inventory', href: itemsIndex() },
        { title: 'Suppliers', href: suppliersIndex() },
    ],
});

const open = ref(false);
const editing = ref<SupplierData | null>(null);

function edit(supplier: SupplierData | null) {
    editing.value = supplier;
    open.value = true;
}

/** "acme.example" rather than "https://acme.example/". */
function displayUrl(url: string): string {
    return url.replace(/^https?:\/\//, '').replace(/\/$/, '');
}

function itemsHref(supplier: SupplierData) {
    return itemsIndex({}, { query: { supplier: supplier.id } });
}
</script>

<template>
    <Head title="Suppliers" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Inventory"
            description="The suppliers you buy from. Items and purchase requests point at them."
        >
            <template #actions>
                <Button v-if="can.manage" @click="edit(null)">
                    <Plus />
                    New supplier
                </Button>
            </template>
        </PageHeader>

        <InventoryNav />

        <ul
            v-if="suppliers.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <li
                v-for="supplier in suppliers"
                :key="supplier.id"
                :class="
                    cn(
                        'flex flex-col gap-3 rounded-xl border bg-card p-5 shadow-xs',
                        !supplier.is_active && 'opacity-70',
                    )
                "
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">
                            {{ supplier.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ supplier.contact_name ?? 'No contact named' }}
                        </p>
                    </div>
                    <Button
                        v-if="can.manage"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Edit ${supplier.name}`"
                        @click="edit(supplier)"
                    >
                        <Pencil />
                    </Button>
                </div>
                <ul class="grid gap-1.5 text-sm">
                    <li
                        v-if="supplier.email"
                        class="flex min-w-0 items-center gap-2"
                    >
                        <Mail
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <a
                            :href="`mailto:${supplier.email}`"
                            class="truncate hover:underline"
                            >{{ supplier.email }}</a
                        >
                    </li>
                    <li v-if="supplier.phone" class="flex items-center gap-2">
                        <Phone
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <a
                            :href="`tel:${supplier.phone}`"
                            class="hover:underline"
                            >{{ supplier.phone }}</a
                        >
                    </li>
                    <li
                        v-if="supplier.website"
                        class="flex min-w-0 items-center gap-2"
                    >
                        <Globe
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <a
                            :href="supplier.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="truncate hover:underline"
                            >{{ displayUrl(supplier.website) }}</a
                        >
                    </li>
                    <li
                        v-if="supplier.lead_time_days !== null"
                        class="flex items-center gap-2"
                    >
                        <Clock
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Delivers in {{ plural(supplier.lead_time_days, 'day') }}
                    </li>
                </ul>
                <p
                    v-if="supplier.notes"
                    class="line-clamp-3 text-sm text-pretty text-muted-foreground"
                >
                    {{ supplier.notes }}
                </p>
                <div
                    class="mt-auto flex items-center justify-between gap-2 border-t pt-3 text-sm"
                >
                    <Link
                        :href="itemsHref(supplier)"
                        class="font-medium text-primary hover:underline"
                        >{{ plural(supplier.items_count, 'item') }}</Link
                    >
                    <span
                        v-if="!supplier.is_active"
                        class="rounded-full bg-neutral-soft px-2 py-0.5 text-xs font-medium text-neutral-text"
                        >No longer used</span
                    >
                </div>
            </li>
        </ul>

        <div v-else class="rounded-xl border bg-card shadow-xs">
            <EmptyState
                :icon="Truck"
                title="No suppliers yet"
                description="Add who you buy from, so items and purchase requests can name a supplier and its lead time."
            >
                <Button v-if="can.manage" @click="edit(null)"
                    ><Plus />New supplier</Button
                >
            </EmptyState>
        </div>

        <SupplierDialog
            v-if="can.manage"
            v-model:open="open"
            :supplier="editing"
        />
    </div>
</template>
