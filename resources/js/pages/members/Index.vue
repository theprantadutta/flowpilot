<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Search, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InvitationsList from '@/components/members/InvitationsList.vue';
import InviteMemberDialog from '@/components/members/InviteMemberDialog.vue';
import MembersTable from '@/components/members/MembersTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { useOrganization } from '@/composables/useOrganization';
import { plural } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as membersIndex } from '@/routes/members';
import type { MemberRow, PendingInvitation, RoleOption } from '@/types/members';

const props = defineProps<{
    members: MemberRow[];
    invitations: PendingInvitation[];
    roles: RoleOption[];
    defaultRole: string;
    can: { invite: boolean; manage: boolean; seeInvitations: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Members', href: membersIndex() }] });

const { organization } = useOrganization();

// The command palette's "Invite member" lands here with ?invite=1.
const openInviteOnLoad =
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).has('invite');

type Tab = 'members' | 'invitations';
const tab = ref<Tab>('members');
const query = ref('');

const filteredMembers = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (!needle) {
        return props.members;
    }

    return props.members.filter((member) =>
        [member.name, member.email, member.role_label, member.department ?? '']
            .join(' ')
            .toLowerCase()
            .includes(needle),
    );
});

const activeCount = computed(
    () => props.members.filter((member) => member.status === 'active').length,
);

const tabs = computed<{ id: Tab; label: string; count: number }[]>(() => [
    { id: 'members', label: 'Members', count: props.members.length },
    ...(props.can.seeInvitations
        ? [
              {
                  id: 'invitations' as const,
                  label: 'Invitations',
                  count: props.invitations.length,
              },
          ]
        : []),
]);
</script>

<template>
    <Head title="Members" />

    <div
        class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Members"
            :description="`${plural(activeCount, 'active member')} in ${organization?.name}. Roles decide what each person can see and do.`"
        >
            <template #actions>
                <InviteMemberDialog
                    v-if="can.invite"
                    :roles="roles"
                    :default-role="defaultRole"
                    :default-open="openInviteOnLoad"
                    :organization-name="organization?.name ?? ''"
                />
            </template>
        </PageHeader>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <div
                    role="tablist"
                    aria-label="Members and invitations"
                    class="inline-flex w-fit rounded-lg bg-muted p-0.5"
                >
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.id"
                        :class="
                            cn(
                                'inline-flex h-8 items-center gap-2 rounded-md px-3 text-sm font-medium transition-colors',
                                tab === item.id
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        @click="tab = item.id"
                    >
                        {{ item.label }}
                        <span class="figures text-xs text-muted-foreground">{{
                            item.count
                        }}</span>
                    </button>
                </div>

                <div v-if="tab === 'members'" class="relative sm:w-72">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        type="search"
                        placeholder="Search by name, email or role"
                        aria-label="Search members"
                        class="pl-8"
                    />
                </div>
            </div>

            <div v-if="tab === 'members'" role="tabpanel">
                <MembersTable
                    v-if="filteredMembers.length > 0"
                    :members="filteredMembers"
                    :roles="roles"
                />
                <EmptyState
                    v-else
                    :icon="Users"
                    :title="`No one matches “${query}”`"
                    description="Try a different name, email address or role."
                    compact
                />
            </div>

            <div v-else role="tabpanel">
                <InvitationsList
                    :invitations="invitations"
                    :timezone="organization?.timezone ?? 'UTC'"
                />
            </div>
        </section>
    </div>
</template>
