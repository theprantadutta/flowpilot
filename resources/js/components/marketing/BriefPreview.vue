<script setup lang="ts">
import { CircleAlert, CircleDot, Sparkles, TriangleAlert } from '@lucide/vue';
import { cn } from '@/lib/utils';

/**
 * The AI operations brief as members see it, with an example company's
 * records. Every point names the records it came from, as the real one does.
 */
const severity = {
    high: {
        label: 'Urgent',
        icon: CircleAlert,
        class: 'bg-danger-soft text-danger-text',
    },
    medium: {
        label: 'Soon',
        icon: TriangleAlert,
        class: 'bg-warning-soft text-warning-text',
    },
    low: {
        label: 'When you can',
        icon: CircleDot,
        class: 'bg-neutral-soft text-neutral-text',
    },
} as const;

const items: {
    title: string;
    detail: string;
    severity: keyof typeof severity;
    links: string[];
}[] = [
    {
        title: 'Line 2 conveyor is down',
        detail: 'A critical issue reported 40 minutes ago has no one assigned, and two production tasks depend on it.',
        severity: 'high',
        links: ['ISS-57 Conveyor belt snapped', 'T-311 Run batch 14'],
    },
    {
        title: 'Approve the glove reorder',
        detail: '14 boxes of nitrile gloves are left against a reorder point of 20. The purchase request is waiting on you.',
        severity: 'medium',
        links: ['PR-121 Nitrile gloves'],
    },
    {
        title: 'Two onboarding tasks are overdue',
        detail: 'Amara Okafor starts on Monday; payroll and laptop setup were due yesterday.',
        severity: 'low',
        links: ['T-298 Add to payroll', 'T-299 Prepare laptop'],
    },
];
</script>

<template>
    <figure
        class="overflow-hidden rounded-2xl border border-ai/25 bg-card shadow-lg ring-1 ring-black/5 dark:ring-white/5"
    >
        <figcaption class="sr-only">
            An example operations brief written by FlowPilot AI, listing three
            things that need attention, each linked to its records.
        </figcaption>
        <div
            class="flex items-center gap-3 border-b border-ai/15 bg-ai-soft/40 px-5 py-3.5"
        >
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ai-soft text-ai-text"
            >
                <Sparkles class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0">
                <p class="font-display text-sm font-semibold">
                    Operations brief
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    Written by FlowPilot AI 8 minutes ago
                </p>
            </div>
        </div>
        <div class="grid gap-5 p-5">
            <p
                class="font-display text-lg leading-snug font-semibold text-balance"
            >
                One stoppage needs an owner now; the rest can wait until this
                afternoon.
            </p>
            <ol class="grid gap-4">
                <li
                    v-for="(item, index) in items"
                    :key="item.title"
                    class="flex gap-3"
                >
                    <span
                        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted figures text-xs font-semibold"
                        >{{ index + 1 }}</span
                    >
                    <div class="grid min-w-0 gap-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ item.title }}</span>
                            <span
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                        severity[item.severity].class,
                                    )
                                "
                            >
                                <component
                                    :is="severity[item.severity].icon"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ severity[item.severity].label }}
                            </span>
                        </p>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ item.detail }}
                        </p>
                        <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                            <span
                                v-for="link in item.links"
                                :key="link"
                                class="font-medium text-primary"
                                >{{ link }}</span
                            >
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </figure>
</template>
