<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import DateInput from '@/components/DateInput.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import FormField from '@/components/FormField.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store } from '@/routes/issues';
import type {
    EnumOption,
    MemberOption,
    ProjectOption,
} from '@/types/operations';

const props = withDefaults(
    defineProps<{
        members: MemberOption[];
        projects: ProjectOption[];
        severities: EnumOption[];
        defaults?: { project_id?: string | null };
    }>(),
    { defaults: () => ({}) },
);

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    title: '',
    description: '',
    severity: 'medium',
    project_id: props.defaults.project_id ?? null,
    assignee_id: null as number | null,
    due_date: null as string | null,
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.project_id = props.defaults.project_id ?? form.project_id;
    }
});

const severityHelp: Record<string, string> = {
    critical:
        'Work has stopped, people are at risk, or money is being lost now.',
    high: 'A major part of the work is affected and there is no good workaround.',
    medium: 'Something is wrong but work can continue with a workaround.',
    low: 'A minor problem or an improvement worth tracking.',
};

function submit() {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        project_id: data.project_id === 'none' ? null : data.project_id,
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Report an issue</DialogTitle>
                <DialogDescription
                    >Describe what is wrong and how badly it affects
                    work.</DialogDescription
                >
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit">
                <FormField
                    v-slot="field"
                    label="What is wrong?"
                    :error="form.errors.title"
                >
                    <Input
                        v-bind="field"
                        v-model="form.title"
                        v-focus
                        required
                        maxlength="200"
                        placeholder="Conveyor 2 stops when fully loaded"
                    />
                </FormField>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Severity</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <label
                            v-for="option in severities"
                            :key="option.value"
                            :class="
                                cn(
                                    'flex cursor-pointer items-center justify-center rounded-lg border p-2.5 transition-colors hover:bg-accent/50',
                                    'has-[:checked]:border-primary has-[:checked]:bg-info-soft/50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                )
                            "
                        >
                            <input
                                v-model="form.severity"
                                type="radio"
                                name="severity"
                                :value="option.value"
                                class="sr-only"
                            />
                            <EnumBadge :option="option" variant="plain" />
                        </label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ severityHelp[form.severity] }}
                    </p>
                </fieldset>

                <FormField
                    v-slot="field"
                    label="Details"
                    optional
                    :error="form.errors.description"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.description"
                        rows="4"
                        placeholder="When does it happen? What have you tried?"
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField
                        v-slot="field"
                        label="Project"
                        :error="form.errors.project_id"
                    >
                        <Select v-model="form.project_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue placeholder="No project"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No project</SelectItem>
                                <SelectItem
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                    >{{ project.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Assignee"
                        :error="form.errors.assignee_id"
                    >
                        <PersonPicker
                            :id="field.id"
                            v-model="form.assignee_id"
                            :members="members"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Fix by"
                        optional
                        :error="form.errors.due_date"
                    >
                        <DateInput v-bind="field" v-model="form.due_date" />
                    </FormField>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        Report issue
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
