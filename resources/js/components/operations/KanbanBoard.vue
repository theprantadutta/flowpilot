<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { VueDraggable } from 'vue-draggable-plus';
import type { SortableEvent } from 'vue-draggable-plus';
import { toast } from 'vue-sonner';
import { reactive, watch } from 'vue';
import NamedIcon from '@/components/NamedIcon.vue';
import TaskCard from '@/components/operations/TaskCard.vue';
import { sendJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { move as moveRoute } from '@/routes/tasks';
import type { EnumOption, TaskItem } from '@/types/operations';

/**
 * Tasks in columns by status. Dragging a card saves its new column and place
 * straight away; if saving fails the card goes back and the reason is shown.
 */
const props = defineProps<{
    tasks: TaskItem[];
    statuses: EnumOption[];
    canCreate: boolean;
    /** Whether the viewer may move cards at all (each card also checks its own permission). */
    canMove: boolean;
}>();

const emit = defineEmits<{ create: [status: string] }>();

const columns = reactive<Record<string, TaskItem[]>>({});

function rebuild() {
    for (const status of props.statuses) {
        columns[status.value] = props.tasks
            .filter((task) => task.status.value === status.value)
            .sort((a, b) => a.position - b.position);
    }
}

watch(() => [props.tasks, props.statuses], rebuild, { immediate: true });

const columnTone: Record<string, string> = {
    neutral: 'text-muted-foreground',
    info: 'text-info-text',
    flow: 'text-flow-text',
    danger: 'text-danger-text',
    warning: 'text-warning-text',
    success: 'text-success-text',
};

async function persist(task: TaskItem, status: string) {
    const list = columns[status];
    const index = list.findIndex((item) => item.id === task.id);
    const statusOption = props.statuses.find(
        (option) => option.value === status,
    );

    if (statusOption) {
        task.status = statusOption;
    }

    try {
        const saved = await sendJson<{ position: number; status: string }>(
            'PATCH',
            moveRoute({ task: task.id }).url,
            {
                status,
                after_id: list[index - 1]?.id ?? null,
                before_id: list[index + 1]?.id ?? null,
            },
        );
        task.position = saved.position;
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'The card could not be moved.',
        );
        router.reload({ only: ['board'] });
    }
}

function onAdd(event: SortableEvent, status: string) {
    const task = columns[status][event.newIndex ?? 0];

    if (task) {
        void persist(task, status);
    }
}

function onUpdate(event: SortableEvent, status: string) {
    const task = columns[status][event.newIndex ?? 0];

    if (task) {
        void persist(task, status);
    }
}

/** Keyboard and menu alternative to dragging: move to the end of a column. */
function moveTo(task: TaskItem, status: string) {
    const from = columns[task.status.value];
    columns[task.status.value] = from.filter((item) => item.id !== task.id);
    columns[status] = [...columns[status], task];
    void persist(task, status);
}
</script>

<template>
    <div
        class="-mx-4 scrollbar-thin overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6"
    >
        <div class="flex min-w-max gap-4">
            <section
                v-for="status in statuses"
                :key="status.value"
                :aria-label="`${status.label}, ${columns[status.value]?.length ?? 0} tasks`"
                class="flex w-72 shrink-0 flex-col rounded-xl bg-muted/50 dark:bg-muted/40"
            >
                <header class="flex items-center gap-2 px-3 pt-3 pb-2">
                    <NamedIcon
                        :name="status.icon"
                        :class="cn('size-4', columnTone[status.tone])"
                    />
                    <h2 class="text-sm font-semibold">{{ status.label }}</h2>
                    <span class="figures text-xs text-muted-foreground">{{
                        columns[status.value]?.length ?? 0
                    }}</span>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="ml-auto flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
                        :aria-label="`Add a task to ${status.label}`"
                        @click="emit('create', status.value)"
                    >
                        <Plus class="size-4" />
                    </button>
                </header>

                <VueDraggable
                    v-model="columns[status.value]"
                    group="tasks"
                    :animation="160"
                    :disabled="!canMove"
                    ghost-class="opacity-40"
                    filter="button, a, [role=menuitem]"
                    :prevent-on-filter="false"
                    class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-3"
                    @add="(event: SortableEvent) => onAdd(event, status.value)"
                    @update="
                        (event: SortableEvent) => onUpdate(event, status.value)
                    "
                >
                    <TaskCard
                        v-for="task in columns[status.value]"
                        :key="task.id"
                        :task="task"
                        :statuses="statuses"
                        :can-move="canMove && !!task.can?.update"
                        @move="(target: string) => moveTo(task, target)"
                    />
                </VueDraggable>

                <p
                    v-if="(columns[status.value]?.length ?? 0) === 0"
                    class="pointer-events-none -mt-16 px-4 pb-4 text-center text-xs text-muted-foreground"
                >
                    Drop a task here
                </p>
            </section>
        </div>
    </div>
</template>
