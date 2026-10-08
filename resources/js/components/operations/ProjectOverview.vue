<script setup lang="ts">
import { CalendarRange, Coins, Flag, Users } from '@lucide/vue';
import { computed } from 'vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { formatDate, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, ProjectItem } from '@/types/operations';

const props = defineProps<{
    project: ProjectItem;
    summary?: {
        by_status: Record<string, number>;
        overdue: number;
        due_this_week: number;
        critical_issues: number;
    };
    taskStatuses: EnumOption[];
}>();

const total = computed(() => props.project.tasks_count ?? 0);
const open = computed(
    () => total.value - (props.project.done_tasks_count ?? 0),
);

const segmentColors: Record<string, string> = {
    neutral: 'bg-neutral-text/40',
    info: 'bg-info',
    flow: 'bg-flow',
    danger: 'bg-danger',
    warning: 'bg-warning',
    success: 'bg-success',
};

const segments = computed(() =>
    props.taskStatuses
        .map((status) => ({
            status,
            count: props.summary?.by_status[status.value] ?? 0,
        }))
        .filter((segment) => segment.count > 0),
);

const metrics = computed(() => [
    {
        label: 'Complete',
        value: `${props.project.progress ?? 0}%`,
        tone: 'text-foreground',
    },
    { label: 'Open tasks', value: open.value, tone: 'text-foreground' },
    {
        label: 'Overdue',
        value: props.summary?.overdue ?? 0,
        tone:
            (props.summary?.overdue ?? 0) > 0
                ? 'text-danger-text'
                : 'text-foreground',
    },
    {
        label: 'Due in 7 days',
        value: props.summary?.due_this_week ?? 0,
        tone: 'text-foreground',
    },
    {
        label: 'Open issues',
        value: props.project.open_issues_count ?? 0,
        tone:
            (props.summary?.critical_issues ?? 0) > 0
                ? 'text-danger-text'
                : 'text-foreground',
    },
]);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="grid h-fit gap-6">
            <dl
                class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-5"
            >
                <div
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="bg-card px-4 py-4"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ metric.label }}
                    </dt>
                    <dd
                        :class="
                            cn(
                                'mt-1 font-display figures text-2xl font-semibold',
                                metric.tone,
                            )
                        "
                    >
                        {{ metric.value }}
                    </dd>
                </div>
            </dl>

            <section class="rounded-xl border bg-card p-5 shadow-xs">
                <h2 class="font-display text-sm font-semibold">
                    Where the work is
                </h2>
                <p
                    v-if="total === 0"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    No tasks yet. Break the project into tasks to track progress
                    here.
                </p>
                <template v-else>
                    <div
                        class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-secondary"
                        role="img"
                        :aria-label="
                            segments
                                .map((s) => `${s.count} ${s.status.label}`)
                                .join(', ')
                        "
                    >
                        <div
                            v-for="segment in segments"
                            :key="segment.status.value"
                            :class="
                                segmentColors[segment.status.tone] ?? 'bg-info'
                            "
                            :style="{
                                width: `${(segment.count / total) * 100}%`,
                            }"
                        />
                    </div>
                    <ul
                        class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3"
                    >
                        <li
                            v-for="segment in segments"
                            :key="segment.status.value"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <EnumBadge
                                :option="segment.status"
                                variant="plain"
                            />
                            <span class="figures text-muted-foreground">{{
                                segment.count
                            }}</span>
                        </li>
                    </ul>
                </template>
            </section>

            <section class="rounded-xl border bg-card p-5 shadow-xs">
                <h2 class="font-display text-sm font-semibold">
                    About this project
                </h2>
                <p
                    class="mt-2 text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                >
                    {{
                        project.description ||
                        'No description yet. Add one so everyone knows what done looks like.'
                    }}
                </p>
                <ul
                    v-if="project.tags.length"
                    class="mt-4 flex flex-wrap gap-1.5"
                >
                    <li
                        v-for="tag in project.tags"
                        :key="tag"
                        class="rounded-md bg-secondary px-2 py-0.5 text-xs font-medium"
                    >
                        {{ tag }}
                    </li>
                </ul>
            </section>
        </div>

        <aside class="h-fit rounded-xl border bg-card shadow-xs">
            <dl class="divide-y text-sm">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Flag class="size-4" aria-hidden="true" />Priority
                    </dt>
                    <dd>
                        <EnumBadge :option="project.priority" variant="plain" />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-muted-foreground">Owner</dt>
                    <dd>
                        <span
                            v-if="project.owner"
                            class="inline-flex items-center gap-2"
                        >
                            <MemberAvatar
                                :name="project.owner.name"
                                :avatar="project.owner.avatar"
                                class="size-6 text-[0.625rem]"
                            />
                            {{ project.owner.name }}
                        </span>
                        <span v-else class="text-muted-foreground">Nobody</span>
                    </dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <CalendarRange class="size-4" aria-hidden="true" />Dates
                    </dt>
                    <dd class="mt-1.5 flex items-center justify-between gap-2">
                        <span>{{
                            project.start_date
                                ? formatDate(project.start_date)
                                : 'No start date'
                        }}</span>
                        <span class="text-muted-foreground">to</span>
                        <DueDate
                            :date="project.due_date"
                            :overdue="project.is_overdue"
                            :done="project.status.value === 'completed'"
                            bare
                        />
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Coins class="size-4" aria-hidden="true" />Budget
                    </dt>
                    <dd class="figures font-medium">
                        {{
                            project.budget
                                ? formatMoney(
                                      project.budget.amount,
                                      project.budget.currency,
                                  )
                                : '—'
                        }}
                    </dd>
                </div>
                <div class="px-5 py-3">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Users class="size-4" aria-hidden="true" />Members
                    </dt>
                    <dd class="mt-2 grid gap-2">
                        <span
                            v-for="member in project.members ?? []"
                            :key="member.id"
                            class="flex items-center gap-2"
                        >
                            <MemberAvatar
                                :name="member.name"
                                :avatar="member.avatar"
                                class="size-6 text-[0.625rem]"
                            />
                            {{ member.name }}
                        </span>
                        <span
                            v-if="!project.members?.length"
                            class="text-muted-foreground"
                            >No members yet</span
                        >
                    </dd>
                </div>
            </dl>
        </aside>
    </div>
</template>
