<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarX } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { cn } from '@/lib/utils';
import { show } from '@/routes/tasks';
import type { TaskItem } from '@/types/operations';

/**
 * Dated tasks laid out week by week, with a marker for today, so slips and
 * crowded weeks stand out.
 */
const props = defineProps<{ tasks: TaskItem[] }>();

function startOfWeek(date: Date): Date {
    const copy = new Date(date);
    const offset = (copy.getDay() + 6) % 7; // Monday first
    copy.setDate(copy.getDate() - offset);
    copy.setHours(0, 0, 0, 0);

    return copy;
}

const todayWeek = startOfWeek(new Date()).getTime();

const weeks = computed(() => {
    const groups = new Map<number, TaskItem[]>();

    for (const task of props.tasks) {
        if (!task.due_date) {
            continue;
        }

        const week = startOfWeek(
            new Date(`${task.due_date}T00:00:00`),
        ).getTime();
        groups.set(week, [...(groups.get(week) ?? []), task]);
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a - b)
        .map(([week, tasks]) => ({
            week,
            label: new Intl.DateTimeFormat(undefined, {
                month: 'short',
                day: 'numeric',
            }).format(new Date(week)),
            isCurrent: week === todayWeek,
            isPast: week < todayWeek,
            tasks,
        }));
});

const dayLabel = (date: string) =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
</script>

<template>
    <EmptyState
        v-if="weeks.length === 0"
        :icon="CalendarX"
        title="Nothing scheduled"
        description="Give tasks due dates and they will be laid out here week by week."
        compact
    />

    <ol v-else class="relative ml-3 border-l pl-6">
        <li
            v-for="week in weeks"
            :key="week.week"
            class="relative pb-8 last:pb-0"
        >
            <span
                :class="
                    cn(
                        'absolute top-1 -left-[1.9rem] size-3 rounded-full ring-4 ring-background',
                        week.isCurrent
                            ? 'bg-primary'
                            : week.isPast
                              ? 'bg-border-strong'
                              : 'bg-card ring-1 ring-border-strong',
                    )
                "
                aria-hidden="true"
            />
            <p class="flex items-center gap-2 text-sm font-semibold">
                Week of {{ week.label }}
                <span
                    v-if="week.isCurrent"
                    class="rounded bg-info-soft px-1.5 py-px text-[0.6875rem] font-medium text-info-text"
                    >This week</span
                >
            </p>
            <ul class="mt-3 grid gap-2">
                <li v-for="task in week.tasks" :key="task.id">
                    <Link
                        :href="show({ task: task.id })"
                        :class="
                            cn(
                                'flex flex-wrap items-center gap-3 rounded-lg border bg-card px-3 py-2.5 text-sm shadow-xs transition-colors hover:border-border-strong',
                                task.is_overdue && 'border-danger/40',
                            )
                        "
                    >
                        <span
                            class="w-14 shrink-0 figures text-xs text-muted-foreground"
                            >{{ dayLabel(task.due_date!) }}</span
                        >
                        <span class="min-w-0 flex-1 truncate">
                            <span
                                class="mr-1.5 figures text-xs text-muted-foreground"
                                >{{ task.reference }}</span
                            >
                            <span
                                :class="
                                    task.status.value === 'done' &&
                                    'text-muted-foreground line-through'
                                "
                                >{{ task.title }}</span
                            >
                        </span>
                        <span
                            v-if="task.is_overdue"
                            class="text-xs font-medium text-danger-text"
                            >Overdue</span
                        >
                        <EnumBadge :option="task.status" />
                        <MemberAvatar
                            v-if="task.assignee"
                            :name="task.assignee.name"
                            :avatar="task.assignee.avatar"
                            class="size-6 text-[0.625rem]"
                        />
                    </Link>
                </li>
            </ul>
        </li>
    </ol>
</template>
