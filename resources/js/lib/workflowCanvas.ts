import { MarkerType } from '@vue-flow/core';
import type { Edge, Node } from '@vue-flow/core';
import type { ComputedRef, InjectionKey } from 'vue';
import { handlesFor } from '@/lib/workflows';
import type {
    BuilderCatalog,
    DefinitionEdge,
    DefinitionNode,
    NodeKind,
    WorkflowField,
    WorkflowNodeData,
} from '@/types/workflows';

/** Data type used when a step is dragged from the palette onto the canvas. */
export const STEP_DRAG_TYPE = 'application/x-flowpilot-step';

/** How a step fared in a run, for the read-only run graph. */
export type StepState = 'done' | 'current' | 'waiting' | 'failed' | 'cancelled';

/**
 * What every node on a canvas needs to describe itself, shared through
 * provide/inject so node data stays exactly what is saved.
 */
export type CanvasContext = {
    catalog: BuilderCatalog;
    trigger: ComputedRef<string>;
    fields: ComputedRef<WorkflowField[]>;
    issues: ComputedRef<Record<string, string[]>>;
    states?: ComputedRef<Record<string, StepState>>;
};

export const canvasContextKey: InjectionKey<CanvasContext> =
    Symbol('workflow-canvas');

export type FlowNode = Node<WorkflowNodeData>;

export function toFlowNodes(nodes: DefinitionNode[]): FlowNode[] {
    return nodes.map((node) => ({
        id: node.id,
        type: node.type,
        position: {
            x: Number(node.position?.x ?? 0),
            y: Number(node.position?.y ?? 0),
        },
        data: {
            label: node.data?.label ?? '',
            config: (node.data?.config ?? {}) as WorkflowNodeData['config'],
        },
        deletable: node.type !== 'trigger',
    }));
}

export function edgeLabel(
    edge: Pick<DefinitionEdge, 'source' | 'sourceHandle'>,
    nodes: FlowNode[],
    catalog: BuilderCatalog,
): string | undefined {
    if (!edge.sourceHandle || edge.sourceHandle === 'next') {
        return undefined;
    }

    const source = nodes.find((node) => node.id === edge.source);

    if (!source) {
        return undefined;
    }

    return handlesFor(
        source.type as NodeKind,
        source.data?.config ?? {},
        catalog,
    ).find((handle) => handle.id === edge.sourceHandle)?.label;
}

export function toFlowEdge(
    edge: DefinitionEdge,
    nodes: FlowNode[],
    catalog: BuilderCatalog,
): Edge {
    return {
        id: edge.id,
        source: edge.source,
        target: edge.target,
        sourceHandle: edge.sourceHandle,
        type: 'smoothstep',
        label: edgeLabel(edge, nodes, catalog),
        markerEnd: MarkerType.ArrowClosed,
    };
}

export function toDefinition(
    nodes: FlowNode[],
    edges: Edge[],
): {
    nodes: DefinitionNode[];
    edges: DefinitionEdge[];
} {
    return {
        nodes: nodes.map((node) => ({
            id: node.id,
            type: node.type as NodeKind,
            position: {
                x: Math.round(node.position.x),
                y: Math.round(node.position.y),
            },
            data: {
                label: node.data?.label ?? '',
                config: node.data?.config ?? {},
            },
        })),
        edges: edges.map((edge) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            sourceHandle: edge.sourceHandle ?? 'next',
        })),
    };
}
