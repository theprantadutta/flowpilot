<script setup lang="ts">
import { Deferred, Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MarkerType, Panel, VueFlow, useVueFlow } from '@vue-flow/core';
import type { Connection, Edge } from '@vue-flow/core';
import { MiniMap } from '@vue-flow/minimap';
import { useMediaQuery } from '@vueuse/core';
import {
    Archive,
    CircleAlert,
    CircleCheck,
    Cloud,
    CloudAlert,
    EllipsisVertical,
    History,
    LoaderCircle,
    Pause,
    Pencil,
    Play,
    Rocket,
    Trash2,
    TriangleAlert,
    Unplug,
} from '@lucide/vue';
import {
    computed,
    markRaw,
    nextTick,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import NodeDetails from '@/components/workflows/NodeDetails.vue';
import NodeInspector from '@/components/workflows/NodeInspector.vue';
import NodePalette from '@/components/workflows/NodePalette.vue';
import PublishDialog from '@/components/workflows/PublishDialog.vue';
import StartRunDialog from '@/components/workflows/StartRunDialog.vue';
import VersionsSheet from '@/components/workflows/VersionsSheet.vue';
import WorkflowCanvasNode from '@/components/workflows/WorkflowCanvasNode.vue';
import { timeAgo } from '@/lib/format';
import {
    STEP_DRAG_TYPE,
    canvasContextKey,
    edgeLabel,
    toDefinition,
    toFlowEdge,
    toFlowNodes,
} from '@/lib/workflowCanvas';
import type { FlowNode } from '@/lib/workflowCanvas';
import {
    defaultConfig,
    fieldsFor,
    handlesFor,
    NODE_KINDS,
    shortId,
    variablesFor,
} from '@/lib/workflows';
import { show as runShow } from '@/routes/workflow-runs';
import {
    destroy,
    index as workflowsIndex,
    show,
    update,
} from '@/routes/workflows';
import { update as saveDraftRoute } from '@/routes/workflows/draft';
import { update as statusRoute } from '@/routes/workflows/status';
import type {
    BuilderCatalog,
    DefinitionNode,
    ManualInput,
    NodeConfig,
    NodeKind,
    ValidationIssue,
    WorkflowDraft,
    WorkflowRunItem,
    WorkflowSummary,
    WorkflowVersionItem,
} from '@/types/workflows';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

const props = defineProps<{
    workflow: WorkflowSummary;
    draft: WorkflowDraft;
    issues: ValidationIssue[];
    catalog: BuilderCatalog;
    versions: WorkflowVersionItem[];
    recentRuns?: { data: WorkflowRunItem[] };
    startForm: { inputs: ManualInput[] } | null;
    can: {
        update: boolean;
        publish: boolean;
        execute: boolean;
        delete: boolean;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Workflows', href: workflowsIndex() },
        {
            title: props.workflow.name,
            href: show({ workflow: props.workflow.id }),
        },
    ],
});

const FLOW_ID = 'workflow-builder';
const compact = useMediaQuery('(max-width: 1023px)');
// The overview map only earns its space when the canvas is wide.
const roomy = useMediaQuery('(min-width: 1536px)');
const editable = computed(() => props.can.update && !compact.value);

const nodeTypes = Object.fromEntries(
    NODE_KINDS.map((type) => [type, markRaw(WorkflowCanvasNode)]),
);

const {
    addNodes,
    addEdges,
    removeNodes,
    removeEdges,
    updateNodeData,
    findNode,
    getNodes,
    getEdges,
    setNodes,
    setEdges,
    screenToFlowCoordinate,
    fitView,
    addSelectedNodes,
    removeSelectedElements,
    onConnect,
    onNodeClick,
    onEdgeClick,
    onPaneClick,
} = useVueFlow(FLOW_ID);

const trigger = ref(props.draft.trigger);
const initialNodes = toFlowNodes(props.draft.nodes);
const nodes = ref<FlowNode[]>(initialNodes);
const edges = ref<Edge[]>(
    props.draft.edges.map((edge) =>
        toFlowEdge(edge, initialNodes, props.catalog),
    ),
);

const selectedId = ref<string | null>(null);
const selectedEdgeId = ref<string | null>(null);
const detailsOpen = ref(false);

/* ---------------------------------------------------------------- context */

