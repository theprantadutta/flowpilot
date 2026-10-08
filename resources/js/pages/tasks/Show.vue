<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DateInput from '@/components/DateInput.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InlineText from '@/components/InlineText.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import ChecklistEditor from '@/components/operations/ChecklistEditor.vue';
import CommentThread from '@/components/operations/CommentThread.vue';
import DependencyEditor from '@/components/operations/DependencyEditor.vue';
import FileList from '@/components/operations/FileList.vue';
import PersonPicker from '@/components/PersonPicker.vue';
import TagInput from '@/components/TagInput.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, timeAgo } from '@/lib/format';
import { show as projectShow } from '@/routes/projects';
import { destroy, index as tasksIndex, show, update } from '@/routes/tasks';
import { store as storeAttachment } from '@/routes/tasks/attachments';
import { store as storeComment } from '@/routes/tasks/comments';
import type { ActivityEntry } from '@/types/activity';
import type {
    CommentItem,
    EnumOption,
    FileItem,
    MemberOption,
    ProjectOption,
    TaskItem,
} from '@/types/operations';

const props = defineProps<{
    task: { data: TaskItem } | TaskItem;
    comments: CommentItem[];
    attachments: FileItem[];
    activity?: ActivityEntry[];
    dependencyOptions?: { id: string; label: string }[];
    statuses: EnumOption[];
    priorities: EnumOption[];
    members?: MemberOption[];
    projects?: ProjectOption[];
}>();

const task = computed<TaskItem>(() =>
    'data' in props.task ? props.task.data : props.task,
);
const editable = computed(() => !!task.value.can?.update);

setLayoutProps({
    breadcrumbs: [
        { title: 'Tasks', href: tasksIndex() },
        { title: task.value.reference, href: show({ task: task.value.id }) },
    ],
});

const page = usePage();
const { organization } = useOrganization();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

function save(
    changes: Record<string, string | number | boolean | string[] | null>,
) {
    router.visit(update.patch({ task: task.value.id }), {
        data: changes,
        preserveScroll: true,
        only: ['task', 'activity', 'errors'],
    });
}

const projectModel = computed({
    get: () => task.value.project?.id ?? 'none',
    set: (value: string) =>
        save({ project_id: value === 'none' ? null : value }),
});

const tags = computed({
    get: () => task.value.tags,
    set: (value: string[]) => save({ tags: value }),
});

const deleting = ref(false);

function remove() {
    router.visit(destroy({ task: task.value.id }));
}

const isDone = computed(() => task.value.status.value === 'done');
</script>

