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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/inventory/suppliers';
import type { SupplierData } from '@/types/inventory';

/**
 * Add a supplier, or edit one when `supplier` is given.
 */
const props = defineProps<{
    supplier?: SupplierData | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const editing = computed(() => !!props.supplier);
/** Kept out of the template, where "//" confuses the type checker. */
const websitePlaceholder = 'https://';

function initial() {
    return {
        name: props.supplier?.name ?? '',
        contact_name: props.supplier?.contact_name ?? '',
        email: props.supplier?.email ?? '',
        phone: props.supplier?.phone ?? '',
        website: props.supplier?.website ?? '',
        lead_time_days: props.supplier?.lead_time_days?.toString() ?? '',
        notes: props.supplier?.notes ?? '',
        is_active: props.supplier?.is_active ?? true,
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

function submit() {
    form.transform((data) => ({
        name: data.name,
        contact_name: data.contact_name || null,
        email: data.email || null,
        phone: data.phone || null,
        website: data.website || null,
        lead_time_days:
            data.lead_time_days === '' ? null : Number(data.lead_time_days),
        notes: data.notes || null,
        is_active: data.is_active,
    })).submit(
        editing.value && props.supplier
            ? update({ supplier: props.supplier.id })
            : store(),
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
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? `Edit ${supplier?.name}` : 'New supplier'
                }}</DialogTitle>
                <DialogDescription
                    >Who you buy from, and how to reach them.</DialogDescription
                >
            </DialogHeader>

            <form id="supplier" class="grid gap-4" @submit.prevent="submit">
                <FormField
                    v-slot="field"
                    label="Name"
                    :error="form.errors.name"
                >
                    <Input
                        v-bind="field"
                        v-model="form.name"
                        v-focus
                        required
                        maxlength="120"
                    />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        v-slot="field"
                        label="Contact"
                        optional
                        :error="form.errors.contact_name"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.contact_name"
                            maxlength="120"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Lead time (days)"
                        optional
                        :error="form.errors.lead_time_days"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.lead_time_days"
                            type="number"
                            min="0"
                            max="365"
                            class="figures"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Email"
                        optional
                        :error="form.errors.email"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            autocomplete="off"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Phone"
                        optional
                        :error="form.errors.phone"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                            autocomplete="off"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="field"
                    label="Website"
                    optional
                    :error="form.errors.website"
                >
                    <Input
                        v-bind="field"
                        v-model="form.website"
                        type="url"
                        maxlength="255"
                        :placeholder="websitePlaceholder"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Notes"
                    optional
                    :error="form.errors.notes"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.notes"
                        rows="2"
                        maxlength="2000"
                    />
                </FormField>
                <div v-if="editing" class="flex items-center gap-2">
                    <Checkbox id="supplier-active" v-model="form.is_active" />
                    <Label for="supplier-active" class="font-normal"
                        >Still buying from this supplier</Label
                    >
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
                    form="supplier"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ editing ? 'Save supplier' : 'Add supplier' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