const triggerNode = computed(() =>
    getNodes.value.find((node) => node.type === 'trigger'),
);
const triggerConfig = computed<NodeConfig>(
    () => (triggerNode.value?.data?.config as NodeConfig | undefined) ?? {},
);
const fields = computed(() =>
    fieldsFor(trigger.value, triggerConfig.value, props.catalog),
);
const subjectType = computed(
    () =>
        props.catalog.triggers.find((item) => item.value === trigger.value)
            ?.subject ?? null,
);

const issuesByNode = computed<Record<string, string[]>>(() => {
    const map: Record<string, string[]> = {};

    for (const issue of props.issues) {
        if (issue.node) {
            (map[issue.node] ??= []).push(issue.message);
        }
    }

    return map;
});
const generalIssues = computed(() =>
    props.issues.filter((issue) => !issue.node),
);

provide(canvasContextKey, {
    catalog: props.catalog,
    trigger: computed(() => trigger.value),
    fields,
    issues: issuesByNode,
});

const selectedNode = computed(() =>
    selectedId.value
        ? (findNode(selectedId.value) as FlowNode | undefined)
        : undefined,
);
const selectedDefinition = computed<DefinitionNode | null>(() => {
    const node = selectedNode.value;

    return node
        ? {
              id: node.id,
              type: node.type as NodeKind,
              position: node.position,
              data: {
                  label: node.data?.label ?? '',
                  config: node.data?.config ?? {},
              },
          }
        : null;
});

/** Steps that come before the selected one, whose outputs it can use. */
const upstream = computed<DefinitionNode[]>(() => {
    if (!selectedId.value) {
        return [];
    }

    const seen = new Set<string>();
    const queue = [selectedId.value];

    while (queue.length) {
        const current = queue.shift() as string;

        for (const edge of getEdges.value) {
            if (edge.target === current && !seen.has(edge.source)) {
                seen.add(edge.source);
                queue.push(edge.source);
            }
        }
    }

    return toDefinition(getNodes.value as FlowNode[], []).nodes.filter((node) =>
        seen.has(node.id),
    );
});

const variables = computed(() =>
    variablesFor(
        trigger.value,
        triggerConfig.value,
        props.catalog,
        upstream.value,
    ),
);

/* ------------------------------------------------------------------ saving */

type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

const saveState = ref<SaveState>('saved');
const saveError = ref<string | null>(null);
const serialized = computed(() =>
    JSON.stringify({
        trigger: trigger.value,
        definition: toDefinition(getNodes.value as FlowNode[], getEdges.value),
    }),
);
let lastSaved = '';
let saveTimer: ReturnType<typeof setTimeout> | null = null;
let inFlight: Promise<void> | null = null;
let pendingVisit: string | null = null;

onMounted(async () => {
    await nextTick();
    lastSaved = serialized.value;
});

watch(serialized, (value) => {
    if (!props.can.update || value === lastSaved) {
        return;
    }

    saveState.value = 'dirty';

    if (saveTimer) {
        clearTimeout(saveTimer);
    }

    saveTimer = setTimeout(() => void save(), 1200);
});

function save(): Promise<void> {
    if (saveTimer) {
        clearTimeout(saveTimer);
        saveTimer = null;
    }

    if (inFlight) {
        return inFlight.then(() => save());
    }

    const payload = serialized.value;

    if (payload === lastSaved) {
        saveState.value = 'saved';

        return Promise.resolve();
    }

    saveState.value = 'saving';

    inFlight = new Promise<void>((resolve) => {
        router.put(
            saveDraftRoute({ workflow: props.workflow.id }).url,
            JSON.parse(payload),
            {
                preserveState: true,
                preserveScroll: true,
                only: ['issues', 'workflow'],
                onSuccess: () => {
                    lastSaved = payload;
                    saveError.value = null;
                    saveState.value =
                        serialized.value === payload ? 'saved' : 'dirty';
                },
                onError: (errors) => {
                    saveState.value = 'error';
                    saveError.value =
                        Object.values(errors)[0] ??
                        'The draft could not be saved.';
                },
                onNetworkError: () => {
                    saveState.value = 'error';
                    saveError.value =
                        'You seem to be offline. Changes will save when you reconnect.';
                },
                onFinish: () => {
                    inFlight = null;
                    resolve();

                    if (saveState.value === 'dirty') {
                        saveTimer = setTimeout(() => void save(), 600);
                    } else if (pendingVisit && saveState.value === 'saved') {
                        const url = pendingVisit;
                        pendingVisit = null;
                        router.visit(url);
                    }
                },
            },
        );
    });

    return inFlight;
}