<template>
    <Head :title="`${task.reference} ${task.title}`" />

    <div
        class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:py-8"
    >
        <main class="grid min-w-0 content-start gap-8">
            <header class="grid gap-3">
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="figures">{{ task.reference }}</span>
                    <template v-if="task.project">
                        <span aria-hidden="true">/</span>
                        <Link
                            :href="projectShow({ project: task.project.id })"
                            class="hover:text-foreground hover:underline"
                            >{{ task.project.name }}</Link
                        >
                    </template>
                </div>
                <InlineText
                    :value="task.title"
                    label="title"
                    :editable="editable"
                    :error="errors.title"
                    class="text-2xl leading-tight font-semibold"
                    @save="(value) => save({ title: value })"
                />
                <div class="flex flex-wrap items-center gap-3">
                    <EnumBadge :option="task.status" />
                    <EnumBadge :option="task.priority" variant="plain" />
                    <span
                        v-if="task.is_overdue"
                        class="text-xs font-medium text-danger-text"
                        >Overdue</span
                    >
                    <Button
                        v-if="editable"
                        size="sm"
                        :variant="isDone ? 'outline' : 'default'"
                        class="ml-auto"
                        @click="save({ status: isDone ? 'todo' : 'done' })"
                    >
                        <NamedIcon
                            :name="isDone ? 'circle' : 'circle-check'"
                            class="size-4"
                        />
                        {{ isDone ? 'Reopen task' : 'Mark as done' }}
                    </Button>
                </div>
            </header>

            <section aria-labelledby="description-heading" class="grid gap-2">
                <h2 id="description-heading" class="text-sm font-semibold">
                    Description
                </h2>
                <InlineText
                    :value="task.description"
                    label="description"
                    :editable="editable"
                    multiline
                    :maxlength="20000"
                    placeholder="Add details: what needs doing and how we will know it is done."
                    class="text-sm leading-relaxed"
                    @save="(value) => save({ description: value || null })"
                />
            </section>

            <section aria-labelledby="checklist-heading" class="grid gap-3">
                <h2 id="checklist-heading" class="text-sm font-semibold">
                    Checklist
                </h2>
                <ChecklistEditor
                    :task-id="task.id"
                    :items="task.checklist?.items ?? []"
                    :editable="editable"
                />
            </section>

            <section aria-labelledby="files-heading" class="grid gap-3">
                <h2 id="files-heading" class="text-sm font-semibold">Files</h2>
                <FileList
                    :files="attachments"
                    :upload-to="
                        editable ? storeAttachment({ task: task.id }) : null
                    "
                    compact
                />
            </section>

            <section aria-labelledby="comments-heading" class="grid gap-3">
                <h2 id="comments-heading" class="text-sm font-semibold">
                    Comments
                </h2>
                <CommentThread
                    :comments="comments"
                    :post-to="storeComment({ task: task.id })"
                    :timezone="organization?.timezone"
                />
            </section>

            <section aria-labelledby="history-heading" class="grid gap-3">
                <h2 id="history-heading" class="text-sm font-semibold">
                    History
                </h2>
                <div v-if="activity === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-9 w-full" />
                </div>
                <ol v-else-if="activity.length">
                    <ActivityItem
                        v-for="(entry, index) in activity"
                        :key="entry.id"
                        :entry="entry"
                        :timezone="organization?.timezone"
                        :connected="index < activity.length - 1"
                    />
                </ol>
            </section>
        </main>

        <aside
            class="grid h-fit gap-5 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6"
        >
            <div class="grid gap-1.5">
                <label
                    for="task-status"
                    class="text-xs font-medium text-muted-foreground"
                    >Status</label
                >
                <Select
                    :model-value="task.status.value"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ status: String(value) })
                    "
                >
                    <SelectTrigger id="task-status" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            <span class="flex items-center gap-2"
                                ><NamedIcon
                                    :name="option.icon"
                                    class="size-4"
                                />{{ option.label }}</span
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="task-priority"
                    class="text-xs font-medium text-muted-foreground"
                    >Priority</label
                >
                <Select
                    :model-value="task.priority.value"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ priority: String(value) })
                    "
                >
                    <SelectTrigger id="task-priority" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in priorities"
                            :key="option.value"
                            :value="option.value"
                        >
                            <span class="flex items-center gap-2"
                                ><NamedIcon
                                    :name="option.icon"
                                    class="size-4"
                                />{{ option.label }}</span
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="task-assignee"
                    class="text-xs font-medium text-muted-foreground"
                    >Assignee</label
                >
                <PersonPicker
                    id="task-assignee"
                    :model-value="task.assignee?.id ?? null"
                    :members="members ?? []"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ assignee_id: value })
                    "
                />
                <p v-if="errors.assignee_id" class="text-sm text-danger-text">
                    {{ errors.assignee_id }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="task-due"
                    class="text-xs font-medium text-muted-foreground"
                    >Due date</label
                >
                <DateInput
                    id="task-due"
                    :model-value="task.due_date"
                    :disabled="!editable"
                    @update:model-value="(value) => save({ due_date: value })"
                />
            </div>

            <div class="grid gap-1.5">
                <label
                    for="task-project"
                    class="text-xs font-medium text-muted-foreground"
                    >Project</label
                >
                <Select v-model="projectModel" :disabled="!editable">
                    <SelectTrigger id="task-project" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No project</SelectItem>
                        <SelectItem
                            v-if="
                                task.project &&
                                !(projects ?? []).some(
                                    (p) => p.id === task.project?.id,
                                )
                            "
                            :value="task.project.id"
                        >
                            {{ task.project.name }}
                        </SelectItem>
                        <SelectItem
                            v-for="project in projects ?? []"
                            :key="project.id"
                            :value="project.id"
                            >{{ project.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="task-tags"
                    class="text-xs font-medium text-muted-foreground"
                    >Tags</label
                >
                <TagInput id="task-tags" v-model="tags" />
            </div>

            <div class="grid gap-2 border-t pt-4">
                <h2 class="text-xs font-medium text-muted-foreground">
                    Dependencies
                </h2>
                <DependencyEditor
                    :task-id="task.id"
                    :dependencies="task.dependencies ?? []"
                    :dependents="task.dependents ?? []"
                    :options="dependencyOptions ?? []"
                    :editable="editable"
                />
            </div>

            <dl class="grid gap-2 border-t pt-4 text-xs text-muted-foreground">
                <div
                    v-if="task.reporter"
                    class="flex items-center justify-between gap-2"
                >
                    <dt>Created by</dt>
                    <dd class="flex items-center gap-1.5 text-foreground">
                        <MemberAvatar
                            :name="task.reporter.name"
                            :avatar="task.reporter.avatar"
                            class="size-5 text-[0.625rem]"
                        />
                        {{ task.reporter.name }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt>Created</dt>
                    <dd
                        :title="
                            formatDateTime(
                                task.created_at,
                                organization?.timezone,
                            )
                        "
                    >
                        {{ timeAgo(task.created_at) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt>Updated</dt>
                    <dd
                        :title="
                            formatDateTime(
                                task.updated_at,
                                organization?.timezone,
                            )
                        "
                    >
                        {{ timeAgo(task.updated_at) }}
                    </dd>
                </div>
                <div
                    v-if="task.completed_at"
                    class="flex justify-between gap-2"
                >
                    <dt>Completed</dt>
                    <dd>
                        {{
                            formatDateTime(
                                task.completed_at,
                                organization?.timezone,
                            )
                        }}
                    </dd>
                </div>
            </dl>

            <Button
                v-if="task.can?.delete"
                variant="ghost"
                size="sm"
                class="justify-start text-danger-text"
                @click="deleting = true"
            >
                <Trash2 />
                Delete task
            </Button>
        </aside>

        <ConfirmDialog
            v-model:open="deleting"
            :title="`Delete ${task.reference}?`"
            description="The task, its checklist, comments and files are removed. The activity log keeps a record that it existed."
            confirm-label="Delete task"
            destructive
            @confirm="remove"
        />
    </div>
</template>
