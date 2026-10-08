<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/vue3';
import {
    CircleX,
    ExternalLink,
    GitBranch,
    RotateCw,
    Square,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import RunGraph from '@/components/workflows/RunGraph.vue';
import RunTimeline from '@/components/workflows/RunTimeline.vue';
import { formatDateTime } from '@/lib/format';
import { formatDuration } from '@/lib/workflows';
import {
    cancel,
    index as runsIndex,
    retry,
    show,
} from '@/routes/workflow-runs';
import {
    index as workflowsIndex,
    show as workflowShow,
} from '@/routes/workflows';
import type {
    BuilderCatalog,
    DefinitionEdge,
    DefinitionNode,
    WorkflowRunItem,
    WorkflowStepItem,
} from '@/types/workflows';

const props = defineProps<{
    run: WorkflowRunItem;
    steps: WorkflowStepItem[];
    graph: {
        nodes: DefinitionNode[];
        edges: DefinitionEdge[];
        trigger: string;
        current: string | null;
    };
    input: { key: string; label: string; value: string }[];
    catalog: BuilderCatalog;
    can: { cancel: boolean; retry: boolean; editWorkflow: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Workflows', href: workflowsIndex() },
        { title: 'Runs', href: runsIndex() },
        { title: props.run.reference, href: show({ run: props.run.id }) },
    ],
});

// Follow the run live while it is still moving.
const { stop, start } = usePoll(
    3000,
    { only: ['run', 'steps', 'graph', 'can'] },
    { autoStart: !props.run.is_finished, keepAlive: false },
);

watch(
    () => props.run.is_finished,
    (finished) => (finished ? stop() : start()),
);

const cancelling = ref(false);
const processing = ref(false);

function confirmCancel() {
    router.post(
        cancel({ run: props.run.id }).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (cancelling.value = false),
        },
    );
}

function tryAgain() {
    router.post(
        retry({ run: props.run.id }).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => start(),
        },
    );
}

const facts = computed(() =>
    [
        {
            label: 'Workflow',
            value: props.run.workflow?.name ?? '',
            href: props.run.workflow
                ? workflowShow({ workflow: props.run.workflow.id }).url
                : null,
        },
        {
            label: 'Version',
            value: props.run.version ? `Version ${props.run.version}` : '',
        },
        { label: 'Trigger', value: props.run.trigger.label },
        {
            label: 'Started by',
            value:
                props.run.starter?.name ??
                (props.run.subject ? 'The event' : ''),
        },
        {
            label: 'Started',
            value: props.run.started_at
                ? formatDateTime(props.run.started_at)
                : formatDateTime(props.run.created_at),
        },
        {
            label: 'Took',
            value: props.run.is_finished
                ? formatDuration(props.run.duration_seconds)
                : 'Still running',
        },
    ].filter((fact) => fact.value !== ''),
);
</script>

<template>
    <Head :title="`${run.reference} · ${run.workflow?.name ?? 'Run'}`" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-display text-2xl font-semibold">
                        {{ run.reference }}
                    </h1>
                    <EnumBadge :option="run.status" />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ run.workflow?.name }}
                    <template v-if="run.subject">
                        · about
                        <Link
                            v-if="run.subject.url"
                            :href="run.subject.url"
                            class="font-medium text-foreground underline-offset-4 hover:underline"
                            >{{ run.subject.label }}</Link
                        >
                        <span v-else>{{ run.subject.label }}</span>
                    </template>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.editWorkflow && run.workflow"
                    variant="outline"
                    as-child
                >
                    <Link :href="workflowShow({ workflow: run.workflow.id })"
                        ><GitBranch />Open builder</Link
                    >
                </Button>
                <Button
                    v-if="can.retry"
                    :disabled="processing"
                    @click="tryAgain"
                    ><RotateCw />Try again</Button
                >
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    :disabled="processing"
                    @click="cancelling = true"
                    ><Square />Cancel run</Button
                >
            </div>
        </header>

        <div
            v-if="run.status.value === 'failed' && run.error"
            role="alert"
            class="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-soft/60 p-4 text-danger-text"
        >
            <CircleX class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-medium">This run failed</p>
                <p class="text-sm">{{ run.error }}</p>
                <p v-if="can.retry" class="mt-1 text-sm">
                    Fix the cause, then try again: the run picks up from the
                    step that failed.
                </p>
            </div>
        </div>

        <dl
            class="grid grid-cols-2 gap-3 rounded-xl border bg-card p-4 shadow-xs sm:grid-cols-3 lg:grid-cols-6"
        >
            <div v-for="fact in facts" :key="fact.label" class="min-w-0">
                <dt class="text-xs text-muted-foreground">{{ fact.label }}</dt>
                <dd class="truncate text-sm font-medium">
                    <Link
                        v-if="'href' in fact && fact.href"
                        :href="fact.href"
                        class="hover:underline"
                        >{{ fact.value }}</Link
                    >
                    <template v-else>{{ fact.value }}</template>
                </dd>
            </div>
        </dl>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <section
                class="grid content-start gap-4"
                aria-labelledby="run-steps"
            >
                <div
                    v-if="input.length"
                    class="rounded-xl border bg-card p-4 shadow-xs"
                >
                    <h2 class="text-sm font-semibold">Details entered</h2>
                    <dl
                        class="mt-3 grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]"
                    >
                        <template v-for="item in input" :key="item.key">
                            <dt class="text-muted-foreground">
                                {{ item.label }}
                            </dt>
                            <dd class="break-words">{{ item.value || '—' }}</dd>
                        </template>
                    </dl>
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-xs">
                    <h2 id="run-steps" class="mb-4 text-sm font-semibold">
                        Steps
                    </h2>
                    <RunTimeline v-if="steps.length" :steps="steps" />
                    <div v-else class="grid gap-3">
                        <Skeleton
                            v-for="index in 3"
                            :key="index"
                            class="h-14 w-full rounded-lg"
                        />
                        <p class="text-sm text-muted-foreground">
                            The run is queued and will start in a moment.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="h-[28rem] overflow-hidden rounded-xl border bg-secondary/30 shadow-xs lg:sticky lg:top-4 lg:h-[calc(100dvh-8rem)]"
                aria-label="Path through the workflow"
            >
                <RunGraph
                    :nodes="graph.nodes"
                    :edges="graph.edges"
                    :trigger="graph.trigger"
                    :steps="steps"
                    :current="graph.current"
                    :catalog="catalog"
                />
            </section>
        </div>

        <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <ExternalLink class="size-3.5" aria-hidden="true" />
            Every step is also recorded in the activity log.
        </p>
    </div>

    <ConfirmDialog
        v-model:open="cancelling"
        :title="`Cancel ${run.reference}?`"
        description="Steps that have not run yet will not run. Steps already done are not undone."
        confirm-label="Cancel run"
        destructive
        :processing="processing"
        @confirm="confirmCancel"
    />
</template>
