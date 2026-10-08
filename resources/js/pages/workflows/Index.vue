<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    GitBranch,
    LoaderCircle,
    Play,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import NewWorkflowDialog from '@/components/workflows/NewWorkflowDialog.vue';
import { useFilters } from '@/composables/useFilters';
import { formatNumber, plural, timeAgo } from '@/lib/format';
import { index as runsIndex } from '@/routes/workflow-runs';
import { index as workflowsIndex, show } from '@/routes/workflows';
import type { EnumOption } from '@/types/operations';
import type {
    WorkflowSummary,
    WorkflowTemplateOption,
} from '@/types/workflows';

const props = defineProps<{
    workflows: { data: WorkflowSummary[] };
    filters: { q: string; status: string | null };
    statuses: EnumOption[];
    stats: {
        active: number;
        running: number;
        completed_week: number;
        failed_week: number;
    };
    templates: WorkflowTemplateOption[];
    triggers: { value: string; label: string; description: string }[];
    can: { create: boolean };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Workflows', href: workflowsIndex() }],
});

const { filters } = useFilters(
    { q: props.filters.q, status: props.filters.status },
    { only: ['workflows', 'filters'] },
);

const statusModel = computed({
    get: () => filters.status ?? 'current',
    set: (value: string) =>
        (filters.status = value === 'current' ? null : value),
});

// Opened straight away when arriving from "Create workflow" in the command palette.
const creating = ref(
    props.can.create &&
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);
const startingTemplate = ref<string | null>(null);

function startFrom(template: string) {
    startingTemplate.value = template;
    creating.value = true;
}

const hasFilters = computed(() => !!filters.q || !!filters.status);

const cards = computed(() => [
    {
        label: 'Workflows on',
        value: props.stats.active,
        icon: Play,
        tone: 'text-flow-text bg-flow-soft',
    },
    {
        label: 'Runs in progress',
        value: props.stats.running,
        icon: LoaderCircle,
        tone: 'text-info-text bg-info-soft',
    },
    {
        label: 'Finished this week',
        value: props.stats.completed_week,
        icon: CircleCheck,
        tone: 'text-success-text bg-success-soft',
    },
    {
        label: 'Failed this week',
        value: props.stats.failed_week,
        icon: CircleX,
        tone: 'text-danger-text bg-danger-soft',
    },
]);
</script>

<template>
    <Head title="Workflows" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Workflows"
            description="Automations that move requests, tasks and issues along without anyone chasing them."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="runsIndex()">See all runs</Link>
                </Button>
                <Button v-if="can.create" @click="startFrom('blank')">
                    <Plus />
                    New workflow
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.label"
                class="flex items-center gap-3 rounded-xl border bg-card p-4 shadow-xs"
            >
                <span
                    :class="[
                        'flex size-9 items-center justify-center rounded-lg',
                        card.tone,
                    ]"
                >
                    <component
                        :is="card.icon"
                        class="size-4.5"
                        aria-hidden="true"
                    />
                </span>
                <div>
                    <p
                        class="font-display figures text-2xl leading-none font-semibold"
                    >
                        {{ formatNumber(card.value) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1 sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="filters.q"
                    type="search"
                    placeholder="Search workflows"
                    aria-label="Search workflows"
                    class="pl-8"
                />
            </div>
            <Select v-model="statusModel">
                <SelectTrigger class="h-9 w-44" aria-label="Status"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="current">All but archived</SelectItem>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status.value"
                        :value="status.value"
                        >{{ status.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <ul
            v-if="workflows.data.length"
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
        >
            <li v-for="workflow in workflows.data" :key="workflow.id">
                <Link
                    :href="show({ workflow: workflow.id })"
                    class="group flex h-full flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs transition-[border-color,box-shadow] hover:border-border-strong hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p
                                class="truncate font-semibold group-hover:text-primary"
                            >
                                {{ workflow.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ workflow.trigger.label }}
                            </p>
                        </div>
                        <EnumBadge :option="workflow.status" />
                    </div>
                    <p
                        v-if="workflow.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ workflow.description }}
                    </p>
                    <div
                        class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t pt-3 text-xs text-muted-foreground"
                    >
                        <span v-if="workflow.version" class="figures"
                            >Version {{ workflow.version }}</span
                        >
                        <span v-else>Never published</span>
                        <span
                            v-if="
                                workflow.has_unpublished_changes &&
                                workflow.version
                            "
                            class="font-medium text-warning-text"
                            >Unpublished changes</span
                        >
                        <span class="figures">{{
                            plural(workflow.runs_count ?? 0, 'run')
                        }}</span>
                        <span
                            v-if="workflow.active_runs_count"
                            class="figures font-medium text-flow-text"
                            >{{ workflow.active_runs_count }} in progress</span
                        >
                        <span
                            v-if="workflow.failed_runs_count"
                            class="figures font-medium text-danger-text"
                            >{{ workflow.failed_runs_count }} failed in 30
                            days</span
                        >
                        <span v-if="workflow.last_run_at"
                            >Last ran {{ timeAgo(workflow.last_run_at) }}</span
                        >
                    </div>
                </Link>
            </li>
        </ul>

        <EmptyState
            v-else-if="hasFilters"
            :icon="Search"
            title="No workflows match"
            description="Try another search or status."
            class="rounded-xl border bg-card"
        >
            <Button
                variant="outline"
                @click="Object.assign(filters, { q: '', status: null })"
                >Clear filters</Button
            >
        </EmptyState>

        <section
            v-else
            class="grid gap-4 rounded-xl border bg-card p-6 shadow-xs"
        >
            <EmptyState
                compact
                :icon="GitBranch"
                title="Automate your first process"
                description="Pick a template to start from. Each one opens in the builder, ready to adjust before you switch it on."
            />
            <ul
                v-if="can.create"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <li
                    v-for="template in templates.filter(
                        (item) => item.key !== 'blank',
                    )"
                    :key="template.key"
                >
                    <button
                        type="button"
                        class="flex h-full w-full flex-col gap-1 rounded-xl border p-4 text-left transition-[border-color] hover:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="startFrom(template.key)"
                    >
                        <span class="text-sm font-semibold">{{
                            template.name
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            template.description
                        }}</span>
                    </button>
                </li>
            </ul>
        </section>

        <NewWorkflowDialog
            v-if="can.create"
            v-model:open="creating"
            :templates="templates"
            :triggers="triggers"
            :initial-template="startingTemplate"
        />
    </div>
</template>
