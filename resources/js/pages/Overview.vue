<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    CircleCheck,
    ListChecks,
    MailPlus,
    Plus,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import ChartCard from '@/components/charts/ChartCard.vue';
import DueDate from '@/components/DueDate.vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/reports/StatTile.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { plural } from '@/lib/format';
import { cn } from '@/lib/utils';
import { overview } from '@/routes';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as issuesIndex } from '@/routes/issues';
import { index as membersIndex } from '@/routes/members';
import { index as purchaseRequestsIndex } from '@/routes/purchase-requests';
import { index as tasksIndex } from '@/routes/tasks';
import type { ActivityEntry } from '@/types/activity';
import type { EnumOption } from '@/types/operations';
import type { ChartData } from '@/types/reports';

type AttentionItem = {
    key: string;
    tone: 'danger' | 'warning' | 'info';
    icon: string;
    title: string;
    description: string;
    href: string;
    action: string;
};

type Stat = {
    key: string;
    label: string;
    value: number;
    hint: string | null;
    tone: 'success' | 'warning' | 'danger' | null;
    href: string;
};

type WorkItem = {
    id: string;
    reference: string;
    title: string;
    status: EnumOption;
    priority: EnumOption;
    project: string | null;
    due_date: string | null;
    is_overdue: boolean;
    url: string;
};

