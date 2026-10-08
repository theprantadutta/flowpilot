<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ListChecks, MessageSquare } from '@lucide/vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { show } from '@/routes/tasks';
import type { TaskItem } from '@/types/operations';

/**
 * Tasks as a table on wide screens and stacked rows on narrow ones.
 */
withDefaults(
    defineProps<{
        tasks: TaskItem[];
        showProject?: boolean;
    }>(),
    { showProject: true },
);
</script>

<template>
    <table class="hidden w-full text-sm md:table">
        <thead>
            <tr class="border-b text-left text-xs text-muted-foreground">
                <th scope="col" class="py-2.5 pr-3 pl-5 font-medium">Task</th>
                <th scope="col" class="px-3 py-2.5 font-medium">Status</th>
                <th scope="col" class="px-3 py-2.5 font-medium">Priority</th>
                <th scope="col" class="px-3 py-2.5 font-medium">Assignee</th>
                <th scope="col" class="py-2.5 pr-5 pl-3 font-medium">Due</th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="task in tasks"
                :key="task.id"
                class="group border-b last:border-0 hover:bg-accent/40"
            >
                <td class="max-w-0 py-3 pr-3 pl-5">
                    <Link
                        :href="show({ task: task.id })"
                        class="flex min-w-0 items-baseline gap-2 focus-visible:outline-2"
                    >
                        <span
                            class="shrink-0 figures text-xs text-muted-foreground"
                            >{{ task.reference }}</span
                        >
                        <span
                            class="truncate font-medium group-hover:text-primary"
                            :class="
                                task.status.value === 'done' &&
                                'text-muted-foreground line-through decoration-muted-foreground/50'
                            "
                        >
                            {{ task.title }}
                        </span>
                    </Link>
                    <div
                        class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="showProject && task.project"
                            class="truncate"
                            >{{ task.project.name }}</span
                        >
                        <span
                            v-if="
                                task.checklist_summary &&
                                task.checklist_summary.total > 0
                            "
                            class="inline-flex items-center gap-1"
                        >
                            <ListChecks class="size-3.5" aria-hidden="true" />
                            <span class="figures"
                                >{{ task.checklist_summary.done }}/{{
                                    task.checklist_summary.total
                                }}</span
                            >
                        </span>
                        <span
                            v-if="task.comments_count"
                            class="inline-flex items-center gap-1"
                        >
                            <MessageSquare
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <span class="figures">{{
                                task.comments_count
                            }}</span>
                            <span class="sr-only">comments</span>
                        </span>
                    </div>
                </td>
                <td class="px-3 py-3"><EnumBadge :option="task.status" /></td>
                <td class="px-3 py-3">
                    <EnumBadge :option="task.priority" variant="plain" />
                </td>
                <td class="px-3 py-3">
                    <span
                        v-if="task.assignee"
                        class="inline-flex items-center gap-2"
                    >
                        <MemberAvatar
                            :name="task.assignee.name"
                            :avatar="task.assignee.avatar"
                            class="size-6 text-[0.625rem]"
                        />
                        <span class="truncate">{{ task.assignee.name }}</span>
                    </span>
                    <span v-else class="text-muted-foreground">Unassigned</span>
                </td>
                <td class="py-3 pr-5 pl-3">
                    <DueDate
                        :date="task.due_date"
                        :overdue="task.is_overdue"
                        :done="task.status.value === 'done'"
                    />
                </td>
            </tr>
        </tbody>
    </table>

    <ul class="divide-y md:hidden">
        <li v-for="task in tasks" :key="task.id">
            <Link
                :href="show({ task: task.id })"
                class="flex flex-col gap-2 px-4 py-3.5"
            >
                <span class="flex items-baseline gap-2">
                    <span class="figures text-xs text-muted-foreground">{{
                        task.reference
                    }}</span>
                    <span class="font-medium">{{ task.title }}</span>
                </span>
                <span class="flex flex-wrap items-center gap-2">
                    <EnumBadge :option="task.status" />
                    <EnumBadge :option="task.priority" variant="plain" />
                    <DueDate
                        v-if="task.due_date"
                        :date="task.due_date"
                        :overdue="task.is_overdue"
                        :done="task.status.value === 'done'"
                    />
                    <span
                        v-if="task.assignee"
                        class="ml-auto inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <MemberAvatar
                            :name="task.assignee.name"
                            :avatar="task.assignee.avatar"
                            class="size-5 text-[0.625rem]"
                        />
                        {{ task.assignee.name }}
                    </span>
                </span>
            </Link>
        </li>
    </ul>
</template>
