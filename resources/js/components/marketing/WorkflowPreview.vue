<script setup lang="ts">
import { CircleCheck, Clock } from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import { computed, useTemplateRef } from 'vue';
import NamedIcon from '@/components/NamedIcon.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { toneChip } from '@/lib/workflows';
import { cn } from '@/lib/utils';

/**
 * A purchase approval workflow on the builder canvas, mid-run: the request
 * is over the threshold, so it is waiting on Finance. Nodes copy the real
 * canvas node; the canvas is drawn at a fixed size and scaled to fit.
 */
type Node = {
    id: string;
    type: string;
    icon: string;
    tone: keyof typeof toneChip;
    title: string;
    summary: string;
    x: number;
    y: number;
    handles?: string[];
    state?: 'done' | 'waiting';
};

const WIDTH = 880;
const HEIGHT = 520;
const NODE_WIDTH = 256;
const NODE_HEIGHT = 78;
const STRIP_HEIGHT = 26;

const nodes: Node[] = [
    {
        id: 'trigger',
        type: 'Trigger',
        icon: 'zap',
        tone: 'flow',
        title: 'New purchase request',
        summary: 'When a request is submitted',
        x: 312,
        y: 16,
        state: 'done',
    },
    {
        id: 'amount',
        type: 'Condition',
        icon: 'git-fork',
        tone: 'info',
        title: 'Over $5,000?',
        summary: 'Amount is more than $5,000',
        x: 312,
        y: 146,
        handles: ['Yes', 'No'],
        state: 'done',
    },
    {
        id: 'finance',
        type: 'Approval',
        icon: 'stamp',
        tone: 'warning',
        title: 'Finance approves',
        summary: 'Finance, due in 48 hours',
        x: 96,
        y: 286,
        handles: ['Approved', 'Rejected'],
        state: 'waiting',
    },
    {
        id: 'order',
        type: 'Assign',
        icon: 'user-check',
        tone: 'info',
        title: 'Place the order',
        summary: 'Procurement, due in 2 days',
        x: 560,
        y: 286,
    },
    {
        id: 'notify',
        type: 'Notification',
        icon: 'bell',
        tone: 'info',
        title: 'Tell procurement',
        summary: 'In the app and by email',
        x: 16,
        y: 426,
    },
    {
        id: 'declined',
        type: 'Notification',
        icon: 'bell',
        tone: 'info',
        title: 'Tell the requester',
        summary: 'With the approver’s comment',
        x: 300,
        y: 426,
    },
];

const edges: { from: string; handle: number; to: string; active?: boolean }[] =
    [
        { from: 'trigger', handle: 0, to: 'amount', active: true },
        { from: 'amount', handle: 0, to: 'finance', active: true },
        { from: 'amount', handle: 1, to: 'order' },
        { from: 'finance', handle: 0, to: 'notify' },
        { from: 'finance', handle: 1, to: 'declined' },
    ];

const byId = Object.fromEntries(nodes.map((node) => [node.id, node]));

function height(node: Node): number {
    return NODE_HEIGHT + (node.handles ? STRIP_HEIGHT : 0);
}

const paths = edges.map((edge) => {
    const source = byId[edge.from];
    const target = byId[edge.to];
    const count = source.handles?.length ?? 1;
    const x1 = source.x + (NODE_WIDTH * (edge.handle + 1)) / (count + 1);
    const y1 = source.y + height(source);
    const x2 = target.x + NODE_WIDTH / 2;
    const y2 = target.y;
    const bend = Math.max(24, (y2 - y1) / 2);

    return {
        key: `${edge.from}-${edge.handle}-${edge.to}`,
        d: `M ${x1} ${y1} C ${x1} ${y1 + bend}, ${x2} ${y2 - bend}, ${x2} ${y2}`,
        active: edge.active ?? false,
    };
});

const frame = useTemplateRef<HTMLElement>('frame');
const { width } = useElementSize(frame);
const scale = computed(() =>
    width.value > 0 ? Math.min(1, width.value / WIDTH) : 1,
);
</script>

<template>
    <figure
        class="overflow-hidden rounded-2xl border border-border-strong/60 bg-card shadow-lg ring-1 ring-black/5 dark:ring-white/5"
    >
        <figcaption
            class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3.5"
        >
            <span class="min-w-0">
                <span class="block font-display text-sm font-semibold"
                    >Purchase approval</span
                >
                <span class="block text-xs text-muted-foreground"
                    >Version 3, published · run 1842 waiting on Finance</span
                >
            </span>
            <StatusBadge tone="success">Active</StatusBadge>
        </figcaption>

        <p class="sr-only">
            When a purchase request is submitted, FlowPilot checks whether it is
            over $5,000. If it is, anyone in Finance is asked to approve it
            within 48 hours: approval tells procurement, rejection tells the
            requester why. Smaller requests go straight to procurement as a task
            to place the order.
        </p>

        <div
            ref="frame"
            class="relative bg-background bg-dot-grid"
            :style="{ height: `${HEIGHT * scale}px` }"
            aria-hidden="true"
        >
            <div
                class="absolute top-0 left-1/2 origin-top"
                :style="{
                    width: `${WIDTH}px`,
                    height: `${HEIGHT}px`,
                    transform: `translateX(-50%) scale(${scale})`,
                }"
            >
                <svg
                    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
                    :width="WIDTH"
                    :height="HEIGHT"
                    class="absolute inset-0"
                    fill="none"
                >
                    <path
                        v-for="path in paths"
                        :key="path.key"
                        :d="path.d"
                        :class="
                            path.active
                                ? 'animate-flow-dash stroke-flow'
                                : 'stroke-border-strong'
                        "
                        :stroke-width="path.active ? 2 : 1.5"
                        :stroke-dasharray="path.active ? '6 6' : undefined"
                        stroke-linecap="round"
                    />
                </svg>

                <div
                    v-for="node in nodes"
                    :key="node.id"
                    :class="
                        cn(
                            'absolute overflow-hidden rounded-xl border bg-card text-left shadow-xs',
                            node.state === 'done' && 'border-success/60',
                            node.state === 'waiting' &&
                                'border-warning ring-2 ring-warning/30',
                            !node.state && 'border-border',
                        )
                    "
                    :style="{
                        left: `${node.x}px`,
                        top: `${node.y}px`,
                        width: `${NODE_WIDTH}px`,
                        height: `${height(node)}px`,
                    }"
                >
                    <div
                        class="flex items-start gap-3 p-3"
                        :style="{ height: `${NODE_HEIGHT}px` }"
                    >
                        <span
                            :class="
                                cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                    toneChip[node.tone],
                                )
                            "
                        >
                            <NamedIcon :name="node.icon" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[0.6875rem] font-medium text-muted-foreground"
                            >
                                {{ node.type }}
                            </p>
                            <p
                                class="truncate text-sm font-semibold text-foreground"
                            >
                                {{ node.title }}
                            </p>
                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ node.summary }}
                            </p>
                        </div>
                        <CircleCheck
                            v-if="node.state === 'done'"
                            class="size-4 shrink-0 text-success-text"
                        />
                        <Clock
                            v-else-if="node.state === 'waiting'"
                            class="size-4 shrink-0 text-warning-text"
                        />
                    </div>
                    <div
                        v-if="node.handles"
                        class="flex justify-around gap-1 border-t px-2 py-1.5"
                    >
                        <span
                            v-for="handle in node.handles"
                            :key="handle"
                            class="text-[0.6875rem] font-medium text-muted-foreground"
                            >{{ handle }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </figure>
</template>
