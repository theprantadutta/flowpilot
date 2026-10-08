<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ChevronDown, MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InputError from '@/components/InputError.vue';
import FileList from '@/components/operations/FileList.vue';
import IssueFormDialog from '@/components/operations/IssueFormDialog.vue';
import IssueTable from '@/components/operations/IssueTable.vue';
import ProjectFormDialog from '@/components/operations/ProjectFormDialog.vue';
import ProjectOverview from '@/components/operations/ProjectOverview.vue';
import TaskFormDialog from '@/components/operations/TaskFormDialog.vue';
import TaskTable from '@/components/operations/TaskTable.vue';
import TaskTimeline from '@/components/operations/TaskTimeline.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useOrganization } from '@/composables/useOrganization';
import { cn } from '@/lib/utils';
import {
    destroy,
    index as projectsIndex,
    show,
    update,
} from '@/routes/projects';
import { store as storeAttachment } from '@/routes/projects/attachments';
import type { ActivityEntry } from '@/types/activity';
import type {
    EnumOption,
    FileItem,
    IssueItem,
    MemberOption,
    ProjectItem,
    ResourceCollection,
    TaskItem,
} from '@/types/operations';

type Tab = 'overview' | 'tasks' | 'timeline' | 'issues' | 'files' | 'activity';

const props = defineProps<{
    project: { data: ProjectItem } | ProjectItem;
    tab: Tab;
    summary?: {
        by_status: Record<string, number>;
        overdue: number;
        due_this_week: number;
        critical_issues: number;
    };
    tasks?: ResourceCollection<TaskItem>;
    timeline?: ResourceCollection<TaskItem>;
    issues?: ResourceCollection<IssueItem>;
    files?: FileItem[];
    activity?: ActivityEntry[];
    statuses: EnumOption[];
    priorities: EnumOption[];
    taskStatuses: EnumOption[];
    severities: EnumOption[];
    members?: MemberOption[];
    canCreateTasks: boolean;
    canCreateIssues: boolean;
}>();

// A single resource arrives wrapped in `data`.
const project = computed<ProjectItem>(() =>
    'data' in props.project ? props.project.data : props.project,
);

setLayoutProps({
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        {
            title: project.value.name,
            href: show({ project: project.value.id }),
        },
    ],
});

const { organization } = useOrganization();

const tabs: { id: Tab; label: string; prop?: keyof typeof props }[] = [
    { id: 'overview', label: 'Overview' },
    { id: 'tasks', label: 'Tasks', prop: 'tasks' },
    { id: 'timeline', label: 'Timeline', prop: 'timeline' },
    { id: 'issues', label: 'Issues', prop: 'issues' },
    { id: 'files', label: 'Files', prop: 'files' },
    { id: 'activity', label: 'Activity', prop: 'activity' },
];

const activeTab = ref<Tab>(props.tab);
const loading = ref(false);

