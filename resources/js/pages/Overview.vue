<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Activity, ArrowRight, MailPlus, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { plural } from '@/lib/format';
import { overview } from '@/routes';
import { index as membersIndex } from '@/routes/members';
import type { ActivityEntry } from '@/types/activity';

defineProps<{
    team: { members: number; pendingInvitations: number };
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
</script>

<template>
    <Head title="Overview" />

    <div
        class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            :title="greeting"
            :description="`Here is what is happening at ${organization?.name}.`"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section
                aria-labelledby="team-heading"
                class="flex h-fit flex-col gap-5 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-info-soft text-info-text"
                    >
                        <UsersRound class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2
                            id="team-heading"
                            class="font-display text-base font-semibold"
                        >
                            {{ plural(team.members, 'member') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="team.pendingInvitations > 0">
                                {{
                                    plural(
                                        team.pendingInvitations,
                                        'invitation',
                                    )
                                }}
                                waiting to be accepted.
                            </template>
                            <template v-else-if="team.members === 1">
                                FlowPilot works best when approvals have someone
                                to go to. Invite your team.
                            </template>
                            <template v-else
                                >Everyone you invited has joined.</template
                            >
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="can('members.view')"
                        as-child
                        variant="outline"
                    >
                        <Link :href="membersIndex()">
                            View members
                            <ArrowRight />
                        </Link>
                    </Button>
                    <Button
                        v-if="can('members.invite') && team.members === 1"
                        as-child
                    >
                        <Link
                            :href="membersIndex({}, { query: { invite: 1 } })"
                        >
                            <MailPlus />
                            Invite your team
                        </Link>
                    </Button>
                </div>
            </section>

            <section
                aria-labelledby="activity-heading"
                class="rounded-xl border bg-card shadow-xs lg:row-span-2"
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
        </div>
    </div>
</template>
