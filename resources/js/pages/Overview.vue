<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { ArrowRight, MailPlus, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useOrganization } from '@/composables/useOrganization';
import { plural } from '@/lib/format';
import { overview } from '@/routes';
import { index as membersIndex } from '@/routes/members';

defineProps<{
    team: { members: number; pendingInvitations: number };
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

        <section
            aria-labelledby="team-heading"
            class="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
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
                            {{ plural(team.pendingInvitations, 'invitation') }}
                            waiting to be accepted.
                        </template>
                        <template v-else-if="team.members === 1">
                            FlowPilot works best when approvals have someone to
                            go to. Invite your team.
                        </template>
                        <template v-else>
                            Everyone you invited has joined.
                        </template>
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button v-if="can('members.view')" as-child variant="outline">
                    <Link :href="membersIndex()">
                        View members
                        <ArrowRight />
                    </Link>
                </Button>
                <Button
                    v-if="can('members.invite') && team.members === 1"
                    as-child
                >
                    <Link :href="membersIndex()">
                        <MailPlus />
                        Invite your team
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
