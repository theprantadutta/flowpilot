<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DateInput from '@/components/DateInput.vue';
import FormField from '@/components/FormField.vue';
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
import { formatMoney, parseMoney } from '@/lib/format';
import { store } from '@/routes/purchase-requests';
import type { PurchaseOptions } from '@/types/inventory';

/**
 * Ask to buy something: a stocked item (cost, supplier and reorder quantity
 * filled in from the item) or a one-off purchase described in words.
 */
const props = defineProps<{
    options: PurchaseOptions;
    /** Start with this stocked item chosen. */
    itemId?: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(16)}-0000-4000-8000-${Math.random().toString(16).slice(2, 14).padEnd(12, '0')}`;
}

function blank() {
    return {
        inventory_item_id: props.itemId ?? 'none',
        item_name: '',
        supplier_id: 'none',
        deliver_to_location_id: 'none',
        quantity: '',
        unit_cost: '',
        needed_by: null as string | null,
        reason: '',
        request_key: newKey(),
    };
}

const form = useForm(blank());

const chosenItem = computed(() =>
    props.options.items.find((item) => item.id === form.inventory_item_id),
);

/** Fill in what the item already knows, without overwriting edits. */
function prefill() {
    const item = chosenItem.value;

    if (!item) {
        return;
    }

    if (item.unit_cost && form.unit_cost === '') {
        form.unit_cost = item.unit_cost;
    }

    if (item.supplier_id && form.supplier_id === 'none') {
        form.supplier_id = item.supplier_id;
    }

    if (item.reorder_quantity > 0 && form.quantity === '') {
        form.quantity = String(item.reorder_quantity);
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults(blank());
        form.reset();
        form.clearErrors();
        prefill();
    }
});

watch(() => form.inventory_item_id, prefill);

const today = new Date().toISOString().slice(0, 10);

/**
 * The estimated total, shown while typing. The server does the real sum from
 * the text that is sent.
 */
const estimate = computed(() => {
    const quantity = Number(form.quantity);
    const currency = organization.value?.currency ?? 'USD';
    const unitCost = parseMoney(form.unit_cost, currency);

    if (!Number.isInteger(quantity) || quantity < 1 || unitCost === null) {
        return null;
    }

    return formatMoney(unitCost * quantity, currency);
});

const none = (value: string) => (value === 'none' ? null : value);

function submit() {
    form.transform((data) => ({
        inventory_item_id: none(data.inventory_item_id),
        item_name: data.inventory_item_id === 'none' ? data.item_name : null,
        supplier_id: none(data.supplier_id),
        deliver_to_location_id: none(data.deliver_to_location_id),
        quantity: Number(data.quantity),
        unit_cost: data.unit_cost.trim(),
        needed_by: data.needed_by,
        reason: data.reason || null,
        request_key: data.request_key,
    })).post(store().url, {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Request a purchase</DialogTitle>
                <DialogDescription>
                    It goes for approval before anything is ordered.
                </DialogDescription>
            </DialogHeader>

            <form
                id="purchase-request"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <FormField
                    v-slot="field"
                    label="What to buy"
                    :error="form.errors.inventory_item_id"
                >
                    <Select v-model="form.inventory_item_id">
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none"
                                >Something we do not stock</SelectItem
                            >
                            <SelectItem
                                v-for="item in options.items"
                                :key="item.id"
                                :value="item.id"
                                >{{ item.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField
                    v-if="form.inventory_item_id === 'none'"
                    v-slot="field"
                    label="Describe it"
                    :error="form.errors.item_name"
                >
                    <Input
                        v-bind="field"
                        v-model="form.item_name"
                        v-focus
                        maxlength="160"
                        required
                        placeholder="Replacement drive belt for line 2"
                    />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-slot="field"
                        label="Quantity"
                        :error="form.errors.quantity"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.quantity"
                            type="number"
                            min="1"
                            class="figures"
                            required
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Unit cost"
                        :error="form.errors.unit_cost"
                        :help="
                            estimate ? `About ${estimate} in total` : undefined
                        "
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
                                required
                            />
                        </div>
                    </FormField>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
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
                                <SelectItem value="none"
                                    >Not decided</SelectItem
                                >
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
                        label="Deliver to"
                        optional
                        :error="form.errors.deliver_to_location_id"
                    >
                        <Select v-model="form.deliver_to_location_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >Decide on arrival</SelectItem
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

                <FormField
                    v-slot="field"
                    label="Needed by"
                    optional
                    :error="form.errors.needed_by"
                >
                    <DateInput
                        v-bind="field"
                        v-model="form.needed_by"
                        :min="today"
                        class="sm:w-56"
                        clear-label="Clear needed-by date"
                    />
                </FormField>

                <FormField
                    v-slot="field"
                    label="Why it is needed"
                    optional
                    :error="form.errors.reason"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.reason"
                        rows="3"
                        maxlength="2000"
                    />
                </FormField>
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
                    form="purchase-request"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Submit request
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