// Save before leaving the builder, then carry on to where the person was going.
const stopBefore = router.on('before', (event) => {
    const visit = event.detail.visit;

    if (
        visit.method !== 'get' ||
        !props.can.update ||
        serialized.value === lastSaved ||
        saveState.value === 'error'
    ) {
        return;
    }

    if (visit.url.pathname === window.location.pathname) {
        return;
    }

    pendingVisit = visit.url.href;
    void save();

    return false;
});

function warnBeforeUnload(event: BeforeUnloadEvent) {
    if (props.can.update && serialized.value !== lastSaved) {
        event.preventDefault();
    }
}

onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload));
onBeforeUnmount(() => {
    stopBefore();
    window.removeEventListener('beforeunload', warnBeforeUnload);

    if (saveTimer) {
        clearTimeout(saveTimer);
    }
});

/* ------------------------------------------------------------- editing */

const isValidConnection = (connection: Connection): boolean => {
    if (!editable.value || connection.source === connection.target) {
        return false;
    }

    const target = findNode(connection.target);

    if (!target || target.type === 'trigger') {
        return false;
    }

    // Refuse anything that would loop back: the target must not already reach the source.
    const seen = new Set<string>([connection.target]);
    const queue = [connection.target];

    while (queue.length) {
        const current = queue.shift() as string;

        if (current === connection.source) {
            return false;
        }

        for (const edge of getEdges.value) {
            if (edge.source === current && !seen.has(edge.target)) {
                seen.add(edge.target);
                queue.push(edge.target);
            }
        }
    }

    return true;
};

function connect(source: string, target: string, handle: string) {
    const existing = getEdges.value.filter(
        (edge) =>
            edge.source === source && (edge.sourceHandle ?? 'next') === handle,
    );

    if (existing.length) {
        removeEdges(existing.map((edge) => edge.id));
    }

    addEdges([
        toFlowEdge(
            {
                id: `${source}-${handle}-${target}`,
                source,
                target,
                sourceHandle: handle,
            },
            getNodes.value as FlowNode[],
            props.catalog,
        ),
    ]);
}

onConnect((connection) => {
    if (isValidConnection(connection)) {
        connect(
            connection.source,
            connection.target,
            connection.sourceHandle ?? 'next',
        );
    }
});

onNodeClick(({ node }) => {
    selectedId.value = node.id;
    selectedEdgeId.value = null;
    detailsOpen.value = compact.value;
});

onEdgeClick(({ edge }) => {
    selectedEdgeId.value = edge.id;
    selectedId.value = null;
});

onPaneClick(() => {
    selectedId.value = null;
    selectedEdgeId.value = null;
});

function freeHandle(node: FlowNode): string | null {
    const used = new Set(
        getEdges.value
            .filter((edge) => edge.source === node.id)
            .map((edge) => edge.sourceHandle ?? 'next'),
    );

    return (
        handlesFor(
            node.type as NodeKind,
            node.data?.config ?? {},
            props.catalog,
        ).find((handle) => !used.has(handle.id))?.id ?? null
    );
}

function addStep(type: NodeKind, position?: { x: number; y: number }) {
    if (!editable.value) {
        return;
    }

    const anchor = position
        ? null
        : ((selectedNode.value as FlowNode | undefined) ??
          [...(getNodes.value as FlowNode[])].sort(
              (a, b) => b.position.y - a.position.y,
          )[0]);
    const option = props.catalog.nodeTypes.find((item) => item.value === type);
    const id = shortId(type);
    const handle = anchor ? freeHandle(anchor) : null;
    const siblings = anchor
        ? getEdges.value.filter((edge) => edge.source === anchor.id).length
        : 0;

    addNodes([
        {
            id,
            type,
            position: position ?? {
                x:
                    (anchor?.position.x ?? 0) +
                    (siblings > 0 && handle ? siblings * 300 : 0),
                y: (anchor?.position.y ?? 0) + 170,
            },
            data: { label: option?.label ?? type, config: defaultConfig(type) },
        },
    ]);

    if (anchor && handle) {
        connect(anchor.id, id, handle);
    }

    selectedId.value = id;
    selectedEdgeId.value = null;
    removeSelectedElements();
    void nextTick(() => {
        const node = findNode(id);

        if (node) {
            addSelectedNodes([node]);
        }
    });
}

