<script setup lang="ts">
import { Crown } from '@lucide/vue';
import MemberActions from '@/components/members/MemberActions.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { timeAgo } from '@/lib/format';
import type { MemberRow, RoleOption } from '@/types/members';

defineProps<{
    members: MemberRow[];
    roles: RoleOption[];
}>();

function lastSeen(member: MemberRow): string {
    return member.last_active_at ? timeAgo(member.last_active_at) : 'Not yet';
}
</script>

<template>
    <!-- Wide screens: a table. -->
    <table class="hidden w-full text-sm md:table">
        <thead>
            <tr class="border-b text-left text-xs text-muted-foreground">
                <th scope="col" class="py-2.5 pr-4 pl-5 font-medium">Member</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Role</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Department</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Status</th>
                <th scope="col" class="px-4 py-2.5 font-medium">Last active</th>
                <th scope="col" class="py-2.5 pr-5 pl-4">
                    <span class="sr-only">Actions</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="member in members"
                :key="member.id"
                class="border-b transition-colors last:border-0 hover:bg-accent/40"
            >
                <td class="py-3 pr-4 pl-5">
                    <div class="flex items-center gap-3">
                        <MemberAvatar
                            :name="member.name"
                            :avatar="member.avatar"
                        />
                        <div class="min-w-0">
                            <p
                                class="flex items-center gap-1.5 truncate font-medium text-foreground"
                            >
                                {{ member.name }}
                                <span
                                    v-if="member.is_you"
                                    class="rounded bg-secondary px-1.5 py-px text-[0.6875rem] font-medium text-muted-foreground"
                                    >You</span
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    member.job_title
                                        ? `${member.job_title} · `
                                        : ''
                                }}{{ member.email }}
                            </p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5">
                        <Crown
                            v-if="member.is_owner"
                            class="size-3.5 text-warning"
                            aria-hidden="true"
                        />
                        {{ member.role_label }}
                    </span>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ member.department ?? '—' }}
                </td>
                <td class="px-4 py-3">
                    <StatusBadge
                        :tone="
                            member.status === 'active' ? 'success' : 'danger'
                        "
                    >
                        {{
                            member.status === 'active' ? 'Active' : 'Suspended'
                        }}
                    </StatusBadge>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                    {{ lastSeen(member) }}
                </td>
                <td class="py-3 pr-5 pl-4">
                    <MemberActions
                        v-if="member.can.update || member.can.delete"
                        :member="member"
                        :roles="roles"
                    />
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Narrow screens: one card per member. -->
    <ul class="divide-y md:hidden">
        <li
            v-for="member in members"
            :key="member.id"
            class="flex items-start gap-3 px-4 py-3.5"
        >
            <MemberAvatar :name="member.name" :avatar="member.avatar" />
            <div class="min-w-0 flex-1 space-y-1.5">
                <div>
                    <p class="flex items-center gap-1.5 font-medium">
                        <span class="truncate">{{ member.name }}</span>
                        <span
                            v-if="member.is_you"
                            class="rounded bg-secondary px-1.5 py-px text-[0.6875rem] text-muted-foreground"
                            >You</span
                        >
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ member.email }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 font-medium">
                        <Crown
                            v-if="member.is_owner"
                            class="size-3.5 text-warning"
                            aria-hidden="true"
                        />
                        {{ member.role_label }}
                    </span>
                    <span v-if="member.department" class="text-muted-foreground"
                        >· {{ member.department }}</span
                    >
                    <StatusBadge
                        v-if="member.status === 'suspended'"
                        tone="danger"
                        >Suspended</StatusBadge
                    >
                </div>
            </div>
            <MemberActions
                v-if="member.can.update || member.can.delete"
                :member="member"
                :roles="roles"
            />
        </li>
    </ul>
</template>
