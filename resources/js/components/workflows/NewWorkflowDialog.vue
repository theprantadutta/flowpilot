<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { cn } from '@/lib/utils';
import { store } from '@/routes/workflows';
import type { WorkflowTemplateOption } from '@/types/workflows';

/**
 * Start a workflow from a template or from scratch. It opens in the builder
 * as a draft; nothing runs until it is published.
 */
const props = defineProps<{
    templates: WorkflowTemplateOption[];
    triggers: { value: string; label: string; description: string }[];
    /** Preselect a template, e.g. from the empty state. */
    initialTemplate?: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    name: '',
    description: '',
    template: 'blank',
    trigger: 'manual',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        choose(props.initialTemplate ?? 'blank');
    }
});

const chosen = computed(() =>
    props.templates.find((template) => template.key === form.template),
);
const triggerLabel = (value: string) =>
    props.triggers.find((trigger) => trigger.value === value)?.label ?? value;

function choose(key: string) {
    const previous = chosen.value;
    form.template = key;

    // Suggest the template's name until the person types their own.
    if (!form.name || form.name === previous?.name) {
        form.name = key === 'blank' ? '' : (chosen.value?.name ?? '');
    }
}

function submit() {
    form.transform((data) => ({
        name: data.name,
        description: data.description || null,
        template: data.template === 'blank' ? null : data.template,
        trigger: data.template === 'blank' ? data.trigger : null,
    })).submit(store());
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>New workflow</DialogTitle>
                <DialogDescription
                    >Start from a template or a blank canvas. It stays a draft
                    until you publish it.</DialogDescription
                >
            </DialogHeader>

            <form id="new-workflow" class="grid gap-5" @submit.prevent="submit">
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Start from</legend>
                    <div
                        class="grid gap-2 sm:grid-cols-2"
                        role="radiogroup"
                        aria-label="Template"
                    >
                        <button
                            v-for="template in templates"
                            :key="template.key"
                            type="button"
                            role="radio"
                            :aria-checked="form.template === template.key"
                            :class="
                                cn(
                                    'relative grid gap-1 rounded-xl border p-3 text-left transition-[border-color,box-shadow] hover:border-border-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    form.template === template.key &&
                                        'border-primary ring-1 ring-primary',
                                )
                            "
                            @click="choose(template.key)"
                        >
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-sm font-semibold">{{
                                    template.name
                                }}</span>
                                <Check
                                    v-if="form.template === template.key"
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                template.description
                            }}</span>
                            <span
                                class="mt-1 text-[0.6875rem] font-medium text-muted-foreground"
                            >
                                {{ template.category }} ·
                                {{ triggerLabel(template.trigger)
                                }}<template v-if="template.key !== 'blank'">
                                    · {{ template.steps }} steps</template
                                >
                            </span>
                        </button>
                    </div>
                </fieldset>

                <FormField
                    v-if="form.template === 'blank'"
                    v-slot="field"
                    label="Starts when"
                    :error="form.errors.trigger"
                    :help="
                        triggers.find(
                            (trigger) => trigger.value === form.trigger,
                        )?.description
                    "
                >
                    <Select v-model="form.trigger">
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="trigger in triggers"
                                :key="trigger.value"
                                :value="trigger.value"
                                >{{ trigger.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
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
                        maxlength="120"
                        placeholder="Purchase approval"
                    />
                </FormField>

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
                        maxlength="1000"
                        :placeholder="
                            chosen?.key !== 'blank'
                                ? chosen?.description
                                : 'What this workflow is for'
                        "
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
                    form="new-workflow"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Create and open builder
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
