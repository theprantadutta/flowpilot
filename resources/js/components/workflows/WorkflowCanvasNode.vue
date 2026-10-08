<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import type { NodeProps } from '@vue-flow/core';
import {
    CircleCheck,
    CircleX,
    Clock,
    LoaderCircle,
    TriangleAlert,
} from '@lucide/vue';
import { computed, inject } from 'vue';
import NamedIcon from '@/components/NamedIcon.vue';
import { canvasContextKey } from '@/lib/workflowCanvas';
import { describeNode, handlesFor, toneChip } from '@/lib/workflows';
import { cn } from '@/lib/utils';
import type { NodeKind, WorkflowNodeData } from '@/types/workflows';

/**
 * One step on the workflow canvas: what it is, what it does, where it can
 * lead, and (on a run) how it went.
 */
const props = defineProps<NodeProps<WorkflowNodeData, object, NodeKind>>();

const context = inject(canvasContextKey);

const type = computed(() =>
    context?.catalog.nodeTypes.find((option) => option.value === props.type),
);
const isTrigger = computed(() => props.type === 'trigger');
const icon = computed(() =>
    isTrigger.value ? 'zap' : (type.value?.icon ?? 'circle'),
);
const tone = computed(() =>
    isTrigger.value ? 'flow' : (type.value?.tone ?? 'neutral'),
);
const typeLabel = computed(() =>
    isTrigger.value ? 'Trigger' : (type.value?.label ?? props.type),
);

const summary = computed(() =>
    context
        ? describeNode(
              {
                  id: props.id,
                  type: props.type,
                  position: props.position,
                  data: props.data,
              },
              context.catalog,
              context.fields.value,
              context.trigger.value,
          )
        : '',
);

const handles = computed(() =>
    context ? handlesFor(props.type, props.data.config, context.catalog) : [],
);
const issues = computed(() => context?.issues.value[props.id] ?? []);
const state = computed(() => context?.states?.value[props.id]);

function handleLeft(index: number): string {
    return `${((index + 1) / (handles.value.length + 1)) * 100}%`;
}

const stateClasses: Record<string, string> = {
    done: 'border-success/60',
    current: 'border-flow ring-2 ring-flow/30',
    waiting: 'border-warning ring-2 ring-warning/30',
    failed: 'border-danger ring-2 ring-danger/30',
    cancelled: 'border-border opacity-70',
};
</script>

<template>
    <div
        :class="
            cn(
                'relative w-64 rounded-xl border bg-card text-left shadow-xs transition-shadow',
                selected && 'ring-2 ring-primary',
                state ? stateClasses[state] : 'border-border',
                context?.states && !state && 'opacity-55',
            )
        "
    >
        <Handle
            v-if="!isTrigger"
            type="target"
            :position="Position.Top"
            :connectable="connectable"
            class="workflow-handle"
        />

        <div class="flex items-start gap-3 p-3">
            <span
                :class="
                    cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                        toneChip[tone],
                    )
                "
            >
                <NamedIcon :name="icon" class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[0.6875rem] font-medium text-muted-foreground">
                    {{ typeLabel }}
                </p>
                <p class="truncate text-sm font-semibold text-foreground">
                    {{ data.label || typeLabel }}
                </p>
                <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                    {{ summary }}
                </p>
            </div>
            <span
                v-if="issues.length && !state"
                class="flex shrink-0 items-center gap-1 rounded-full bg-warning-soft px-1.5 py-0.5 text-[0.6875rem] font-medium text-warning-text"
                :title="issues.join(' ')"
            >
                <TriangleAlert class="size-3" aria-hidden="true" />
                {{ issues.length }}
                <span class="sr-only">{{
                    issues.length === 1 ? 'problem' : 'problems'
                }}</span>
            </span>
            <span v-else-if="state" class="shrink-0" :aria-label="state">
                <CircleCheck
                    v-if="state === 'done'"
                    class="size-4 text-success-text"
                    aria-hidden="true"
                />
                <LoaderCircle
                    v-else-if="state === 'current'"
                    class="size-4 animate-spin text-flow-text motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <Clock
                    v-else-if="state === 'waiting'"
                    class="size-4 text-warning-text"
                    aria-hidden="true"
                />
                <CircleX
                    v-else-if="state === 'failed'"
                    class="size-4 text-danger-text"
                    aria-hidden="true"
                />
            </span>
        </div>

        <div
            v-if="handles.length > 1"
            class="flex justify-around gap-1 border-t px-2 py-1.5"
        >
            <span
                v-for="handle in handles"
                :key="handle.id"
                class="max-w-24 truncate text-[0.6875rem] font-medium text-muted-foreground"
            >
                {{ handle.label }}
            </span>
        </div>

        <Handle
            v-for="(handle, index) in handles"
            :id="handle.id"
            :key="handle.id"
            type="source"
            :position="Position.Bottom"
            :connectable="connectable"
            :style="{ left: handleLeft(index) }"
            :title="`Connect “${handle.label}”`"
            class="workflow-handle"
        />
    </div>
</template>
