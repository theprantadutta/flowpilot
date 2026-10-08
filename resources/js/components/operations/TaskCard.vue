<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ListChecks, MessageSquare, MoreVertical } from '@lucide/vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show } from '@/routes/tasks';
import type { EnumOption, TaskItem } from '@/types/operations';

defineProps<{
    task: TaskItem;
    statuses: EnumOption[];
    canMove: boolean;
}>();

const emit = defineEmits<{ move: [status: string] }>();
</script>

<template>
    <article
        class="group relative rounded-lg border bg-card p-3 shadow-xs transition-[border-color,box-shadow] hover:border-border-strong hover:shadow-sm"
        :class="canMove && 'cursor-grab active:cursor-grabbing'"
    >
        <div class="flex items-start gap-2">
            <Link
                :href="show({ task: task.id })"
                class="min-w-0 flex-1 text-sm leading-snug font-medium hover:text-primary focus-visible:outline-2"
            >
                <span
                    class="mr-1 figures text-xs font-normal text-muted-foreground"
                    >{{ task.reference }}</span
                >
                {{ task.title }}
            </Link>
            <DropdownMenu v-if="canMove">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="-mt-0.5 -mr-1 flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground opacity-60 hover:bg-accent hover:opacity-100 focus-visible:opacity-100"
                        :aria-label="`Move ${task.reference}`"
                    >
                        <MoreVertical class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel
                        class="text-xs font-normal text-muted-foreground"
                        >Move to</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-for="status in statuses"
                        :key="status.value"
                        :disabled="status.value === task.status.value"
                        @select="emit('move', status.value)"
                    >
                        <NamedIcon :name="status.icon" class="size-4" />
                        {{ status.label }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <p
            v-if="task.project"
            class="mt-1 truncate text-xs text-muted-foreground"
        >
            {{ task.project.name }}
        </p>

        <div
            class="mt-3 flex items-center gap-2.5 text-xs text-muted-foreground"
        >
            <EnumBadge
                v-if="
                    task.priority.value === 'urgent' ||
                    task.priority.value === 'high'
                "
                :option="task.priority"
                variant="plain"
            />
            <DueDate
                v-if="task.due_date"
                :date="task.due_date"
                :overdue="task.is_overdue"
                :done="task.status.value === 'done'"
                bare
            />
            <span
                v-if="task.checklist_summary && task.checklist_summary.total"
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
                <MessageSquare class="size-3.5" aria-hidden="true" />
                <span class="figures">{{ task.comments_count }}</span>
                <span class="sr-only">comments</span>
            </span>
            <MemberAvatar
                v-if="task.assignee"
                :name="task.assignee.name"
                :avatar="task.assignee.avatar"
                class="ml-auto size-6 text-[0.625rem]"
                :title="task.assignee.name"
            />
        </div>
    </article>
</template>
