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
import { store, update } from '@/routes/inventory/locations';
import type { LocationData } from '@/types/inventory';

/**
 * Add a place stock is kept, or edit one when `location` is given.
 */
const props = defineProps<{
    location?: LocationData | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const editing = computed(() => !!props.location);

function initial() {
    return {
        name: props.location?.name ?? '',
        code: props.location?.code ?? '',
        description: props.location?.description ?? '',
        is_active: props.location?.is_active ?? true,
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
        code: data.code || null,
        description: data.description || null,
        is_active: data.is_active,
    })).submit(
        editing.value && props.location
            ? update({ location: props.location.id })
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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? `Edit ${location?.name}` : 'New location'
                }}</DialogTitle>
                <DialogDescription
                    >A store room, shelf, van or site where stock is
                    kept.</DialogDescription
                >
            </DialogHeader>

            <form id="location" class="grid gap-4" @submit.prevent="submit">
                <div
                    class="grid gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                >
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
                            maxlength="80"
                            placeholder="Main store"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Code"
                        optional
                        :error="form.errors.code"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.code"
                            maxlength="20"
                            placeholder="MS-1"
                        />
                    </FormField>
                </div>
                <FormField
                    v-slot="field"
                    label="Description"
                    optional
                    :error="form.errors.description"
                >
                    <Input
                        v-bind="field"
                        v-model="form.description"
                        maxlength="255"
                    />
                </FormField>
                <div v-if="editing" class="flex items-center gap-2">
                    <Checkbox id="location-active" v-model="form.is_active" />
                    <Label for="location-active" class="font-normal"
                        >Still in use</Label
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
                    form="location"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    {{ editing ? 'Save location' : 'Add location' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
