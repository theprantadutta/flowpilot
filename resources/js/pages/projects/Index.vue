<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { FolderKanban, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ProjectCard from '@/components/operations/ProjectCard.vue';
import ProjectFormDialog from '@/components/operations/ProjectFormDialog.vue';
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
import { Switch } from '@/components/ui/switch';
import { useFilters } from '@/composables/useFilters';
import { cn } from '@/lib/utils';
import { index as projectsIndex } from '@/routes/projects';
import type {
    EnumOption,
    MemberOption,
    PaginatedResource,
    ProjectItem,
} from '@/types/operations';

const props = defineProps<{
    projects: PaginatedResource<ProjectItem>;
    filters: {
        q: string;
        status: string | null;
        mine: boolean;
        sort: string;
        direction: string;
    };
    counts: Record<string, number>;
    statuses: EnumOption[];
    priorities: EnumOption[];
    members?: MemberOption[];
    can: { create: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Projects', href: projectsIndex() }] });

const { filters } = useFilters(
    {
        q: props.filters.q,
        status: props.filters.status,
        mine: props.filters.mine,
        sort: props.filters.sort,
    },
    { only: ['projects', 'filters', 'counts'] },
);

const creating = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);

const activeTotal = computed(() =>
    props.statuses
        .filter((status) => status.value !== 'archived')
        .reduce((sum, status) => sum + (props.counts[status.value] ?? 0), 0),
);

const tabs = computed(() => [
    { value: null, label: 'All active', count: activeTotal.value },
    ...props.statuses.map((status) => ({
        value: status.value,
        label: status.label,
        count: props.counts[status.value] ?? 0,
    })),
]);

const sorts = [
    { value: 'updated_at', label: 'Recently updated' },
    { value: 'due_date', label: 'Due date' },
    { value: 'name', label: 'Name' },
    { value: 'priority', label: 'Priority' },
    { value: 'created_at', label: 'Newest' },
];

const hasFilters = computed(
    () => !!filters.q || !!filters.status || filters.mine,
);
</script>

<template>
    <Head title="Projects" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Projects"
            description="Every piece of work with a clear outcome, its progress and what is blocking it."
        >
            <template #actions>
                <Button v-if="can.create" @click="creating = true">
                    <Plus />
                    New project
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3">
            <div
                role="tablist"
                aria-label="Filter by status"
                class="-mx-1 flex scrollbar-thin gap-1 overflow-x-auto px-1 pb-1"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value ?? 'all'"
                    type="button"
                    role="tab"
                    :aria-selected="filters.status === tab.value"
                    :class="
                        cn(
                            'inline-flex h-8 shrink-0 items-center gap-2 rounded-lg px-3 text-sm transition-colors',
                            filters.status === tab.value
                                ? 'bg-secondary font-medium text-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )
                    "
                    @click="filters.status = tab.value"
                >
                    {{ tab.label }}
                    <span class="figures text-xs text-muted-foreground">{{
                        tab.count
                    }}</span>
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search projects"
                        aria-label="Search projects"
                        class="pl-8"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Switch
                        v-model="filters.mine"
                        aria-label="Only projects I am on"
                    />
                    Only mine
                </label>
                <div class="flex items-center gap-2 sm:ml-auto">
                    <span class="text-sm text-muted-foreground">Sort by</span>
                    <Select v-model="filters.sort">
                        <SelectTrigger class="w-44" aria-label="Sort projects"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sort in sorts"
                                :key="sort.value"
                                :value="sort.value"
                                >{{ sort.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <div
            v-if="projects.data.length > 0"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <ProjectCard
                v-for="project in projects.data"
                :key="project.id"
                :project="project"
            />
        </div>

        <div v-else class="rounded-xl border border-dashed bg-card/50">
            <EmptyState
                v-if="hasFilters"
                :icon="Search"
                title="No projects match these filters"
                description="Try a different status or search term."
            >
                <Button
                    variant="outline"
                    @click="
                        Object.assign(filters, {
                            q: '',
                            status: null,
                            mine: false,
                        })
                    "
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="FolderKanban"
                title="Start your first project"
                description="Projects bring the tasks, issues and files for one outcome together, so everyone can see progress and what is in the way."
            >
                <Button v-if="can.create" @click="creating = true">
                    <Plus />
                    New project
                </Button>
            </EmptyState>
        </div>

        <div
            v-if="projects.meta.last_page > 1"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <Pagination :paginator="projects" noun="projects" />
        </div>

        <ProjectFormDialog
            v-if="can.create"
            v-model:open="creating"
            :members="members ?? []"
            :statuses="statuses"
            :priorities="priorities"
        />
    </div>
</template>
