<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { publish } from '@/routes/workflows';
import type { ValidationIssue } from '@/types/workflows';

/**
 * Publishing turns the draft into the next version. New runs use it at once;
 * runs already under way keep the version they started on.
 */
const props = defineProps<{
    workflowId: string;
    nextVersion: number;
    issues: ValidationIssue[];
    /** Called first so the latest canvas is saved before publishing. */
    beforePublish: () => Promise<void>;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = useForm({ notes: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

async function submit() {
    await props.beforePublish();

    form.submit(publish({ workflow: props.workflowId }), {
        preserveScroll: true,
        preserveState: true,
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
                <DialogTitle>Publish version {{ nextVersion }}</DialogTitle>
                <DialogDescription>
                    New runs use this version straight away. Runs already under
                    way finish on the version they started with.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="issues.length"
                role="alert"
                class="grid gap-2 rounded-lg border border-warning/40 bg-warning-soft/60 p-3 text-sm text-warning-text"
            >
                <p class="flex items-center gap-1.5 font-medium">
                    <TriangleAlert class="size-4" aria-hidden="true" />
                    {{
                        issues.length === 1
                            ? 'One thing to fix first'
                            : `${issues.length} things to fix first`
                    }}
                </p>
                <ul class="list-disc space-y-0.5 pl-5 text-xs">
                    <li
                        v-for="(issue, index) in issues.slice(0, 8)"
                        :key="index"
                    >
                        {{ issue.message }}
                    </li>
                </ul>
            </div>

            <form
                id="publish-workflow"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <FormField
                    v-slot="field"
                    label="What changed"
                    optional
                    :error="form.errors.notes"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.notes"
                        rows="3"
                        maxlength="500"
                        placeholder="Finance now approves anything over 5,000."
                    />
                </FormField>
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)
                            .definition
                    "
                />
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
                    form="publish-workflow"
                    :disabled="form.processing || issues.length > 0"
                >
                    <Spinner v-if="form.processing" />
                    Publish version {{ nextVersion }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
