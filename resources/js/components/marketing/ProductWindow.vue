<script setup lang="ts">
import {
    Boxes,
    ChartColumn,
    CircleAlert,
    FolderKanban,
    GitBranch,
    LayoutDashboard,
    ListChecks,
    Stamp,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import StatTile from '@/components/reports/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { cn } from '@/lib/utils';
import type { ChartData } from '@/types/reports';

/**
 * The FlowPilot overview as a customer sees it, drawn with the app's own
 * components and an example organization's numbers. Purely a picture: it is
 * inert, and screen readers get the caption instead.
 */
const nav = [
    { label: 'Overview', icon: LayoutDashboard, active: true },
    { label: 'Projects', icon: FolderKanban },
    { label: 'Tasks', icon: ListChecks },
    { label: 'Issues', icon: CircleAlert },
    { label: 'Inventory', icon: Boxes },
    { label: 'Workflows', icon: GitBranch },
    { label: 'Approvals', icon: Stamp, badge: 3 },
    { label: 'Reports', icon: ChartColumn },
];

const runs: ChartData = {
    key: 'runs',
    title: 'Workflow runs this week',
    description: 'By outcome',
    kind: 'columns',
    format: 'number',
    stacked: true,
    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    series: [
        {
            key: 'completed',
            label: 'Completed',
            color: 'success',
            values: [31, 38, 42, 36, 29, 6, 4],
        },
        {
            key: 'waiting',
            label: 'Waiting',
            color: 'warning',
            values: [2, 3, 4, 3, 5, 1, 0],
        },
        {
            key: 'failed',
            label: 'Failed',
            color: 'danger',
            values: [0, 1, 0, 0, 1, 0, 0],
        },
    ],
    empty: false,
};

const waiting = [
    {
        title: 'PR-118 40 industrial sensors',
        detail: '$8,420.00 · Finance approval',
        age: '2h',
    },
    {
        title: 'Overtime for weekend shift',
        detail: 'Operations · asked by Marcus Reyes',
        age: '5h',
    },
    {
        title: 'New supplier: Harbor Fasteners',
        detail: 'Procurement · due tomorrow',
        age: '1d',
    },
];
</script>

<template>
    <figure
        class="overflow-hidden rounded-2xl border border-border-strong/60 bg-background shadow-lg ring-1 ring-black/5 dark:ring-white/5"
    >
        <figcaption class="sr-only">
            The FlowPilot overview for an example company: headline figures,
            workflow runs this week by outcome, and approvals waiting on the
            viewer.
        </figcaption>
        <div
            class="flex items-center gap-2 border-b bg-card px-4 py-2.5"
            aria-hidden="true"
        >
            <span class="size-2.5 rounded-full bg-border-strong" />
            <span class="size-2.5 rounded-full bg-border-strong" />
            <span class="size-2.5 rounded-full bg-border-strong" />
            <span
                class="ml-3 truncate rounded-md bg-muted px-3 py-1 text-xs text-muted-foreground"
                >Overview · Northwind Fabrication</span
            >
        </div>

        <div class="flex" inert aria-hidden="true">
            <aside
                class="hidden w-48 shrink-0 flex-col gap-1 border-r bg-sidebar p-3 md:flex"
            >
                <div class="mb-2 flex items-center gap-2 px-2 py-1.5">
                    <AppLogoIcon class="size-6" />
                    <span class="truncate text-sm font-semibold"
                        >Northwind</span
                    >
                </div>
                <span
                    v-for="item in nav"
                    :key="item.label"
                    :class="
                        cn(
                            'flex items-center gap-2 rounded-md px-2 py-1.5 text-[0.8125rem]',
                            item.active
                                ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                : 'text-muted-foreground',
                        )
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span
                        v-if="item.badge"
                        class="rounded-full bg-warning-soft px-1.5 figures text-[0.6875rem] font-medium text-warning-text"
                        >{{ item.badge }}</span
                    >
                </span>
            </aside>

            <div class="grid min-w-0 flex-1 content-start gap-4 p-4 sm:p-5">
                <div>
                    <p class="font-display text-lg font-semibold">
                        Good morning, Priya
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Here is what needs attention at Northwind Fabrication.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatTile label="Active projects" :value="12" />
                    <StatTile
                        label="Overdue tasks"
                        :value="2"
                        tone="warning"
                        hint="Both due yesterday"
                    />
                    <StatTile label="Open approvals" :value="5" />
                    <StatTile
                        label="Runs this week"
                        :value="205"
                        hint="99% finished"
                        tone="success"
                    />
                </div>

                <div
                    class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]"
                >
                    <ChartCard :chart="runs" />
                    <section class="overflow-hidden rounded-xl border bg-card">
                        <h3
                            class="border-b px-4 py-3 font-display text-sm font-semibold"
                        >
                            Waiting on you
                        </h3>
                        <ul class="divide-y">
                            <li
                                v-for="item in waiting"
                                :key="item.title"
                                class="flex items-start gap-3 px-4 py-3"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning-text"
                                >
                                    <Stamp class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ item.title }}</span
                                    >
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ item.detail }}</span
                                    >
                                </span>
                                <StatusBadge tone="warning" class="h-5 px-2">{{
                                    item.age
                                }}</StatusBadge>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </figure>
</template>
