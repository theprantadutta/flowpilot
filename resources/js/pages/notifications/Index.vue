<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { BellOff, CheckCheck, Settings2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    open as openNotification,
    read,
    readAll,
} from '@/routes/notifications';
import { edit as preferencesEdit } from '@/routes/notification-preferences';
import type { NotificationItem } from '@/types/activity';
import type { Paginated } from '@/types/pagination';

defineProps<{
    notifications: Paginated<NotificationItem>;
    filter: 'all' | 'unread';
    unreadCount: number;
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Notifications', href: notificationsIndex() }],
});

const { organization } = useOrganization();

const toneDot: Record<string, string> = {
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    flow: 'bg-flow',
    ai: 'bg-ai',
    neutral: 'bg-neutral-text',
};

function markRead(item: NotificationItem) {
    router.visit(read({ notification: item.id }), { preserveScroll: true });
}

function markAllRead() {
    router.visit(readAll(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <div
        class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Notifications"
            :description="`Everything that needed your attention in ${organization?.name}.`"
        >
            <template #actions>
                <Button as-child variant="ghost" size="sm">
                    <Link :href="preferencesEdit()">
                        <Settings2 />
                        Email settings
                    </Link>
                </Button>
                <Button
                    v-if="unreadCount > 0"
                    variant="outline"
                    size="sm"
                    @click="markAllRead"
                >
                    <CheckCheck />
                    Mark all read
                </Button>
            </template>
        </PageHeader>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex items-center gap-1 border-b px-3 py-2">
                <Button
                    v-for="option in [
                        { id: 'all', label: 'All' },
                        { id: 'unread', label: `Unread (${unreadCount})` },
                    ]"
                    :key="option.id"
                    as-child
                    size="sm"
                    :variant="filter === option.id ? 'secondary' : 'ghost'"
                >
                    <Link
                        :href="
                            notificationsIndex(
                                {},
                                {
                                    query:
                                        option.id === 'all'
                                            ? {}
                                            : { filter: 'unread' },
                                },
                            )
                        "
                        preserve-scroll
                        :aria-current="
                            filter === option.id ? 'page' : undefined
                        "
                    >
                        {{ option.label }}
                    </Link>
                </Button>
            </div>

            <EmptyState
                v-if="notifications.data.length === 0"
                :icon="BellOff"
                :title="
                    filter === 'unread'
                        ? 'Nothing unread'
                        : 'No notifications yet'
                "
                :description="
                    filter === 'unread'
                        ? 'You have read everything. New approvals and assignments will appear here.'
                        : 'When something needs you, such as an approval or a task, it shows up here.'
                "
            />

            <ul v-else class="divide-y">
                <li
                    v-for="item in notifications.data"
                    :key="item.id"
                    :class="
                        cn(
                            'flex items-start gap-3 px-4 py-4 sm:px-5',
                            !item.read && 'bg-info-soft/30',
                        )
                    "
                >
                    <span
                        :class="
                            cn(
                                'mt-1.5 size-2 shrink-0 rounded-full',
                                item.read
                                    ? 'ring-1 ring-border-strong'
                                    : (toneDot[item.tone] ?? 'bg-info'),
                            )
                        "
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="openNotification({ notification: item.id })"
                            :class="
                                cn(
                                    'text-sm hover:underline',
                                    item.read
                                        ? 'text-foreground/80'
                                        : 'font-medium text-foreground',
                                )
                            "
                        >
                            {{ item.title }}
                            <span v-if="!item.read" class="sr-only"
                                >(unread)</span
                            >
                        </Link>
                        <p
                            v-if="item.body"
                            class="mt-0.5 text-sm text-muted-foreground"
                        >
                            {{ item.body }}
                        </p>
                        <time
                            :datetime="item.created_at ?? undefined"
                            :title="
                                formatDateTime(
                                    item.created_at,
                                    organization?.timezone,
                                )
                            "
                            class="mt-1 block text-xs text-muted-foreground"
                        >
                            {{ timeAgo(item.created_at) }}
                        </time>
                    </div>
                    <Button
                        v-if="!item.read"
                        variant="ghost"
                        size="sm"
                        class="shrink-0 text-xs"
                        @click="markRead(item)"
                    >
                        Mark read
                    </Button>
                </li>
            </ul>

            <Pagination :paginator="notifications" noun="notifications" />
        </section>
    </div>
</template>
