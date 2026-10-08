<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Plus, Search, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import IssueFormDialog from '@/components/operations/IssueFormDialog.vue';
import IssueTable from '@/components/operations/IssueTable.vue';
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
import { index as issuesIndex } from '@/routes/issues';
import type {
    EnumOption,
    IssueItem,
    MemberOption,
    PaginatedResource,
    ProjectOption,
} from '@/types/operations';

const props = defineProps<{
    issues: PaginatedResource<IssueItem>;
    filters: {
        q: string;
        status: string;
        severity: string | null;
        mine: boolean;
        project: string | null;
    };
    openBySeverity: Record<string, number>;
    severities: EnumOption[];
    statuses: EnumOption[];
    members?: MemberOption[];
    projects?: ProjectOption[];
    can: { create: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Issues', href: issuesIndex() }] });

const { filters } = useFilters(
    {
        q: props.filters.q,
        status: props.filters.status,
        severity: props.filters.severity,
        mine: props.filters.mine,
        project: props.filters.project,
    },
    { only: ['issues', 'filters', 'openBySeverity'] },
);

const reporting = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);

const statusModel = computed({
    get: () => filters.status,
    set: (value: string) => (filters.status = value),
});

const projectModel = computed({
    get: () => filters.project ?? 'any',
    set: (value: string) => (filters.project = value === 'any' ? null : value),
});

const severityTone: Record<string, string> = {
    danger: 'border-danger/30 bg-danger-soft/50 text-danger-text',
    warning: 'border-warning/30 bg-warning-soft/50 text-warning-text',
    info: 'border-info/30 bg-info-soft/50 text-info-text',
    neutral: 'border-border bg-secondary/50 text-neutral-text',
};

const totalOpen = computed(() =>
    Object.values(props.openBySeverity).reduce(
        (sum, count) => sum + Number(count),
        0,
    ),
);
const hasFilters = computed(
    () =>
        !!filters.q ||
        !!filters.severity ||
        filters.mine ||
        !!filters.project ||
        filters.status !== 'open',
);
</script>

<template>
    <Head title="Issues" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Issues"
            description="Problems that are slowing work down, most severe first."
        >
            <template #actions>
                <Button v-if="can.create" @click="reporting = true">
                    <Plus />
                    Report an issue
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <button
                v-for="severity in severities"
                :key="severity.value"
                type="button"
                :aria-pressed="filters.severity === severity.value"
                :class="
                    cn(
                        'flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition-[box-shadow] hover:shadow-sm',
                        severityTone[severity.tone],
                        filters.severity === severity.value &&
                            'ring-2 ring-ring',
                    )
                "
                @click="
                    filters.severity =
                        filters.severity === severity.value
                            ? null
                            : severity.value
                "
            >
                <span class="flex items-center gap-1.5 text-sm font-medium">
                    <NamedIcon :name="severity.icon" class="size-4" />
                    {{ severity.label }}
                </span>
                <span class="font-display figures text-2xl font-semibold">{{
                    openBySeverity[severity.value] ?? 0
                }}</span>
                <span class="text-xs opacity-80">open</span>
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by title or I-number"
                        aria-label="Search issues"
                        class="pl-8"
                    />
                </div>
                <Select v-model="statusModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="open">Still open</SelectItem>
                        <SelectItem value="all">All issues</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                            >{{ status.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="projectModel">
                    <SelectTrigger class="h-9 w-48" aria-label="Project"
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
                <label class="flex items-center gap-2 text-sm">
                    <Switch
                        v-model="filters.mine"
                        aria-label="Only issues assigned to me"
                    />
                    Assigned to me
                </label>
            </div>

            <IssueTable v-if="issues.data.length" :issues="issues.data" />
            <EmptyState
                v-else-if="hasFilters"
                :icon="Search"
                title="No issues match these filters"
                description="Try a different severity, status or search."
            >
                <Button
                    variant="outline"
                    @click="
                        Object.assign(filters, {
                            q: '',
                            status: 'open',
                            severity: null,
                            mine: false,
                            project: null,
                        })
                    "
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="ShieldCheck"
                :title="
                    totalOpen === 0
                        ? 'Nothing is broken right now'
                        : 'No issues here'
                "
                description="When something gets in the way of work, report it here so it is tracked until it is fixed."
            >
                <Button v-if="can.create" @click="reporting = true"
                    ><Plus />Report an issue</Button
                >
            </EmptyState>

            <Pagination :paginator="issues" noun="issues" />
        </div>

        <IssueFormDialog
            v-if="can.create"
            v-model:open="reporting"
            :members="members ?? []"
            :projects="projects ?? []"
            :severities="severities"
            :defaults="{ project_id: filters.project }"
        />
    </div>
</template>