function selectTab(tab: Tab) {
    activeTab.value = tab;
    const definition = tabs.find((item) => item.id === tab);

    router.visit(
        show(
            { project: project.value.id },
            { query: tab === 'overview' ? {} : { tab } },
        ),
        {
            only: definition?.prop
                ? [definition.prop as string, 'tab']
                : ['tab', 'summary'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

onMounted(() => {
    const definition = tabs.find((item) => item.id === activeTab.value);

    if (definition?.prop && props[definition.prop] === undefined) {
        selectTab(activeTab.value);
    }
});

const editing = ref(false);
const creatingTask = ref(false);
const reportingIssue = ref(false);
const deleting = ref(false);

function setStatus(status: string) {
    router.visit(update({ project: project.value.id }), {
        data: { status },
        preserveScroll: true,
        only: ['project', 'summary'],
    });
}

const deleteForm = useForm({ confirm_name: '' });

function confirmDelete() {
    deleteForm.submit(destroy({ project: project.value.id }));
}
</script>

<template>
    <Head :title="project.name" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl leading-tight font-semibold text-balance"
                    >
                        {{ project.name }}
                    </h1>
                    <DropdownMenu v-if="project.can?.update">
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-full focus-visible:outline-2"
                                :aria-label="`Status: ${project.status.label}. Change status`"
                            >
                                <EnumBadge :option="project.status" />
                                <ChevronDown
                                    class="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuLabel
                                class="text-xs font-normal text-muted-foreground"
                                >Move project to</DropdownMenuLabel
                            >
                            <DropdownMenuItem
                                v-for="status in statuses"
                                :key="status.value"
                                :disabled="
                                    status.value === project.status.value
                                "
                                @select="setStatus(status.value)"
                            >
                                <EnumBadge :option="status" variant="plain" />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <EnumBadge v-else :option="project.status" />
                </div>
                <p
                    v-if="project.description"
                    class="line-clamp-1 max-w-3xl text-sm text-muted-foreground"
                >
                    {{ project.description }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Button v-if="canCreateTasks" @click="creatingTask = true">
                    <Plus />
                    Add task
                </Button>
                <Button
                    v-if="project.can?.update"
                    variant="outline"
                    @click="editing = true"
                >
                    <Pencil />
                    Edit
                </Button>
                <DropdownMenu v-if="project.can?.delete">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="More project actions"
                            ><MoreHorizontal
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-if="canCreateIssues"
                            @select="reportingIssue = true"
                            >Report an issue</DropdownMenuItem
                        >
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            @select="deleting = true"
                        >
                            <Trash2 />
                            Delete project
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <nav
            role="tablist"
            aria-label="Project sections"
            class="-mx-1 flex scrollbar-thin gap-1 overflow-x-auto overflow-y-hidden border-b px-1"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.id"
                :class="
                    cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm transition-colors',
                        activeTab === tab.id
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )
                "
                @click="selectTab(tab.id)"
            >
                {{ tab.label }}
                <span
                    v-if="tab.id === 'tasks' && project.tasks_count"
                    class="ml-1 figures text-xs text-muted-foreground"
                    >{{ project.tasks_count }}</span
                >
                <span
                    v-if="tab.id === 'issues' && project.open_issues_count"
                    class="ml-1 figures text-xs text-warning-text"
                    >{{ project.open_issues_count }}</span
                >
            </button>
        </nav>

        <section role="tabpanel" :aria-busy="loading">
            <ProjectOverview
                v-if="activeTab === 'overview'"
                :project="project"
                :summary="summary"
                :task-statuses="taskStatuses"
            />

            <template v-else-if="activeTab === 'tasks'">
                <div v-if="tasks === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 5" :key="n" class="h-14 w-full" />
                </div>
                <div
                    v-else
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <TaskTable
                        v-if="tasks.data.length"
                        :tasks="tasks.data"
                        :show-project="false"
                    />
                    <EmptyState
                        v-else
                        :icon="Plus"
                        title="No tasks in this project yet"
                        description="Break the work into tasks so people know what to do next."
                    >
                        <Button
                            v-if="canCreateTasks"
                            @click="creatingTask = true"
                            ><Plus />Add task</Button
                        >
                    </EmptyState>
                </div>
            </template>

            <template v-else-if="activeTab === 'timeline'">
                <div v-if="timeline === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 4" :key="n" class="h-16 w-full" />
                </div>
                <div
                    v-else
                    class="rounded-xl border bg-card p-5 shadow-xs sm:p-6"
                >
                    <TaskTimeline :tasks="timeline.data" />
                </div>
            </template>

            <template v-else-if="activeTab === 'issues'">
                <div v-if="issues === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-14 w-full" />
                </div>
                <div
                    v-else
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        v-if="canCreateIssues"
                        class="flex justify-end border-b px-4 py-2.5"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="reportingIssue = true"
                            ><Plus />Report an issue</Button
                        >
                    </div>
                    <IssueTable
                        v-if="issues.data.length"
                        :issues="issues.data"
                        :show-project="false"
                    />
                    <EmptyState
                        v-else
                        :icon="Plus"
                        title="No issues reported"
                        description="Problems found while doing this work are tracked here."
                        compact
                    />
                </div>
            </template>

            <template v-else-if="activeTab === 'files'">
                <div v-if="files === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-12 w-full" />
                </div>
                <div v-else class="rounded-xl border bg-card p-5 shadow-xs">
                    <FileList
                        :files="files"
                        :upload-to="
                            project.can?.update
                                ? storeAttachment({ project: project.id })
                                : null
                        "
                    />
                </div>
            </template>

            <template v-else-if="activeTab === 'activity'">
                <div v-if="activity === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 5" :key="n" class="h-10 w-full" />
                </div>
                <div v-else class="rounded-xl border bg-card p-5 shadow-xs">
                    <ol v-if="activity.length">
                        <ActivityItem
                            v-for="(entry, index) in activity"
                            :key="entry.id"
                            :entry="entry"
                            :timezone="organization?.timezone"
                            :connected="index < activity.length - 1"
                        />
                    </ol>
                    <EmptyState
                        v-else
                        title="No activity yet"
                        description="Changes to the project, its tasks and issues show up here."
                        compact
                    />
                </div>
            </template>
        </section>

        <ProjectFormDialog
            v-if="project.can?.update"
            v-model:open="editing"
            :project="project"
            :members="members ?? []"
            :statuses="statuses"
            :priorities="priorities"
        />

        <TaskFormDialog
            v-if="canCreateTasks"
            v-model:open="creatingTask"
            :members="members ?? []"
            :projects="[{ id: project.id, name: project.name }]"
            :priorities="priorities"
            :statuses="taskStatuses"
            :defaults="{ project_id: project.id }"
        />

        <IssueFormDialog
            v-if="canCreateIssues"
            v-model:open="reportingIssue"
            :members="members ?? []"
            :projects="[{ id: project.id, name: project.name }]"
            :severities="severities"
            :defaults="{ project_id: project.id }"
        />

        <Dialog v-model:open="deleting">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete {{ project.name }}?</DialogTitle>
                    <DialogDescription>
                        This deletes the project, its
                        {{ project.tasks_count ?? 0 }} tasks and their files.
                        Issues are kept. It cannot be undone. Type the project
                        name to confirm.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="confirmDelete">
                    <label for="confirm-project-name" class="sr-only"
                        >Project name</label
                    >
                    <Input
                        id="confirm-project-name"
                        v-model="deleteForm.confirm_name"
                        autocomplete="off"
                        :placeholder="project.name"
                    />
                    <InputError :message="deleteForm.errors.confirm_name" />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="deleting = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="
                                deleteForm.processing ||
                                deleteForm.confirm_name !== project.name
                            "
                        >
                            <Spinner v-if="deleteForm.processing" />
                            Delete project
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
