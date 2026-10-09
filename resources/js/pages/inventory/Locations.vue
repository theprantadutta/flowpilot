<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Tag, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormField from '@/components/FormField.vue';
import InventoryNav from '@/components/inventory/InventoryNav.vue';
import LocationDialog from '@/components/inventory/LocationDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber, plural } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    destroy as destroyCategory,
    store as storeCategory,
    update as updateCategory,
} from '@/routes/inventory/categories';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as locationsIndex } from '@/routes/inventory/locations';
import type { CategoryData, LocationData } from '@/types/inventory';

defineProps<{
    locations: LocationData[];
    categories: CategoryData[];
    can: { manage: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Inventory', href: itemsIndex() },
        { title: 'Locations', href: locationsIndex() },
    ],
});

const locationOpen = ref(false);
const editingLocation = ref<LocationData | null>(null);

function editLocation(location: LocationData | null) {
    editingLocation.value = location;
    locationOpen.value = true;
}

const categoryOpen = ref(false);
const editingCategory = ref<CategoryData | null>(null);
const categoryForm = useForm({ name: '' });

function editCategory(category: CategoryData | null) {
    editingCategory.value = category;
    categoryOpen.value = true;
}

watch(categoryOpen, (isOpen) => {
    if (isOpen) {
        categoryForm.defaults({ name: editingCategory.value?.name ?? '' });
        categoryForm.reset();
        categoryForm.clearErrors();
    }
});

function saveCategory() {
    categoryForm.submit(
        editingCategory.value
            ? updateCategory({ category: editingCategory.value.id })
            : storeCategory(),
        {
            preserveScroll: true,
            onSuccess: () => {
                categoryOpen.value = false;
            },
        },
    );
}

const removing = ref<CategoryData | null>(null);
const removingOpen = ref(false);
const processing = ref(false);

function askRemove(category: CategoryData) {
    removing.value = category;
    removingOpen.value = true;
}

function confirmRemove() {
    if (!removing.value) {
        return;
    }

    router.delete(destroyCategory({ category: removing.value.id }).url, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (removingOpen.value = false),
    });
}
</script>

<template>
    <Head title="Locations" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Inventory"
            description="Where stock is kept, and the categories items are grouped by."
        >
            <template #actions>
                <Button v-if="can.manage" @click="editLocation(null)">
                    <Plus />
                    New location
                </Button>
            </template>
        </PageHeader>

        <InventoryNav />

        <section aria-labelledby="locations-heading" class="grid gap-3">
            <h2 id="locations-heading" class="text-sm font-semibold">
                Locations
            </h2>
            <ul
                v-if="locations.length"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <li
                    v-for="location in locations"
                    :key="location.id"
                    :class="
                        cn(
                            'flex items-start gap-3 rounded-xl border bg-card p-4 shadow-xs',
                            !location.is_active && 'opacity-70',
                        )
                    "
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"
                    >
                        <MapPin class="size-4" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-baseline gap-2">
                            <span class="truncate font-medium">{{
                                location.name
                            }}</span>
                            <span
                                v-if="location.code"
                                class="figures text-xs text-muted-foreground"
                                >{{ location.code }}</span
                            >
                        </p>
                        <p
                            v-if="location.description"
                            class="truncate text-sm text-muted-foreground"
                        >
                            {{ location.description }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <template v-if="location.items"
                                >{{ plural(location.items, 'item') }},
                                {{ formatNumber(location.units) }} in
                                stock</template
                            >
                            <template v-else>Empty</template>
                            <span v-if="!location.is_active">
                                · No longer used</span
                            >
                        </p>
                    </div>
                    <Button
                        v-if="can.manage"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Edit ${location.name}`"
                        @click="editLocation(location)"
                    >
                        <Pencil />
                    </Button>
                </li>
            </ul>
            <div v-else class="rounded-xl border bg-card shadow-xs">
                <EmptyState
                    :icon="MapPin"
                    title="No locations yet"
                    description="Add the store rooms, shelves, vans or sites where you keep stock. Every movement is booked against one."
                >
                    <Button v-if="can.manage" @click="editLocation(null)"
                        ><Plus />New location</Button
                    >
                </EmptyState>
            </div>
        </section>

        <section aria-labelledby="categories-heading" class="grid gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 id="categories-heading" class="text-sm font-semibold">
                    Categories
                </h2>
                <Button
                    v-if="can.manage"
                    variant="outline"
                    size="sm"
                    @click="editCategory(null)"
                >
                    <Plus />
                    New category
                </Button>
            </div>
            <ul
                v-if="categories.length"
                class="divide-y overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <li
                    v-for="category in categories"
                    :key="category.id"
                    class="flex items-center gap-3 px-4 py-2.5"
                >
                    <Tag
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{
                        category.name
                    }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        plural(category.items_count, 'item')
                    }}</span>
                    <template v-if="can.manage">
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Rename ${category.name}`"
                            @click="editCategory(category)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove ${category.name}`"
                            @click="askRemove(category)"
                        >
                            <Trash2 />
                        </Button>
                    </template>
                </li>
            </ul>
            <p
                v-else
                class="rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No categories yet. Categories group items for filtering, such as
                "Safety" or "Packaging".
            </p>
        </section>

        <LocationDialog
            v-if="can.manage"
            v-model:open="locationOpen"
            :location="editingLocation"
        />

        <Dialog v-if="can.manage" v-model:open="categoryOpen">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{
                        editingCategory
                            ? `Rename ${editingCategory.name}`
                            : 'New category'
                    }}</DialogTitle>
                    <DialogDescription
                        >Items can be filtered by category.</DialogDescription
                    >
                </DialogHeader>
                <form id="category" @submit.prevent="saveCategory">
                    <FormField
                        v-slot="field"
                        label="Name"
                        :error="categoryForm.errors.name"
                    >
                        <Input
                            v-bind="field"
                            v-model="categoryForm.name"
                            v-focus
                            required
                            maxlength="80"
                        />
                    </FormField>
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="categoryForm.processing"
                        @click="categoryOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        form="category"
                        :disabled="categoryForm.processing"
                    >
                        <Spinner v-if="categoryForm.processing" />
                        {{ editingCategory ? 'Save category' : 'Add category' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="removingOpen"
            :title="`Remove ${removing?.name ?? 'category'}?`"
            :description="
                removing?.items_count
                    ? `Its ${plural(removing.items_count, 'item')} stay, without a category.`
                    : 'No items use it.'
            "
            confirm-label="Remove category"
            destructive
            :processing="processing"
            @confirm="confirmRemove"
        />
    </div>
</template>