function onDrop(event: DragEvent) {
    const type = event.dataTransfer?.getData(STEP_DRAG_TYPE) as
        | NodeKind
        | undefined;

    if (!type) {
        return;
    }

    const position = screenToFlowCoordinate({
        x: event.clientX,
        y: event.clientY,
    });
    addStep(type, { x: position.x - 128, y: position.y - 30 });
}

function updateLabel(label: string) {
    if (selectedId.value) {
        updateNodeData(selectedId.value, { label });
    }
}

function updateConfig(config: NodeConfig) {
    const id = selectedId.value;

    if (!id) {
        return;
    }

    updateNodeData(id, { config });

    // Paths that no longer exist (a removed branch case) lose their connections; labels follow renames.
    const node = findNode(id) as FlowNode | undefined;

    if (node) {
        const handles = handlesFor(
            node.type as NodeKind,
            config,
            props.catalog,
        ).map((handle) => handle.id);
        const orphaned = getEdges.value.filter(
            (edge) =>
                edge.source === id &&
                !handles.includes(edge.sourceHandle ?? 'next'),
        );

        if (orphaned.length) {
            removeEdges(orphaned.map((edge) => edge.id));
        }

        for (const edge of getEdges.value.filter(
            (item) => item.source === id,
        )) {
            edge.label = edgeLabel(
                {
                    source: edge.source,
                    sourceHandle: edge.sourceHandle ?? 'next',
                },
                getNodes.value as FlowNode[],
                props.catalog,
            );
        }
    }
}

function changeTrigger(value: string) {
    trigger.value = value;

    if (triggerNode.value) {
        updateNodeData(triggerNode.value.id, {
            config: value === 'manual' ? { inputs: [] } : {},
        });
    }
}

/** A detail's key changed: point rules, people and messages at the new key. */
function renameInput(from: string, to: string) {
    const pattern = new RegExp(`\\binput\\.${from}\\b`, 'g');

    for (const node of getNodes.value as FlowNode[]) {
        if (node.type === 'trigger') {
            continue;
        }

        const before = JSON.stringify(node.data?.config ?? {});
        const after = before.replace(pattern, `input.${to}`);

        if (after !== before) {
            updateNodeData(node.id, {
                config: JSON.parse(after) as NodeConfig,
            });
        }
    }
}

function removeSelected() {
    if (selectedId.value && findNode(selectedId.value)?.type !== 'trigger') {
        removeNodes([selectedId.value]);
        selectedId.value = null;
    }
}

function removeSelectedEdge() {
    if (selectedEdgeId.value) {
        removeEdges([selectedEdgeId.value]);
        selectedEdgeId.value = null;
    }
}

/* ----------------------------------------------------------- workflow */

const editingName = ref(false);
const nameDraft = ref(props.workflow.name);
const publishing = ref(false);
const versionsOpen = ref(false);
const starting = ref(false);
const deleting = ref(false);
const deleteProcessing = ref(false);

function saveName() {
    editingName.value = false;
    const name = nameDraft.value.trim();

    if (name.length < 2 || name === props.workflow.name) {
        nameDraft.value = props.workflow.name;

        return;
    }

    router.patch(
        update({ workflow: props.workflow.id }).url,
        { name },
        { preserveState: true, preserveScroll: true, only: ['workflow'] },
    );
}

function setStatus(status: 'active' | 'paused' | 'archived') {
    router.patch(
        statusRoute({ workflow: props.workflow.id }).url,
        { status },
        { preserveState: true, preserveScroll: true, only: ['workflow'] },
    );
}

function confirmDelete() {
    router.delete(destroy({ workflow: props.workflow.id }).url, {
        onStart: () => (deleteProcessing.value = true),
        onFinish: () => (deleteProcessing.value = false),
        onSuccess: () => (deleting.value = false),
    });
}

/** After a version is copied into the draft, load it onto the canvas. */
function reloadDraft() {
    router.reload({
        only: ['draft', 'issues', 'workflow'],
        onSuccess: () => {
            trigger.value = props.draft.trigger;
            const fresh = toFlowNodes(props.draft.nodes);
            setNodes(fresh);
            setEdges(
                props.draft.edges.map((edge) =>
                    toFlowEdge(edge, fresh, props.catalog),
                ),
            );
            selectedId.value = null;
            void nextTick(() => {
                lastSaved = serialized.value;
                saveState.value = 'saved';
                void fitView({ padding: 0.2 });
            });
        },
    });
}

