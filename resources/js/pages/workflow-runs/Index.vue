<script setup lang="ts">
import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { ListChecks, Search } from '@lucide/vue';
import { computed, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFilters } from '@/composables/useFilters';
import { timeAgo } from '@/lib/format';
import { formatDuration } from '@/lib/workflows';
import { index as runsIndex, show } from '@/routes/workflow-runs';
import { index as workflowsIndex } from '@/routes/workflows';
import type { EnumOption, PaginatedResource } from '@/types/operations';
import type { WorkflowRunItem } from '@/types/workflows';

const props = defineProps<{
    runs: PaginatedResource<WorkflowRunItem>;
    filters: { status: string | null; workflow: string | null };
    statuses: EnumOption[];
    workflows: { id: string; name: string }[];
    hasActiveRuns: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Workflows', href: workflowsIndex() },
        { title: 'Runs', href: runsIndex() },
    ],
});

const { filters } = useFilters(
    { status: props.filters.status, workflow: props.filters.workflow },
    { only: ['runs', 'filters', 'hasActiveRuns'] },
);

const statusModel = computed({
    get: () => filters.status ?? 'any',
    set: (value: string) => (filters.status = value === 'any' ? null : value),
});

const workflowModel = computed({
    get: () => filters.workflow ?? 'any',
    set: (value: string) => (filters.workflow = value === 'any' ? null : value),
});

// Keep the list current while runs are moving.
const { stop, start } = usePoll(
    5000,
    { only: ['runs', 'hasActiveRuns'] },
    { autoStart: props.hasActiveRuns, keepAlive: false },
);

watch(
    () => props.hasActiveRuns,
    (active) => (active ? start() : stop()),
);
</script>

<template>
    <Head title="Workflow runs" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Runs"
            description="Every time a workflow ran, what it was about and how it ended."
        />

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <Select v-model="workflowModel">
                    <SelectTrigger class="h-9 w-56" aria-label="Workflow"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">All workflows</SelectItem>
                        <SelectItem
                            v-for="workflow in workflows"
                            :key="workflow.id"
                            :value="workflow.id"
                            >{{ workflow.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="statusModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any status</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                            >{{ status.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <span
                    v-if="hasActiveRuns"
                    class="ml-auto flex items-center gap-1.5 text-xs text-muted-foreground"
                    role="status"
                >
                    <span
                        class="size-1.5 animate-pulse rounded-full bg-flow motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    Updating live
                </span>
            </div>

            <div v-if="runs.data.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-secondary/40 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">
                                Run
                            </th>
                            <th scope="col" class="px-4 py-2.5 font-medium">
                                Workflow
                            </th>
                            <th scope="col" class="px-4 py-2.5 font-medium">
                                About
                            </th>
                            <th scope="col" class="px-4 py-2.5 font-medium">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-2.5 font-medium">
                                Started
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-2.5 text-right font-medium"
                            >
                                Took
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="run in runs.data"
                            :key="run.id"
                            class="hover:bg-secondary/40"
                        >
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                <Link
                                    :href="show({ run: run.id })"
                                    class="hover:underline focus-visible:underline"
                                    >{{ run.reference }}</Link
                                >
                            </td>
                            <td class="max-w-56 truncate px-4 py-3">
                                {{ run.workflow?.name
                                }}<span
                                    v-if="run.version"
                                    class="text-muted-foreground"
                                >
                                    · v{{ run.version }}</span
                                >
                            </td>
                            <td class="max-w-72 px-4 py-3">
                                <span class="block truncate">{{
                                    run.subject?.label ??
                                    (run.starter
                                        ? `Started by ${run.starter.name}`
                                        : run.trigger.label)
                                }}</span>
                                <span
                                    v-if="run.error"
                                    class="block truncate text-xs text-danger-text"
                                    >{{ run.error }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <EnumBadge :option="run.status" />
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ timeAgo(run.created_at) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right figures whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    run.is_finished
                                        ? formatDuration(run.duration_seconds)
                                        : '…'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="filters.status || filters.workflow"
                :icon="Search"
                title="No runs match"
                description="Try another workflow or status."
            >
                <Button
                    variant="outline"
                    @click="
                        Object.assign(filters, { status: null, workflow: null })
                    "
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="ListChecks"
                title="No runs yet"
                description="Runs appear here when a published workflow starts, by hand or from an event."
            >
                <Button variant="outline" as-child
                    ><Link :href="workflowsIndex()"
                        >Go to workflows</Link
                    ></Button
                >
            </EmptyState>

            <Pagination :paginator="runs" noun="runs" />
        </div>
    </div>
</template>