defineProps<{
    team: { members: number; pendingInvitations: number };
    stats: Stat[];
    attention?: AttentionItem[];
    myWork?: WorkItem[];
    trends?: ChartData[];
    recentActivity?: ActivityEntry[];
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Overview', href: overview() }] });

const page = usePage();
const { organization, can } = useOrganization();

const greeting = computed(() => {
    const hour = new Date().getHours();
    const firstName = page.props.auth.user?.name.split(' ')[0] ?? '';
    const part =
        hour < 12
            ? 'Good morning'
            : hour < 18
              ? 'Good afternoon'
              : 'Good evening';

    return `${part}, ${firstName}`;
});

const quickActions = computed(() =>
    [
        {
            label: 'New task',
            href: tasksIndex({}, { query: { create: 1 } }),
            show: can('tasks.create'),
        },
        {
            label: 'Report an issue',
            href: issuesIndex({}, { query: { create: 1 } }),
            show: can('issues.create'),
        },
        {
            label: 'Request an approval',
            href: approvalsIndex({}, { query: { view: 'mine', create: 1 } }),
            show: can('approvals.request'),
        },
        {
            label: 'Request a purchase',
            href: purchaseRequestsIndex({}, { query: { create: 1 } }),
            show: can('inventory.request'),
        },
    ].filter((action) => action.show),
);

const toneClasses: Record<AttentionItem['tone'], string> = {
    danger: 'bg-danger-soft text-danger-text',
    warning: 'bg-warning-soft text-warning-text',
    info: 'bg-info-soft text-info-text',
};
</script>

<template>
    <Head title="Overview" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            :title="greeting"
            :description="`Here is what needs attention at ${organization?.name}.`"
        >
            <template v-if="quickActions.length" #actions>
                <Button
                    v-for="action in quickActions"
                    :key="action.label"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="action.href"><Plus />{{ action.label }}</Link>
                </Button>
            </template>
        </PageHeader>

        <ul
            v-if="stats.length"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"
        >
            <li v-for="stat in stats" :key="stat.key">
                <Link
                    :href="stat.href"
                    class="block h-full rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&>div]:h-full [&>div]:transition-shadow hover:[&>div]:shadow-sm"
                >
                    <StatTile
                        :label="stat.label"
                        :value="stat.value"
                        :hint="stat.hint"
                        :tone="stat.tone"
                    />
                </Link>
            </li>
        </ul>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid min-w-0 content-start gap-6">
                <section
                    aria-labelledby="attention-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <h2
                        id="attention-heading"
                        class="border-b px-5 py-3.5 font-display text-sm font-semibold"
                    >
                        Needs your attention
                    </h2>
                    <div
                        v-if="attention === undefined"
                        class="grid gap-4 p-5"
                        aria-busy="true"
                    >
                        <div v-for="n in 3" :key="n" class="flex gap-3">
                            <Skeleton class="size-9 rounded-lg" />
                            <div class="flex-1 space-y-2 pt-1">
                                <Skeleton class="h-3.5 w-2/3" />
                                <Skeleton class="h-3 w-1/2" />
                            </div>
                        </div>
                    </div>
                    <div
                        v-else-if="attention.length === 0"
                        class="flex items-start gap-3 px-5 py-6"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-soft text-success-text"
                        >
                            <CircleCheck class="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <p class="font-medium">
                                Nothing needs your attention
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Overdue work, decisions waiting on you, failed
                                automation and low stock show up here.
                            </p>
                        </div>
                    </div>
                    <ul v-else class="divide-y">
                        <li
                            v-for="item in attention"
                            :key="item.key"
                            class="flex flex-wrap items-center gap-3 px-5 py-3.5 sm:flex-nowrap"
                        >
                            <span
                                :class="
                                    cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                        toneClasses[item.tone],
                                    )
                                "
                            >
                                <NamedIcon :name="item.icon" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ item.title }}</p>
                                <p
                                    class="text-sm text-pretty text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                            >
                                <Link :href="item.href"
                                    >{{ item.action }}<ArrowRight
                                /></Link>
                            </Button>
                        </li>
                    </ul>
                </section>

                <section
                    v-if="can('tasks.view')"
                    aria-labelledby="work-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b px-5 py-3.5"
                    >
                        <h2
                            id="work-heading"
                            class="font-display text-sm font-semibold"
                        >
                            Your work
                        </h2>
                        <Link
                            :href="
                                tasksIndex({}, { query: { assignee: 'me' } })
                            "
                            class="text-sm font-medium text-primary hover:underline"
                            >All your tasks</Link
                        >
                    </div>
                    <div
                        v-if="myWork === undefined"
                        class="grid gap-3 p-5"
                        aria-busy="true"
                    >
                        <Skeleton v-for="n in 3" :key="n" class="h-9 w-full" />
                    </div>
                    <EmptyState
                        v-else-if="myWork.length === 0"
                        compact
                        :icon="ListChecks"
                        title="Nothing assigned to you"
                        description="Tasks assigned to you, soonest due first, appear here."
                    />
                    <ul v-else class="divide-y">
                        <li v-for="task in myWork" :key="task.id">
                            <Link
                                :href="task.url"
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-accent/40"
                            >
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ task.reference }}</span
                                >
                                <span
                                    class="min-w-0 flex-1 truncate font-medium"
                                    >{{ task.title }}</span
                                >
                                <span
                                    v-if="task.project"
                                    class="hidden max-w-40 truncate text-xs text-muted-foreground sm:inline"
                                    >{{ task.project }}</span
                                >
                                <DueDate
                                    v-if="task.due_date"
                                    :date="task.due_date"
                                    :overdue="task.is_overdue"
                                />
                                <EnumBadge :option="task.status" />
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="grid content-start gap-6">
                <section
                    aria-labelledby="activity-heading"
                    class="rounded-xl border bg-card shadow-xs"
                >
                    <h2
                        id="activity-heading"
                        class="border-b px-5 py-3.5 font-display text-sm font-semibold"
                    >
                        Recent activity
                    </h2>
                    <div
                        v-if="recentActivity === undefined"
                        class="space-y-5 p-5"
                        aria-busy="true"
                    >
                        <div v-for="n in 4" :key="n" class="flex gap-3">
                            <Skeleton class="size-8 rounded-full" />
                            <div class="flex-1 space-y-2 pt-1">
                                <Skeleton class="h-3.5 w-5/6" />
                                <Skeleton class="h-3 w-1/3" />
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else-if="recentActivity.length === 0"
                        :icon="Activity"
                        title="Nothing has happened yet"
                        description="Changes to members, settings and work will be listed here as they happen."
                        compact
                    />
                    <ol v-else class="p-5">
                        <ActivityItem
                            v-for="(entry, index) in recentActivity"
                            :key="entry.id"
                            :entry="entry"
                            :timezone="organization?.timezone"
                            :connected="index < recentActivity.length - 1"
                        />
                    </ol>
                </section>

                <section
                    aria-labelledby="team-heading"
                    class="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-info-soft text-info-text"
                        >
                            <UsersRound class="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2
                                id="team-heading"
                                class="font-display text-sm font-semibold"
                            >
                                {{ plural(team.members, 'member') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                <template v-if="team.pendingInvitations > 0"
                                    >{{
                                        plural(
                                            team.pendingInvitations,
                                            'invitation',
                                        )
                                    }}
                                    waiting to be accepted.</template
                                >
                                <template v-else-if="team.members === 1"
                                    >Approvals need someone to go to. Invite
                                    your team.</template
                                >
                                <template v-else
                                    >Everyone you invited has joined.</template
                                >
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="can('members.invite') && team.members === 1"
                            as-child
                            size="sm"
                        >
                            <Link
                                :href="
                                    membersIndex({}, { query: { invite: 1 } })
                                "
                                ><MailPlus />Invite your team</Link
                            >
                        </Button>
                        <Button
                            v-else-if="can('members.view')"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="membersIndex()"
                                >View members<ArrowRight
                            /></Link>
                        </Button>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="trends === undefined"
            class="grid gap-4 lg:grid-cols-2"
            aria-busy="true"
        >
            <Skeleton class="h-72 rounded-xl" />
            <Skeleton class="h-72 rounded-xl" />
        </div>
        <section
            v-else-if="trends.length"
            aria-label="Trends"
            class="grid gap-4 lg:grid-cols-2"
        >
            <ChartCard
                v-for="chart in trends"
                :key="chart.key"
                :chart="chart"
                :currency="organization?.currency"
            />
        </section>
    </div>
</template>
