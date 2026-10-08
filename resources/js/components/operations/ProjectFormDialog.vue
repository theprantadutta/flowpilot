<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DateInput from '@/components/DateInput.vue';
import FormField from '@/components/FormField.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
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
import { useOrganization } from '@/composables/useOrganization';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/projects';
import type { EnumOption, MemberOption, ProjectItem } from '@/types/operations';

/**
 * Create a project, or edit one when `project` is given.
 */
const props = defineProps<{
    members: MemberOption[];
    statuses: EnumOption[];
    priorities: EnumOption[];
    project?: ProjectItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();

const editing = computed(() => !!props.project);

function initial() {
    return {
        name: props.project?.name ?? '',
        description: props.project?.description ?? '',
        status: props.project?.status.value ?? 'planning',
        priority: props.project?.priority.value ?? 'medium',
        owner_id: props.project?.owner?.id ?? null,
        start_date: props.project?.start_date ?? null,
        due_date: props.project?.due_date ?? null,
        budget: props.project?.budget?.input ?? '',
        member_ids: (props.project?.members ?? []).map((member) => member.id),
        tags: [...(props.project?.tags ?? [])],
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

function toggleMember(id: number) {
    form.member_ids = form.member_ids.includes(id)
        ? form.member_ids.filter((memberId) => memberId !== id)
        : [...form.member_ids, id];
}

function submit() {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        budget: data.budget.trim() === '' ? null : data.budget.trim(),
    })).submit(
        editing.value && props.project
            ? update({ project: props.project.id })
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? `Edit ${project?.name}` : 'New project'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editing
                            ? 'Changes are recorded in the project activity.'
                            : 'A project groups tasks, issues and files around one outcome.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit">
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
                        maxlength="160"
                        placeholder="Factory expansion"
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
                        placeholder="What does done look like?"
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-3">
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
                                    >{{ option.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
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
                                    >{{ option.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Owner"
                        :error="form.errors.owner_id"
                    >
                        <PersonPicker
                            :id="field.id"
                            v-model="form.owner_id"
                            :members="members"
                            :none-label="editing ? 'No owner' : 'You'"
                        />
                    </FormField>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField
                        v-slot="field"
                        label="Start date"
                        optional
                        :error="form.errors.start_date"
                    >
                        <DateInput v-bind="field" v-model="form.start_date" />
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Due date"
                        optional
                        :error="form.errors.due_date"
                    >
                        <DateInput
                            v-bind="field"
                            v-model="form.due_date"
                            :min="form.start_date ?? undefined"
                        />
                    </FormField>
                    <FormField
                        v-slot="field"
                        :label="`Budget (${organization?.currency ?? 'USD'})`"
                        optional
                        :error="form.errors.budget"
                    >
                        <Input
                            v-bind="field"
                            v-model="form.budget"
                            inputmode="decimal"
                            placeholder="250,000"
                            class="figures"
                        />
                    </FormField>
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        Members
                        <span class="font-normal text-muted-foreground"
                            >(they get updates about this project)</span
                        >
                    </legend>
                    <div
                        class="flex max-h-40 scrollbar-thin flex-wrap gap-2 overflow-y-auto"
                    >
                        <button
                            v-for="member in members"
                            :key="member.id"
                            type="button"
                            :aria-pressed="form.member_ids.includes(member.id)"
                            :class="
                                cn(
                                    'inline-flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm transition-colors',
                                    form.member_ids.includes(member.id)
                                        ? 'border-primary bg-info-soft text-info-text'
                                        : 'hover:bg-accent',
                                )
                            "
                            @click="toggleMember(member.id)"
                        >
                            <MemberAvatar
                                :name="member.name"
                                :avatar="member.avatar"
                                class="size-6 text-[0.625rem]"
                            />
                            {{ member.name }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.member_ids"
                        class="text-sm text-danger-text"
                    >
                        {{ form.errors.member_ids }}
                    </p>
                </fieldset>

                <FormField
                    v-slot="field"
                    label="Tags"
                    optional
                    :error="form.errors.tags"
                >
                    <TagInput :id="field.id" v-model="form.tags" />
                </FormField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Spinner v-if="form.processing" />
                        {{ editing ? 'Save project' : 'Create project' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