const nextVersion = computed(() => (props.versions[0]?.version ?? 0) + 1);
const canPublish = computed(() => props.can.publish && !compact.value);
const issueCount = computed(() => props.issues.length);
</script>

<template>
    <Head :title="`${workflow.name} · Builder`" />

    <div
        class="flex h-[calc(100dvh-3.5rem)] min-h-[32rem] flex-col md:h-[calc(100dvh-4.5rem)]"
    >
        <!-- Toolbar -->
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b bg-background px-4 py-2.5 sm:px-6"
        >
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <form
                    v-if="editingName"
                    class="min-w-0 flex-1 sm:max-w-sm"
                    @submit.prevent="saveName"
                >
                    <Input
                        v-model="nameDraft"
                        v-focus
                        aria-label="Workflow name"
                        maxlength="120"
                        class="h-8"
                        @blur="saveName"
                        @keydown.esc="
                            editingName = false;
                            nameDraft = workflow.name;
                        "
                    />
                </form>
                <template v-else>
                    <h1 class="truncate font-display text-lg font-semibold">
                        {{ workflow.name }}
                    </h1>
                    <Button
                        v-if="editable"
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0"
                        aria-label="Rename workflow"
                        @click="editingName = true"
                    >
                        <Pencil class="size-3.5" />
                    </Button>
                </template>
                <EnumBadge :option="workflow.status" class="shrink-0" />
                <span
                    class="hidden shrink-0 text-xs text-muted-foreground sm:inline"
                >
                    <template v-if="workflow.version"
                        >Version {{ workflow.version }} live</template
                    >
                    <template v-else>Not published</template>
                    <template
                        v-if="
                            workflow.version && workflow.has_unpublished_changes
                        "
                    >
                        · draft has changes</template
                    >
                </span>
            </div>

            <div class="flex items-center gap-2">
                <span
                    v-if="can.update && !compact"
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    role="status"
                    aria-live="polite"
                >
                    <template v-if="saveState === 'saving'"
                        ><LoaderCircle
                            class="size-3.5 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />Saving</template
                    >
                    <template v-else-if="saveState === 'dirty'"
                        ><Cloud
                            class="size-3.5"
                            aria-hidden="true"
                        />Unsaved</template
                    >
                    <template v-else-if="saveState === 'error'"
                        ><CloudAlert
                            class="size-3.5 text-danger-text"
                            aria-hidden="true"
                        /><span class="text-danger-text">{{
                            saveError
                        }}</span></template
                    >
                    <template v-else
                        ><CircleCheck
                            class="size-3.5 text-success-text"
                            aria-hidden="true"
                        />Draft saved</template
                    >
                </span>

                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            :class="
                                issueCount
                                    ? 'text-warning-text'
                                    : 'text-success-text'
                            "
                        >
                            <TriangleAlert v-if="issueCount" />
                            <CircleCheck v-else />
                            {{ issueCount ? `${issueCount} to fix` : 'Ready' }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent align="end" class="w-80">
                        <p class="text-sm font-medium">
                            {{
                                issueCount
                                    ? 'Before publishing'
                                    : 'Ready to publish'
                            }}
                        </p>
                        <p
                            v-if="!issueCount"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Every step is connected and set up.
                        </p>
                        <ul
                            v-else
                            class="mt-2 grid max-h-72 gap-1.5 overflow-y-auto"
                        >
                            <li v-for="(issue, index) in issues" :key="index">
                                <button
                                    type="button"
                                    class="w-full rounded-md px-2 py-1.5 text-left text-xs hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :disabled="!issue.node"
                                    @click="
                                        issue.node &&
                                        ((selectedId = issue.node),
                                        (detailsOpen = compact))
                                    "
                                >
                                    <span
                                        v-if="issue.node"
                                        class="block font-medium"
                                        >{{
                                            findNode(issue.node)?.data?.label ??
                                            'A step'
                                        }}</span
                                    >
                                    {{ issue.message }}
                                </button>
                            </li>
                        </ul>
                    </PopoverContent>
                </Popover>

                <Button
                    variant="outline"
                    size="sm"
                    @click="versionsOpen = true"
                >
                    <History />
                    <span class="hidden sm:inline">Versions</span>
                </Button>

                <Button
                    v-if="can.execute && startForm"
                    variant="outline"
                    size="sm"
                    @click="starting = true"
                >
                    <Play />
                    Start run
                </Button>

                <Button v-if="canPublish" size="sm" @click="publishing = true">
                    <Rocket />
                    Publish
                </Button>

                <DropdownMenu v-if="can.publish || can.delete">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            aria-label="More workflow actions"
                        >
                            <EllipsisVertical class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <template v-if="can.publish">
                            <DropdownMenuItem
                                v-if="workflow.status.value === 'active'"
                                @select="setStatus('paused')"
                                ><Pause />Pause workflow</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                v-else-if="workflow.version"
                                @select="setStatus('active')"
                                ><Play />Turn workflow on</DropdownMenuItem
                            >
                            <DropdownMenuItem
                                v-if="workflow.status.value !== 'archived'"
                                @select="setStatus('archived')"
                                ><Archive />Archive</DropdownMenuItem
                            >
                        </template>
                        <template v-if="can.delete">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-danger-text"
                                @select="deleting = true"
                                ><Trash2 />Delete workflow</DropdownMenuItem
                            >
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div
            v-if="compact"
            class="flex items-center gap-2 border-b bg-info-soft/60 px-4 py-2 text-xs text-info-text"
            role="note"
        >
            <CircleAlert class="size-4 shrink-0" aria-hidden="true" />
            Inspection mode: tap a step to see what it does. Editing needs a
            larger screen.
        </div>

        <div class="flex min-h-0 flex-1">
            <!-- Palette -->
            <aside
                v-if="editable"
                class="hidden w-64 shrink-0 scrollbar-thin overflow-y-auto border-r bg-background p-3 lg:block"
                aria-label="Add steps"
            >
                <p class="px-2 pb-2 text-xs font-medium text-muted-foreground">
                    Add a step
                </p>
                <NodePalette :types="catalog.nodeTypes" @add="addStep" />
                <p class="px-2 pt-3 text-xs text-muted-foreground">
                    Click to add after the selected step, or drag onto the
                    canvas. Drag from a dot to connect steps.
                </p>
            </aside>

            <!-- Canvas -->
            <div
                class="relative min-w-0 flex-1 bg-secondary/30"
                @dragover.prevent
                @drop.prevent="onDrop"
            >
                <VueFlow
                    :id="FLOW_ID"
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    :node-types="nodeTypes"
                    :nodes-draggable="editable"
                    :nodes-connectable="editable"
                    :edges-updatable="false"
                    :elements-selectable="true"
                    :delete-key-code="editable ? ['Backspace', 'Delete'] : null"
                    :is-valid-connection="isValidConnection"
                    :default-edge-options="{
                        type: 'smoothstep',
                        markerEnd: MarkerType.ArrowClosed,
                    }"
                    :min-zoom="0.25"
                    :max-zoom="1.75"
                    :snap-to-grid="true"
                    :snap-grid="[10, 10]"
                    fit-view-on-init
                    class="h-full w-full"
                    aria-label="Workflow canvas"
                >
                    <Background variant="dots" :gap="20" :size="1.2" />
                    <Controls
                        :show-interactive="false"
                        position="bottom-left"
                    />
                    <MiniMap
                        v-if="roomy"
                        pannable
                        zoomable
                        position="bottom-right"
                        :width="150"
                        :height="96"
                        :node-border-radius="12"
                        aria-label="Overview of the canvas"
                    />
                    <Panel
                        v-if="selectedEdgeId && editable"
                        position="top-center"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            class="bg-card"
                            @click="removeSelectedEdge"
                        >
                            <Unplug />
                            Remove connection
                        </Button>
                    </Panel>
                </VueFlow>

                <div
                    v-if="generalIssues.length && !compact"
                    class="pointer-events-none absolute top-3 left-3 max-w-sm rounded-lg border border-warning/40 bg-card/95 p-3 text-xs text-warning-text shadow-sm"
                >
                    <p v-for="(issue, index) in generalIssues" :key="index">
                        {{ issue.message }}
                    </p>
                </div>
            </div>

            <!-- Inspector -->
            <aside
                v-if="!compact"
                class="w-96 shrink-0 scrollbar-thin overflow-y-auto border-l bg-background p-4"
                aria-label="Step settings"
            >
                <template v-if="selectedDefinition">
                    <NodeInspector
                        v-if="editable"
                        :id="selectedDefinition.id"
                        :key="selectedDefinition.id"
                        :type="selectedDefinition.type"
                        :label="selectedDefinition.data.label"
                        :config="selectedDefinition.data.config"
                        :catalog="catalog"
                        :trigger="trigger"
                        :fields="fields"
                        :variables="variables"
                        :issues="issuesByNode[selectedDefinition.id] ?? []"
                        @update:label="updateLabel"
                        @update:config="updateConfig"
                        @update:trigger="changeTrigger"
                        @rename-input="renameInput"
                        @remove="removeSelected"
                    />
                    <NodeDetails
                        v-else
                        :node="selectedDefinition"
                        :catalog="catalog"
                        :fields="fields"
                        :trigger="trigger"
                    />
                </template>

                <div v-else class="grid grid-cols-1 gap-5">
                    <div>
                        <p class="text-sm font-medium">About this workflow</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ workflow.description || 'No description yet.' }}
                        </p>
                    </div>
                    <p
                        class="rounded-lg bg-secondary/60 p-3 text-sm text-muted-foreground"
                    >
                        Select a step to change what it does. The draft saves as
                        you work; runs only use what you publish.
                    </p>
                    <div class="grid grid-cols-1 gap-2">
                        <p class="text-sm font-medium">Recent runs</p>
                        <Deferred data="recentRuns">
                            <template #fallback>
                                <div class="grid gap-2">
                                    <Skeleton
                                        v-for="index in 3"
                                        :key="index"
                                        class="h-12 w-full rounded-lg"
                                    />
                                </div>
                            </template>
                            <p
                                v-if="!recentRuns?.data.length"
                                class="text-sm text-muted-foreground"
                            >
                                No runs yet.
                            </p>
                            <ul v-else class="grid grid-cols-1 gap-1.5">
                                <li
                                    v-for="run in recentRuns.data"
                                    :key="run.id"
                                >
                                    <Link
                                        :href="runShow({ run: run.id })"
                                        class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{
                                                run.reference
                                            }}</span>
                                            <span
                                                class="block truncate text-xs text-muted-foreground"
                                                >{{
                                                    run.subject?.label ??
                                                    run.starter?.name ??
                                                    run.trigger.label
                                                }}
                                                ·
                                                {{
                                                    timeAgo(run.created_at)
                                                }}</span
                                            >
                                        </span>
                                        <EnumBadge :option="run.status" />
                                    </Link>
                                </li>
                            </ul>
                        </Deferred>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Inspection mode on small screens -->
    <Sheet v-if="compact" v-model:open="detailsOpen">
        <SheetContent side="bottom" class="max-h-[75dvh] overflow-y-auto">
            <SheetHeader>
                <SheetTitle>{{
                    selectedDefinition?.data.label || 'Step'
                }}</SheetTitle>
                <SheetDescription>What this step does.</SheetDescription>
            </SheetHeader>
            <div class="px-4 pb-6">
                <NodeDetails
                    v-if="selectedDefinition"
                    :node="selectedDefinition"
                    :catalog="catalog"
                    :fields="fields"
                    :trigger="trigger"
                />
                <ul
                    v-if="
                        selectedDefinition &&
                        issuesByNode[selectedDefinition.id]?.length
                    "
                    class="mt-4 list-disc space-y-1 pl-5 text-xs text-warning-text"
                >
                    <li
                        v-for="issue in issuesByNode[selectedDefinition.id]"
                        :key="issue"
                    >
                        {{ issue }}
                    </li>
                </ul>
            </div>
        </SheetContent>
    </Sheet>

    <PublishDialog
        v-if="can.publish"
        v-model:open="publishing"
        :workflow-id="workflow.id"
        :next-version="
            workflow.has_unpublished_changes
                ? nextVersion
                : (workflow.version ?? nextVersion)
        "
        :issues="issues"
        :before-publish="save"
    />
    <VersionsSheet
        v-model:open="versionsOpen"
        :workflow-id="workflow.id"
        :versions="versions"
        :can-restore="can.update && !compact"
        @restored="reloadDraft"
    />
    <StartRunDialog
        v-if="can.execute && startForm"
        v-model:open="starting"
        :workflow-id="workflow.id"
        :workflow-name="workflow.name"
        :inputs="startForm.inputs"
        :members="catalog.members"
    />
    <ConfirmDialog
        v-model:open="deleting"
        :title="`Delete ${workflow.name}?`"
        description="This removes the workflow and its versions. Workflows that have run cannot be deleted; archive those instead."
        confirm-label="Delete workflow"
        destructive
        :processing="deleteProcessing"
        @confirm="confirmDelete"
    />
</template>
