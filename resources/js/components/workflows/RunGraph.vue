<script setup lang="ts">
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow } from '@vue-flow/core';
import type { Edge } from '@vue-flow/core';
import { computed, markRaw, provide } from 'vue';
import WorkflowCanvasNode from '@/components/workflows/WorkflowCanvasNode.vue';
import {
    canvasContextKey,
    toFlowEdge,
    toFlowNodes,
} from '@/lib/workflowCanvas';
import type { StepState } from '@/lib/workflowCanvas';
import { fieldsFor, NODE_KINDS } from '@/lib/workflows';
import type {
    BuilderCatalog,
    DefinitionEdge,
    DefinitionNode,
    NodeConfig,
    WorkflowStepItem,
} from '@/types/workflows';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

/**
 * The version a run executed, with the path it took highlighted. Read-only.
 */
const props = defineProps<{
    nodes: DefinitionNode[];
    edges: DefinitionEdge[];
    trigger: string;
    steps: WorkflowStepItem[];
    current: string | null;
    catalog: BuilderCatalog;
}>();

const nodeTypes = Object.fromEntries(
    NODE_KINDS.map((type) => [type, markRaw(WorkflowCanvasNode)]),
);

const states = computed<Record<string, StepState>>(() => {
    const map: Record<string, StepState> = {};

    for (const step of props.steps) {
        map[step.node_id] =
            (
                {
                    completed: 'done',
                    running: 'current',
                    pending: 'current',
                    waiting: 'waiting',
                    failed: 'failed',
                    cancelled: 'cancelled',
                    skipped: 'cancelled',
                } as Record<string, StepState>
            )[step.status.value] ?? 'done';
    }

    return map;
});

/** Connections the run actually followed: from a finished step, through the path it chose. */
const taken = computed(() => {
    const keys = new Set<string>();

    for (const step of props.steps) {
        if (step.status.value === 'completed' && step.outcome) {
            keys.add(`${step.node_id}|${step.outcome}`);
        }
    }

    return keys;
});

const flowNodes = computed(() =>
    toFlowNodes(props.nodes).map((node) => ({
        ...node,
        draggable: false,
        connectable: false,
        selectable: false,
    })),
);

const flowEdges = computed<Edge[]>(() =>
    props.edges.map((edge) => ({
        ...toFlowEdge(edge, flowNodes.value, props.catalog),
        class: taken.value.has(`${edge.source}|${edge.sourceHandle}`)
            ? 'taken'
            : undefined,
        animated:
            taken.value.has(`${edge.source}|${edge.sourceHandle}`) &&
            edge.target === props.current,
    })),
);

const triggerConfig = computed<NodeConfig>(
    () =>
        props.nodes.find((node) => node.type === 'trigger')?.data.config ?? {},
);

provide(canvasContextKey, {
    catalog: props.catalog,
    trigger: computed(() => props.trigger),
    fields: computed(() =>
        fieldsFor(props.trigger, triggerConfig.value, props.catalog),
    ),
    issues: computed(() => ({})),
    states,
});
</script>

<template>
    <VueFlow
        id="workflow-run-graph"
        :nodes="flowNodes"
        :edges="flowEdges"
        :node-types="nodeTypes"
        :nodes-draggable="false"
        :nodes-connectable="false"
        :elements-selectable="false"
        :min-zoom="0.2"
        :max-zoom="1.5"
        fit-view-on-init
        class="h-full w-full"
        aria-label="The path this run took"
    >
        <Background variant="dots" :gap="20" :size="1.2" />
        <Controls :show-interactive="false" position="bottom-left" />
    </VueFlow>
</template>
