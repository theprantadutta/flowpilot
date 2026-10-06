<script setup lang="ts">
import { Check, GitBranch } from '@lucide/vue';

/**
 * A static picture of the product's core idea, shown beside the auth forms:
 * one purchase request moving through its approval workflow.
 */
type Step = {
    title: string;
    detail: string;
    time?: string;
    state: 'done' | 'waiting' | 'upcoming';
    branch?: boolean;
};

const steps: Step[] = [
    {
        title: 'Purchase request submitted',
        detail: 'Dana Whitfield requested 40 industrial sensors',
        time: '09:12',
        state: 'done',
    },
    {
        title: 'Amount is over $5,000',
        detail: '$8,420.00 needs manager and finance sign-off',
        time: '09:12',
        state: 'done',
        branch: true,
    },
    {
        title: 'Manager approval',
        detail: 'Approved by Marcus Reyes',
        time: '09:47',
        state: 'done',
    },
    {
        title: 'Finance approval',
        detail: 'Waiting on Priya Nair',
        time: '2h 14m',
        state: 'waiting',
    },
    {
        title: 'Notify procurement',
        detail: 'Runs when finance approves',
        state: 'upcoming',
    },
    {
        title: 'Receive stock into inventory',
        detail: 'Main warehouse',
        state: 'upcoming',
    },
];
</script>

<template>
    <figure
        class="w-full max-w-md rounded-xl border border-white/10 bg-card/80 shadow-lg backdrop-blur-sm"
    >
        <figcaption
            class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4"
        >
            <div class="min-w-0">
                <p
                    class="truncate font-display text-[0.9375rem] font-semibold text-foreground"
                >
                    Purchase approval
                </p>
                <p class="figures text-xs text-muted-foreground">
                    Run 1842, started today at 09:12
                </p>
            </div>
            <span
                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-warning-soft px-2.5 py-1 text-xs font-medium text-warning-text"
            >
                <span class="size-1.5 rounded-full bg-warning" />
                Waiting
            </span>
        </figcaption>

        <ol class="px-5 py-4">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="relative flex gap-3.5 pb-5 last:pb-0"
            >
                <!-- The route between this step and the next -->
                <span
                    v-if="index < steps.length - 1"
                    aria-hidden="true"
                    class="absolute top-6 bottom-0 left-[0.6875rem] w-px"
                    :class="
                        step.state === 'done' &&
                        steps[index + 1].state !== 'upcoming'
                            ? 'bg-flow/70'
                            : 'bg-[repeating-linear-gradient(to_bottom,var(--border-strong)_0_4px,transparent_4px_8px)]'
                    "
                />

                <span
                    class="relative z-10 mt-0.5 flex size-[1.4375rem] shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-flow/15 text-flow ring-1 ring-flow/40':
                            step.state === 'done',
                        'animate-pulse-ring bg-warning text-[#2c2210]':
                            step.state === 'waiting',
                        'bg-card ring-1 ring-border-strong':
                            step.state === 'upcoming',
                    }"
                >
                    <GitBranch
                        v-if="step.state === 'done' && step.branch"
                        class="size-3"
                        :stroke-width="2.5"
                    />
                    <Check
                        v-else-if="step.state === 'done'"
                        class="size-3"
                        :stroke-width="3"
                    />
                    <span
                        v-else-if="step.state === 'waiting'"
                        class="size-1.5 rounded-full bg-current"
                    />
                </span>

                <div
                    class="flex min-w-0 flex-1 items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p
                            class="text-sm font-medium"
                            :class="
                                step.state === 'upcoming'
                                    ? 'text-muted-foreground'
                                    : 'text-foreground'
                            "
                        >
                            {{ step.title }}
                        </p>
                        <p
                            class="truncate text-xs"
                            :class="
                                step.state === 'waiting'
                                    ? 'text-warning-text'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ step.detail }}
                        </p>
                    </div>
                    <span
                        v-if="step.time"
                        class="shrink-0 pt-0.5 figures text-xs"
                        :class="
                            step.state === 'waiting'
                                ? 'font-medium text-warning-text'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ step.time }}
                    </span>
                </div>
            </li>
        </ol>
    </figure>
</template>
