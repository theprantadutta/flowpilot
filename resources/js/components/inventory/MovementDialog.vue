<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
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
import { store } from '@/routes/inventory/items/movements';
import type {
    InventoryItemData,
    MovementKind,
    NamedRef,
} from '@/types/inventory';

/**
 * Receive, issue, move or count stock for one item.
 */
const props = defineProps<{
    item: InventoryItemData;
    kind: MovementKind;
    locations: NamedRef[];
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(16)}-0000-4000-8000-${Math.random().toString(16).slice(2, 14).padEnd(12, '0')}`;
}

/** Where the item has stock, for issues, moves and counts. */
const stocked = computed(() => props.item.stock_levels ?? []);

function defaultLocation(): string {
    if (props.kind === 'receipt') {
        return props.item.default_location?.id ?? props.locations[0]?.id ?? '';
    }

    return (
        stocked.value[0]?.location.id ?? props.item.default_location?.id ?? ''
    );
}

function blank() {
    return {
        quantity: '',
        counted: '',
        location_id: defaultLocation(),
        from_location_id: stocked.value[0]?.location.id ?? '',
        to_location_id: '',
        unit_cost: props.item.unit_cost?.input ?? '',
        reference: '',
        notes: '',
        request_key: newKey(),
    };
}

const form = useForm(blank());

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults(blank());
        form.reset();
        form.clearErrors();
    }
});

const titles: Record<MovementKind, string> = {
    receipt: 'Receive stock',
    issue: 'Issue stock',
    transfer: 'Move stock',
    adjustment: 'Record a count',
};

const descriptions: Record<MovementKind, string> = {
    receipt: 'Book a delivery into a location.',
    issue: 'Take stock out for use.',
    transfer: 'Move stock between two locations. The total stays the same.',
    adjustment:
        'Enter what you counted. The difference is booked and kept in the audit trail.',
};

const referenceHints: Record<MovementKind, string> = {
    receipt: 'Delivery note or PO number',
    issue: 'Work order or job',
    transfer: 'Transfer note',
    adjustment: 'Count sheet',
};

const atLocation = computed(() => {
    const id =
        props.kind === 'transfer' ? form.from_location_id : form.location_id;

    return (
        stocked.value.find((level) => level.location.id === id)?.label ?? '0'
    );
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit() {
    form.transform((data) => ({
        type: props.kind,
        request_key: data.request_key,
        reference: data.reference || null,
        notes: data.notes || null,
        ...(props.kind === 'adjustment'
            ? { counted: Number(data.counted), location_id: data.location_id }
            : { quantity: Number(data.quantity) }),
        ...(props.kind === 'transfer'
            ? {
                  from_location_id: data.from_location_id,
                  to_location_id: data.to_location_id,
              }
            : props.kind !== 'adjustment'
              ? { location_id: data.location_id }
              : {}),
        ...(props.kind === 'receipt' && data.unit_cost.trim() !== ''
            ? { unit_cost: data.unit_cost.trim() }
            : {}),
    })).submit(store({ item: props.item.id }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ titles[kind] }}: {{ item.name }}</DialogTitle>
                <DialogDescription
                    >{{ descriptions[kind] }} On hand now:
                    {{ item.stock_label }}.</DialogDescription
                >
            </DialogHeader>

            <form
                id="stock-movement"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <template v-if="kind === 'transfer'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            v-slot="field"
                            label="From"
                            :error="errors.from_location_id"
                            :help="`${atLocation} there`"
                        >
                            <Select v-model="form.from_location_id">
                                <SelectTrigger v-bind="field" class="w-full"
                                    ><SelectValue placeholder="Choose"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="level in stocked"
                                        :key="level.location.id"
                                        :value="level.location.id"
                                        >{{ level.location.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            v-slot="field"
                            label="To"
                            :error="errors.to_location_id"
                        >
                            <Select v-model="form.to_location_id">
                                <SelectTrigger v-bind="field" class="w-full"
                                    ><SelectValue placeholder="Choose"
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="location in locations.filter(
                                            (option) =>
                                                option.id !==
                                                form.from_location_id,
                                        )"
                                        :key="location.id"
                                        :value="location.id"
                                        >{{ location.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>
                </template>
                <FormField
                    v-else
                    v-slot="field"
                    :label="
                        kind === 'receipt'
                            ? 'Received at'
                            : kind === 'issue'
                              ? 'Taken from'
                              : 'Counted at'
                    "
                    :error="errors.location_id"
                    :help="
                        kind === 'receipt'
                            ? undefined
                            : `${atLocation} recorded there`
                    "
                >
                    <Select v-model="form.location_id">
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue placeholder="Choose a location"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="location in locations"
                                :key="location.id"
                                :value="location.id"
                                >{{ location.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-if="kind === 'adjustment'"
                        v-slot="field"
                        label="Counted"
                        :error="errors.counted"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.counted"
                            v-focus
                            type="number"
                            min="0"
                            class="figures"
                            required
                        />
                    </FormField>
                    <FormField
                        v-else
                        v-slot="field"
                        :label="`Quantity (${item.unit.label.toLowerCase()})`"
                        :error="errors.quantity"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.quantity"
                            v-focus
                            type="number"
                            min="1"
                            class="figures"
                            required
                        />
                    </FormField>
                    <FormField
                        v-if="kind === 'receipt'"
                        v-slot="field"
                        label="Unit cost"
                        optional
                        :error="errors.unit_cost"
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
                                class="pl-12 figures"
                            />
                        </div>
                    </FormField>
                    <FormField
                        v-else
                        v-slot="field"
                        label="Reference"
                        optional
                        :error="errors.reference"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.reference"
                            maxlength="80"
                            :placeholder="referenceHints[kind]"
                        />
                    </FormField>
                </div>

                <FormField
                    v-if="kind === 'receipt'"
                    v-slot="field"
                    label="Reference"
                    optional
                    :error="errors.reference"
                >
                    <Input
                        v-bind="field"
                        v-model="form.reference"
                        maxlength="80"
                        placeholder="Delivery note or PO number"
                    />
                </FormField>

                <FormField
                    v-slot="field"
                    :label="
                        kind === 'adjustment' ? 'Why it is different' : 'Notes'
                    "
                    :optional="kind !== 'adjustment'"
                    :error="errors.notes"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.notes"
                        rows="2"
                        maxlength="1000"
                    />
                </FormField>
                <InputError :message="errors.type" />
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
                    form="stock-movement"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ titles[kind] }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
