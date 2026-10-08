<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellOff, CheckCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    open as openNotification,
    readAll,
    recent,
} from '@/routes/notifications';
import type { NotificationItem } from '@/types/activity';

const page = usePage();
const unread = computed(() => Number(page.props.unreadNotifications ?? 0));

const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const items = ref<NotificationItem[]>([]);

const toneDot: Record<string, string> = {
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    flow: 'bg-flow',
    ai: 'bg-ai',
    neutral: 'bg-neutral-text',
};

async function load() {
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(recent().url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const data = (await response.json()) as { items: NotificationItem[] };
        items.value = data.items;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function onOpenChange(value: boolean) {
    open.value = value;

    if (value) {
        void load();
    }
}

function markAllRead() {
    router.visit(readAll(), {
        preserveScroll: true,
        preserveState: true,
        only: ['unreadNotifications'],
        onSuccess: () => {
            items.value = items.value.map((item) => ({ ...item, read: true }));
        },
    });
}
</script>

<template>
    <Popover :open="open" @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="
                    unread > 0
                        ? `Notifications, ${unread} unread`
                        : 'Notifications'
                "
            >
                <Bell />
                <span
                    v-if="unread > 0"
                    class="absolute top-1 right-1 flex h-4 min-w-4 animate-arrive items-center justify-center rounded-full bg-primary px-1 figures text-[0.625rem] font-semibold text-primary-foreground ring-2 ring-background"
                    aria-hidden="true"
                >
                    {{ unread > 99 ? '99+' : unread }}
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent align="end" class="w-[min(24rem,calc(100vw-2rem))] p-0">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <p class="font-display text-sm font-semibold">Notifications</p>
                <Button
                    v-if="unread > 0"
                    variant="ghost"
                    size="sm"
                    class="h-7 text-xs"
                    @click="markAllRead"
                >
                    <CheckCheck />
                    Mark all read
                </Button>
            </div>

            <div class="max-h-[22rem] scrollbar-thin overflow-y-auto">
                <div v-if="loading && items.length === 0" class="space-y-3 p-4">
                    <div v-for="n in 3" :key="n" class="flex gap-3">
                        <Skeleton class="mt-1 size-2 rounded-full" />
                        <div class="flex-1 space-y-1.5">
                            <Skeleton class="h-3.5 w-3/4" />
                            <Skeleton class="h-3 w-1/2" />
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="failed"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Notifications could not be loaded.
                    <button
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="load"
                    >
                        Try again
                    </button>
                </div>

                <div
                    v-else-if="items.length === 0"
                    class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                >
                    <BellOff
                        class="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="text-sm font-medium">You are all caught up</p>
                    <p class="text-xs text-muted-foreground">
                        Approvals, assignments and workflow results will show up
                        here.
                    </p>
                </div>

                <ul v-else class="divide-y">
                    <li v-for="item in items" :key="item.id">
                        <Link
                            :href="openNotification({ notification: item.id })"
                            :class="
                                cn(
                                    'flex gap-3 px-4 py-3 transition-colors hover:bg-accent/60 focus-visible:bg-accent/60 focus-visible:outline-none',
                                    !item.read && 'bg-info-soft/40',
                                )
                            "
                            @click="open = false"
                        >
                            <span
                                :class="
                                    cn(
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        item.read
                                            ? 'bg-transparent ring-1 ring-border-strong'
                                            : (toneDot[item.tone] ?? 'bg-info'),
                                    )
                                "
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    :class="
                                        cn(
                                            'block text-sm leading-snug',
                                            item.read
                                                ? 'text-muted-foreground'
                                                : 'font-medium text-foreground',
                                        )
                                    "
                                >
                                    {{ item.title }}
                                    <span v-if="!item.read" class="sr-only"
                                        >(unread)</span
                                    >
                                </span>
                                <span
                                    v-if="item.body"
                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                    >{{ item.body }}</span
                                >
                                <span
                                    class="mt-1 block text-[0.6875rem] text-muted-foreground"
                                >
                                    {{ timeAgo(item.created_at) }}
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>

            <div class="border-t p-1.5">
                <Button
                    as-child
                    variant="ghost"
                    size="sm"
                    class="w-full justify-center text-xs"
                >
                    <Link :href="notificationsIndex()" @click="open = false">
                        See all notifications
                    </Link>
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
