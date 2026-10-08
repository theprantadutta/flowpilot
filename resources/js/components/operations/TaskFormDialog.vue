<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import DateInput from '@/components/DateInput.vue';
import FormField from '@/components/FormField.vue';
import PersonPicker from '@/components/PersonPicker.vue';
import TagInput from '@/components/TagInput.vue';
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
import { store } from '@/routes/tasks';
import type {
    EnumOption,
    MemberOption,
    ProjectOption,
} from '@/types/operations';

const props = withDefaults(
    defineProps<{
        members: MemberOption[];
        projects: ProjectOption[];
        priorities: EnumOption[];
        statuses: EnumOption[];
        /** Pre-fill when creating from a project or a board column. */
        defaults?: { project_id?: string | null; status?: string };
    }>(),
    { defaults: () => ({}) },
);

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    title: '',
    description: '',
    project_id: props.defaults.project_id ?? null,
    assignee_id: null as number | null,
    priority: 'medium',
    status: props.defaults.status ?? 'todo',
    due_date: null as string | null,
    tags: [] as string[],
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.project_id = props.defaults.project_id ?? form.project_id;
        form.status = props.defaults.status ?? 'todo';
    }
});

function submit(another = false) {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        project_id: data.project_id === 'none' ? null : data.project_id,
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'due_date', 'tags');

            if (!another) {
                open.value = false;
            }
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>New task</DialogTitle>
                <DialogDescription
                    >Give it a clear title; you can fill in the rest now or
                    later.</DialogDescription
                >
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit()">
                <FormField
                    v-slot="field"
                    label="Title"
                    :error="form.errors.title"
                >
                    <Input
                        v-bind="field"
                        v-model="form.title"
                        v-focus
                        required
                        maxlength="200"
                        placeholder="Book the crane for the mezzanine install"
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
                        rows="3"
                        placeholder="What needs to happen, and how will we know it is done?"
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField
                        v-slot="field"
                        label="Project"
                        :error="form.errors.project_id"
                    >
                        <Select v-model="form.project_id">
                            <SelectTrigger v-bind="field" class="w-full">
                                <SelectValue placeholder="No project" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No project</SelectItem>
                                <SelectItem
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                >
                                    {{ project.name }}
                                </SelectItem>
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
                        label="Priority"
                        :error="form.errors.priority"
                    >
                        <Select v-model="form.priority">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in priorities"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        v-slot="field"
                        label="Status"
                        :error="form.errors.status"
                    >
                        <Select v-model="form.status">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        v-slot="field"
                        label="Due date"
                        optional
                        :error="form.errors.due_date"
                    >
                        <DateInput v-bind="field" v-model="form.due_date" />
                    </FormField>

                    <FormField
                        v-slot="field"
                        label="Tags"
                        optional
                        :error="form.errors.tags"
                    >
                        <TagInput :id="field.id" v-model="form.tags" />
                    </FormField>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="form.processing"
                        @click="submit(true)"
                    >
                        Create and add another
                    </Button>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            Create task
                        </Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
