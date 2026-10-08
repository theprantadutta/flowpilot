<script setup lang="ts">
import { CalendarClock } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A due date that says how close it is ("Today", "In 3 days", "2 days late").
 * Overdue dates say so in words as well as colour.
 */
const props = withDefaults(
    defineProps<{
        date: string | null;
        overdue?: boolean;
        done?: boolean;
        /** Hide the icon in dense rows. */
        bare?: boolean;
        class?: string;
    }>(),
    { overdue: false, done: false, bare: false },
);

function daysFromToday(date: string): number {
    const [year, month, day] = date.split('-').map(Number);
    const due = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round((due.getTime() - today.getTime()) / 86_400_000);
}

const text = computed(() => {
    if (!props.date) {
        return 'No due date';
    }

    const days = daysFromToday(props.date);
    const formatted = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(`${props.date}T00:00:00`));

    if (props.done) {
        return formatted;
    }

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    if (days < 0) {
        return `${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'} late`;
    }

    return days <= 6 ? `In ${days} days` : formatted;
});

const tone = computed(() => {
    if (!props.date || props.done) {
        return 'text-muted-foreground';
    }

    if (props.overdue) {
        return 'font-medium text-danger-text';
    }

    return daysFromToday(props.date) <= 1
        ? 'font-medium text-warning-text'
        : 'text-muted-foreground';
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 text-xs whitespace-nowrap',
                tone,
                props.class,
            )
        "
        :title="date ?? undefined"
    >
        <CalendarClock v-if="!bare" class="size-3.5" aria-hidden="true" />
        {{ text }}
    </span>
</template>
