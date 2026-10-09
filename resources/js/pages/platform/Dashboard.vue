<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowRight, Building2, CircleCheck, Inbox } from '@lucide/vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/reports/StatTile.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/platform';
import {
    index as organizationsIndex,
    show as organizationShow,
} from '@/routes/platform/organizations';
import type { PlatformPlanRequest } from '@/types/platform';
import type { ChartData, ReportTile } from '@/types/reports';

defineProps<{
    tiles: ReportTile[];
    plans: ChartData;
    charts?: ChartData[];
    requests: PlatformPlanRequest[];
    newest: {
        name: string;
        slug: string;
        owner: string;
        created_at: string | null;
    }[];
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Platform', href: dashboard() }],
});

/** A request for the plan being trialled asks to keep it once the trial ends. */
function requestSummary(from: string, to: string): string {
    return from === to ? `keep ${to} after the trial` : `${from} to ${to}`;
}
</script>

<template>
    <Head title="Platform" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Platform"
            description="How FlowPilot is doing across every organization, and what is waiting on the team."
        />

        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <li v-for="tile in tiles" :key="tile.key">
                <StatTile
                    class="h-full"
                    :label="tile.label"
                    :value="tile.value"
                    :format="tile.format"
                    :hint="tile.hint"
                    :tone="tile.tone"
                />
            </li>
        </ul>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <section
                aria-labelledby="requests-heading"
                class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-xs"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b px-5 py-3.5"
                >
                    <h2
                        id="requests-heading"
                        class="font-display text-sm font-semibold"
                    >
                        Upgrade requests waiting
                    </h2>
                    <span
                        v-if="requests.length"
                        class="figures text-xs text-muted-foreground"
                        >Oldest first</span
                    >
                </div>
                <div
                    v-if="requests.length === 0"
                    class="flex items-start gap-3 px-5 py-6"
                >
                    <CircleCheck
                        class="mt-0.5 size-5 shrink-0 text-success-text"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-sm font-medium">Nothing waiting</p>
                        <p class="text-sm text-muted-foreground">
                            When an owner asks for a bigger plan, the request
                            shows up here for the team to settle.
                        </p>
                    </div>
                </div>
                <ul v-else class="divide-y">
                    <li v-for="request in requests" :key="request.id">
                        <Link
                            :href="organizationShow(request.organization.slug)"
                            class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning-soft text-warning-text"
                            >
                                <Inbox class="size-4" aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium">
                                    {{ request.organization.name }}:
                                    {{
                                        requestSummary(request.from, request.to)
                                    }}
                                </span>
                                <span
                                    class="block truncate text-sm text-muted-foreground"
                                >
                                    {{
                                        request.message ||
                                        `Asked by ${request.requester ?? 'a former member'}`
                                    }}
                                </span>
                            </span>
                            <time
                                :datetime="request.created_at ?? undefined"
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ timeAgo(request.created_at) }}</time
                            >
                        </Link>
                    </li>
                </ul>
            </section>

            <ChartCard :chart="plans" />
        </div>

        <div
            v-if="charts === undefined"
            class="grid gap-4 lg:grid-cols-2"
            aria-busy="true"
        >
            <Skeleton class="h-72 rounded-xl" />
            <Skeleton class="h-72 rounded-xl" />
        </div>
        <section v-else aria-label="Trends" class="grid gap-4 lg:grid-cols-2">
            <ChartCard
                v-for="chart in charts"
                :key="chart.key"
                :chart="chart"
            />
        </section>

        <section
            aria-labelledby="newest-heading"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <div
                class="flex items-center justify-between gap-3 border-b px-5 py-3.5"
            >
                <h2
                    id="newest-heading"
                    class="font-display text-sm font-semibold"
                >
                    Newest organizations
                </h2>
                <Button as-child variant="ghost" size="sm" class="-mr-2">
                    <Link :href="organizationsIndex()"
                        >All organizations<ArrowRight
                    /></Link>
                </Button>
            </div>
            <EmptyState
                v-if="newest.length === 0"
                compact
                :icon="Building2"
                title="No organizations yet"
                description="Organizations appear here as soon as someone finishes signing up."
            />
            <ul v-else class="grid divide-y sm:grid-cols-2 sm:divide-y-0">
                <li
                    v-for="organization in newest"
                    :key="organization.slug"
                    class="border-b sm:odd:border-r"
                >
                    <Link
                        :href="organizationShow(organization.slug)"
                        class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{
                                organization.name
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >Owned by {{ organization.owner }}</span
                            >
                        </span>
                        <time
                            :datetime="organization.created_at ?? undefined"
                            class="shrink-0 text-xs text-muted-foreground"
                            >{{ formatDate(organization.created_at) }}</time
                        >
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
