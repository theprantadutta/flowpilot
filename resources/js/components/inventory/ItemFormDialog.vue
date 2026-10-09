<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useOrganization } from '@/composables/useOrganization';
import { store, update } from '@/routes/inventory/items';
import type { InventoryItemData, InventoryOptions } from '@/types/inventory';

/**
 * Add a stock item, or edit one when `item` is given. Stock itself changes
 * only through movements; new items can start with what is on the shelf.
 */
const props = defineProps<{
    options: InventoryOptions;
    item?: InventoryItemData | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();
const editing = computed(() => !!props.item);

function initial() {
    return {
        sku: props.item?.sku ?? '',
        name: props.item?.name ?? '',
        description: props.item?.description ?? '',
        unit: props.item?.unit.value ?? 'each',
        category_id: props.item?.category?.id ?? 'none',
        supplier_id: props.item?.supplier?.id ?? 'none',
        default_location_id: props.item?.default_location?.id ?? 'none',
        minimum_stock: String(props.item?.minimum_stock ?? 0),
        reorder_point: String(props.item?.reorder_point ?? 0),
        reorder_quantity: String(props.item?.reorder_quantity ?? 0),
        unit_cost: props.item?.unit_cost?.input ?? '',
        opening_stock: '',
        is_active: props.item?.is_active ?? true,
    };
}

const form = useForm(initial());

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults(initial());
        form.reset();
        form.clearErrors();
    }
});

const none = (value: string) => (value === 'none' ? null : value);

/** The opening location is sent alongside, so its error shows here too. */
const openingError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.opening_stock ?? errors.opening_location_id;
});

function submit() {
    form.transform((data) => ({
        sku: data.sku,
        name: data.name,
        description: data.description || null,
        unit: data.unit,
        category_id: none(data.category_id),
        supplier_id: none(data.supplier_id),
        default_location_id: none(data.default_location_id),
        minimum_stock: Number(data.minimum_stock || 0),
        reorder_point: Number(data.reorder_point || 0),
        reorder_quantity: Number(data.reorder_quantity || 0),
        unit_cost: data.unit_cost.trim() === '' ? null : data.unit_cost.trim(),
        ...(editing.value ? { is_active: data.is_active } : {}),
        ...(editing.value || data.opening_stock === ''
            ? {}
            : {
                  opening_stock: Number(data.opening_stock),
                  opening_location_id: none(data.default_location_id),
              }),
    })).submit(
        editing.value && props.item ? update({ item: props.item.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? `Edit ${item?.name}` : 'New stock item'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editing
                            ? 'Stock levels change through movements, not here.'
                            : 'Add something you keep in stock. You can book what is already on the shelf as opening stock.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form
                id="inventory-item"
                class="grid gap-5"
                @submit.prevent="submit"
            >
                <div
                    class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                >
                    <FormField
                        v-slot="field"
                        label="SKU"
                        :error="form.errors.sku"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.sku"
                            v-focus
                            required
                            maxlength="60"
                            class="uppercase"
                            placeholder="GLV-100"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Name"
                        :error="form.errors.name"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.name"
                            required
                            maxlength="160"
                            placeholder="Nitrile gloves (box of 100)"
                        />
                    </FormField>
                </div>

                <FormField
                    v-slot="field"
                    label="Description"
                    optional
                    :error="form.errors.description"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.description"
                        rows="2"
                        maxlength="5000"
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField
                        v-slot="field"
                        label="Counted in"
                        :error="form.errors.unit"
                    >
                        <Select v-model="form.unit">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="unit in options.units"
                                    :key="unit.value"
                                    :value="unit.value"
                                    >{{ unit.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Category"
                        optional
                        :error="form.errors.category_id"
                    >
                        <Select v-model="form.category_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None</SelectItem>
                                <SelectItem
                                    v-for="category in options.categories"
                                    :key="category.id"
                                    :value="category.id"
                                    >{{ category.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Unit cost"
                        optional
                        :error="form.errors.unit_cost"
                    >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs text-muted-foreground"
                                >{{ organization?.currency }}</span
                            >
                            <Input
                                v-bind="field"
                                v-model="form.unit_cost"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="pl-12 figures"
                            />
                        </div>
                    </FormField>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField
                        v-slot="field"
                        label="Supplier"
                        optional
                        :error="form.errors.supplier_id"
                    >
                        <Select v-model="form.supplier_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None</SelectItem>
                                <SelectItem
                                    v-for="supplier in options.suppliers"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                    >{{ supplier.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Usually kept at"
                        optional
                        :error="form.errors.default_location_id"
                    >
                        <Select v-model="form.default_location_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >No usual location</SelectItem
                                >
                                <SelectItem
                                    v-for="location in options.locations"
                                    :key="location.id"
                                    :value="location.id"
                                    >{{ location.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                <fieldset class="grid gap-3 rounded-xl border p-4">
                    <legend class="px-1 text-sm font-medium">Reordering</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField
                            v-slot="field"
                            label="Minimum stock"
                            help="Never go below this."
                            :error="form.errors.minimum_stock"
                        >
                            <Input
                                v-bind="field"
                                v-model="form.minimum_stock"
                                type="number"
                                min="0"
                                class="figures"
                            />
                        </FormField>
                        <FormField
                            v-slot="field"
                            label="Reorder point"
                            help="Warn at or below this."
                            :error="form.errors.reorder_point"
                        >
                            <Input
                                v-bind="field"
                                v-model="form.reorder_point"
                                type="number"
                                min="0"
                                class="figures"
                            />
                        </FormField>
                        <FormField
                            v-slot="field"
                            label="Reorder quantity"
                            help="How many to order."
                            :error="form.errors.reorder_quantity"
                        >
                            <Input
                                v-bind="field"
                                v-model="form.reorder_quantity"
                                type="number"
                                min="0"
                                class="figures"
                            />
                        </FormField>
                    </div>
                </fieldset>

                <FormField
                    v-if="!editing"
                    v-slot="field"
                    label="Opening stock"
                    optional
                    :help="
                        form.default_location_id === 'none'
                            ? 'Choose where it is usually kept to book opening stock.'
                            : 'Booked as a receipt at its usual location.'
                    "
                    :error="openingError"
                >
                    <Input
                        v-bind="field"
                        v-model="form.opening_stock"
                        type="number"
                        min="0"
                        class="figures sm:w-48"
                        :disabled="form.default_location_id === 'none'"
                    />
                </FormField>

                <div v-if="editing" class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <Checkbox id="item-active" v-model="form.is_active" />
                        <Label for="item-active" class="font-normal"
                            >Still stocked</Label
                        >
                    </div>
                    <p class="pl-6 text-xs text-muted-foreground">
                        Archived items drop out of lists, alerts and purchase
                        forms. Their history stays.
                    </p>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="inventory-item"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ editing ? 'Save item' : 'Add item' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
