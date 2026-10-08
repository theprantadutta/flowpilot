<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheckBig, Columns3, List, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import KanbanBoard from '@/components/operations/KanbanBoard.vue';
import TaskFormDialog from '@/components/operations/TaskFormDialog.vue';
import TaskTable from '@/components/operations/TaskTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFilters } from '@/composables/useFilters';
import { useOrganization } from '@/composables/useOrganization';
import { cn } from '@/lib/utils';
import { index as tasksIndex } from '@/routes/tasks';
import type {
    EnumOption,
    MemberOption,
    PaginatedResource,
    ProjectOption,
    ResourceCollection,
    TaskItem,
} from '@/types/operations';

type Filters = {
    q: string;
    status: string | null;
    priority: string | null;
    assignee: string | null;
    project: string | null;
    due: string | null;
    sort: string;
    direction: string;
};

const props = defineProps<{
    view: 'list' | 'board';
    list?: PaginatedResource<TaskItem>;
    board?: ResourceCollection<TaskItem>;
    filters: Filters;
    statuses: EnumOption[];
    priorities: EnumOption[];
    members?: MemberOption[];
    projects?: ProjectOption[];
    counts: { mine: number; overdue: number };
    can: { create: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Tasks', href: tasksIndex() }] });

const { can: hasPermission } = useOrganization();

const { filters } = useFilters(
    {
        view: props.view,
        q: props.filters.q,
        status: props.filters.status,
        priority: props.filters.priority,
        assignee: props.filters.assignee,
        project: props.filters.project,
        due: props.filters.due,
        sort: props.filters.sort,
        direction: props.filters.direction,
    },
    { only: ['list', 'board', 'view', 'filters', 'counts'] },
);

const creating = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);
const createStatus = ref<string | undefined>(undefined);

function createIn(status: string) {
    createStatus.value = status;
    creating.value = true;
}

// Select components need strings; "any" clears a filter.
function selectModel(
    key: 'status' | 'priority' | 'assignee' | 'project' | 'due',
) {
    return computed({
        get: () => filters[key] ?? 'any',
        set: (value: string) => {
            filters[key] = value === 'any' ? null : value;
        },
    });
}

const statusFilter = selectModel('status');
const priorityFilter = selectModel('priority');
const assigneeFilter = selectModel('assignee');
const projectFilter = selectModel('project');
const dueFilter = selectModel('due');

const quickFilters = computed(() => [
    {
        label: 'My tasks',
        count: props.counts.mine,
        active: filters.assignee === 'me',
        apply: () =>
            (filters.assignee = filters.assignee === 'me' ? null : 'me'),
    },
    {
        label: 'Overdue',
        count: props.counts.overdue,
        active: filters.due === 'overdue',
        apply: () =>
            (filters.due = filters.due === 'overdue' ? null : 'overdue'),
    },
]);

const hasFilters = computed(
    () =>
        !!(
            filters.q ||
            filters.status ||
            filters.priority ||
            filters.assignee ||
            filters.project ||
            filters.due
        ),
);

function clearFilters() {
    Object.assign(filters, {
        q: '',
        status: null,
        priority: null,
        assignee: null,
        project: null,
        due: null,
    });
}

const tasks = computed(
    () => (props.view === 'board' ? props.board?.data : props.list?.data) ?? [],
);
</script>

<template>
    <Head title="Tasks" />

    <div
        class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Tasks"
            description="All work across the organization. Drag cards on the board to update their status."
        >
            <template #actions>
                <div
                    class="inline-flex rounded-lg bg-muted p-0.5"
                    role="group"
                    aria-label="View"
                >
                    <button
                        v-for="option in [
                            { id: 'list', label: 'List', icon: List },
                            { id: 'board', label: 'Board', icon: Columns3 },
                        ]"
                        :key="option.id"
                        type="button"
                        :aria-pressed="filters.view === option.id"
                        :class="
                            cn(
                                'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors',
                                filters.view === option.id
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        @click="filters.view = option.id"
                    >
                        <component
                            :is="option.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ option.label }}
                    </button>
                </div>
                <Button v-if="can.create" @click="createIn('todo')">
                    <Plus />
                    New task
                </Button>
            </template>
        </PageHeader>

        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs"
        >
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by title or T-number"
                        aria-label="Search tasks"
                        class="pl-8"
                    />
                </div>
                <button
                    v-for="quick in quickFilters"
                    :key="quick.label"
                    type="button"
                    :aria-pressed="quick.active"
                    :class="
                        cn(
                            'inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm transition-colors',
                            quick.active
                                ? 'border-primary bg-info-soft text-info-text'
                                : 'hover:bg-accent',
                        )
                    "
                    @click="quick.apply"
                >
                    {{ quick.label }}
                    <span
                        class="figures text-xs"
                        :class="quick.active ? '' : 'text-muted-foreground'"
                        >{{ quick.count }}</span
                    >
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Select v-if="filters.view === 'list'" v-model="statusFilter">
                    <SelectTrigger class="h-8 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any status</SelectItem>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="priorityFilter">
                    <SelectTrigger class="h-8 w-36" aria-label="Priority"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any priority</SelectItem>
                        <SelectItem
                            v-for="option in priorities"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="assigneeFilter">
                    <SelectTrigger class="h-8 w-44" aria-label="Assignee"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Anyone</SelectItem>
                        <SelectItem value="me">Assigned to me</SelectItem>
                        <SelectItem value="unassigned">Unassigned</SelectItem>
                        <SelectItem
                            v-for="member in members ?? []"
                            :key="member.id"
                            :value="String(member.id)"
                            >{{ member.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="projectFilter">
                    <SelectTrigger class="h-8 w-48" aria-label="Project"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">All projects</SelectItem>
                        <SelectItem
                            v-for="project in projects ?? []"
                            :key="project.id"
                            :value="project.id"
                            >{{ project.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="dueFilter">
                    <SelectTrigger class="h-8 w-36" aria-label="Due"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any due date</SelectItem>
                        <SelectItem value="overdue">Overdue</SelectItem>
                        <SelectItem value="today">Due today</SelectItem>
                        <SelectItem value="week">Due in 7 days</SelectItem>
                    </SelectContent>
                </Select>
                <template v-if="filters.view === 'list'">
                    <Select v-model="filters.sort">
                        <SelectTrigger
                            class="h-8 w-40 sm:ml-auto"
                            aria-label="Sort by"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="due_date">Due date</SelectItem>
                            <SelectItem value="priority">Priority</SelectItem>
                            <SelectItem value="updated_at"
                                >Last updated</SelectItem
                            >
                            <SelectItem value="created_at">Created</SelectItem>
                            <SelectItem value="number">Number</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Sort ${filters.direction === 'asc' ? 'descending' : 'ascending'}`"
                        @click="
                            filters.direction =
                                filters.direction === 'asc' ? 'desc' : 'asc'
                        "
                    >
                        {{
                            filters.direction === 'asc'
                                ? 'Ascending'
                                : 'Descending'
                        }}
                    </Button>
                </template>
                <Button
                    v-if="hasFilters"
                    variant="link"
                    size="sm"
                    class="text-muted-foreground"
                    @click="clearFilters"
                    >Clear filters</Button
                >
            </div>
        </div>

        <KanbanBoard
            v-if="view === 'board' && board"
            :tasks="board.data"
            :statuses="statuses"
            :can-create="can.create"
            :can-move="hasPermission('tasks.update')"
            @create="createIn"
        />

        <div
            v-else-if="view === 'list'"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <TaskTable v-if="tasks.length" :tasks="tasks" />
            <EmptyState
                v-else-if="hasFilters"
                :icon="Search"
                title="No tasks match these filters"
                description="Try widening the filters or searching for something else."
            >
                <Button variant="outline" @click="clearFilters"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="CircleCheckBig"
                title="No tasks yet"
                description="Tasks are the unit of work in FlowPilot. Create one, assign it, and track it to done."
            >
                <Button v-if="can.create" @click="createIn('todo')"
                    ><Plus />New task</Button
                >
            </EmptyState>
            <Pagination v-if="list" :paginator="list" noun="tasks" />
        </div>

        <TaskFormDialog
            v-if="can.create"
            v-model:open="creating"
            :members="members ?? []"
            :projects="projects ?? []"
            :priorities="priorities"
            :statuses="statuses"
            :defaults="{ status: createStatus, project_id: filters.project }"
        />
    </div>
</template>
