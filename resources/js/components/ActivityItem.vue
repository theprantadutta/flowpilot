<script setup lang="ts">
import NamedIcon from '@/components/NamedIcon.vue';
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ActivityEntry } from '@/types/activity';

/**
 * One line of history: who did what, and when. Used in feeds and the audit log.
 */
withDefaults(
    defineProps<{
        entry: ActivityEntry;
        timezone?: string;
        /** Draw the connector line to the next entry. */
        connected?: boolean;
    }>(),
    { timezone: undefined, connected: false },
);

const toneClasses: Record<string, string> = {
    neutral: 'bg-neutral-soft text-neutral-text',
    info: 'bg-info-soft text-info-text',
    success: 'bg-success-soft text-success-text',
    warning: 'bg-warning-soft text-warning-text',
    danger: 'bg-danger-soft text-danger-text',
    flow: 'bg-flow-soft text-flow-text',
    ai: 'bg-ai-soft text-ai-text',
};
</script>

<template>
    <li class="relative flex gap-3 pb-5 last:pb-0">
        <span
            v-if="connected"
            aria-hidden="true"
            class="absolute top-8 bottom-0 left-[0.9375rem] w-px bg-border"
        />
        <span
            :class="
                cn(
                    'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full',
                    toneClasses[entry.tone] ?? toneClasses.info,
                )
            "
        >
            <NamedIcon :name="entry.icon" class="size-4" />
        </span>
        <div class="min-w-0 flex-1 pt-1">
            <p class="text-sm leading-snug text-pretty">
                <span class="font-medium text-foreground">{{
                    entry.actor
                }}</span>
                {{ ' ' }}
                <span class="text-muted-foreground">{{ entry.summary }}</span>
            </p>
            <time
                :datetime="entry.created_at"
                :title="formatDateTime(entry.created_at, timezone)"
                class="text-xs text-muted-foreground"
            >
                {{ timeAgo(entry.created_at) }}
            </time>
        </div>
    </li>
</template>
