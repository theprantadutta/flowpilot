<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MailCheck, RefreshCw, X } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, timeAgo } from '@/lib/format';
import { destroy, resend } from '@/routes/members/invitations';
import type { PendingInvitation } from '@/types/members';

defineProps<{
    invitations: PendingInvitation[];
    timezone: string;
}>();

const busy = ref<string | null>(null);

function act(invitation: PendingInvitation, action: 'resend' | 'revoke') {
    const route =
        action === 'resend'
            ? resend({ invitation: invitation.id })
            : destroy({ invitation: invitation.id });

    router.visit(route, {
        preserveScroll: true,
        onStart: () => (busy.value = `${action}:${invitation.id}`),
        onFinish: () => (busy.value = null),
    });
}
</script>

<template>
    <EmptyState
        v-if="invitations.length === 0"
        :icon="MailCheck"
        title="No invitations waiting"
        description="Everyone you invited has joined. New invitations show up here until they are accepted."
        compact
    />

    <ul v-else class="divide-y">
        <li
            v-for="invitation in invitations"
            :key="invitation.id"
            class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ invitation.email }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ invitation.role_label }}
                    <template v-if="invitation.department">
                        · {{ invitation.department }}</template
                    >
                    <template v-if="invitation.invited_by">
                        · Invited by {{ invitation.invited_by }}</template
                    >
                    · Sent {{ timeAgo(invitation.sent_at) }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <StatusBadge v-if="invitation.is_expired" tone="danger"
                    >Expired</StatusBadge
                >
                <StatusBadge v-else tone="warning">
                    Until {{ formatDate(invitation.expires_at, timezone) }}
                </StatusBadge>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="busy !== null"
                    @click="act(invitation, 'resend')"
                >
                    <RefreshCw
                        :class="{
                            'animate-spin': busy === `resend:${invitation.id}`,
                        }"
                    />
                    Resend
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="busy !== null"
                    :aria-label="`Revoke invitation to ${invitation.email}`"
                    @click="act(invitation, 'revoke')"
                >
                    <X />
                    Revoke
                </Button>
            </div>
        </li>
    </ul>
</template>
