<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import PersonPicker from '@/components/PersonPicker.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useOrganization } from '@/composables/useOrganization';
import { store } from '@/routes/workflows/runs';
import type { MemberOption } from '@/types/operations';
import type { ManualInput } from '@/types/workflows';

/**
 * Start a run of a workflow that a person triggers, filling in the details
 * the published version asks for.
 */
const props = defineProps<{
    workflowId: string;
    workflowName: string;
    inputs: ManualInput[];
    members: MemberOption[];
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(16)}-0000-4000-8000-${Math.random().toString(16).slice(2, 14).padEnd(12, '0')}`;
}

function blank(): Record<string, string | number | boolean | null> {
    return Object.fromEntries(
        props.inputs.map((input) => [
            input.key,
            input.type === 'boolean'
                ? false
                : input.type === 'person'
                  ? null
                  : '',
        ]),
    );
}

const form = useForm({ input: blank(), request_key: newKey() });

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults({ input: blank(), request_key: newKey() });
        form.reset();
        form.clearErrors();
    }
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function text(key: string): string {
    const value = form.input[key];

    return typeof value === 'string' ? value : '';
}

function setValue(key: string, value: string | number | boolean | null) {
    form.input = { ...form.input, [key]: value };
}

function submit() {
    form.submit(store({ workflow: props.workflowId }), {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Start {{ workflowName }}</DialogTitle>
                <DialogDescription>
                    {{
                        inputs.length
                            ? 'Fill in the details this workflow needs. You can follow the run as it goes.'
                            : 'This workflow needs no details. Start it and follow the run as it goes.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form
                id="start-workflow-run"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <FormField
                    v-for="input in inputs"
                    :key="input.key"
                    v-slot="field"
                    :label="input.label"
                    :optional="!input.required"
                    :error="errors[`input.${input.key}`]"
                >
                    <div v-if="input.type === 'money'" class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs text-muted-foreground"
                            >{{ organization?.currency }}</span
                        >
                        <Input
                            v-bind="field"
                            :model-value="text(input.key)"
                            inputmode="decimal"
                            placeholder="0.00"
                            class="pl-12 figures"
                            @update:model-value="
                                (value) => setValue(input.key, String(value))
                            "
                        />
                    </div>
                    <Input
                        v-else-if="input.type === 'number'"
                        v-bind="field"
                        :model-value="text(input.key)"
                        inputmode="decimal"
                        class="figures"
                        @update:model-value="
                            (value) => setValue(input.key, String(value))
                        "
                    />
                    <Input
                        v-else-if="input.type === 'date'"
                        v-bind="field"
                        :model-value="text(input.key)"
                        type="date"
                        @update:model-value="
                            (value) => setValue(input.key, String(value))
                        "
                    />
                    <Select
                        v-else-if="input.type === 'select'"
                        :model-value="text(input.key)"
                        @update:model-value="
                            (value) => setValue(input.key, String(value))
                        "
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue placeholder="Choose"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in input.options"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <label
                        v-else-if="input.type === 'boolean'"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Switch
                            v-bind="field"
                            :model-value="form.input[input.key] === true"
                            @update:model-value="
                                (value) => setValue(input.key, Boolean(value))
                            "
                        />
                        Yes
                    </label>
                    <PersonPicker
                        v-else-if="input.type === 'person'"
                        :id="field.id"
                        :model-value="
                            typeof form.input[input.key] === 'number'
                                ? (form.input[input.key] as number)
                                : null
                        "
                        :members="members"
                        none-label="Choose a member"
                        @update:model-value="
                            (value) => setValue(input.key, value)
                        "
                    />
                    <Textarea
                        v-else
                        v-bind="field"
                        :model-value="text(input.key)"
                        rows="2"
                        maxlength="2000"
                        @update:model-value="
                            (value) => setValue(input.key, String(value))
                        "
                    />
                </FormField>
                <InputError :message="errors.workflow ?? errors.input" />
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
                    form="start-workflow-run"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Start run
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
