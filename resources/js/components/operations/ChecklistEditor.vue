<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, store, update } from '@/routes/tasks/checklist';
import type { ChecklistItem } from '@/types/operations';

const props = defineProps<{
    taskId: string;
    items: ChecklistItem[];
    editable: boolean;
}>();

const form = useForm({ body: '' });
const done = computed(() => props.items.filter((item) => item.is_done).length);

function add() {
    if (form.body.trim() === '') {
        return;
    }

    form.submit(store({ task: props.taskId }), {
        preserveScroll: true,
        only: ['task'],
        onSuccess: () => form.reset(),
    });
}

function toggle(item: ChecklistItem, value: boolean | 'indeterminate') {
    router.visit(update.patch({ task: props.taskId, checklistItem: item.id }), {
        data: { is_done: value === true },
        preserveScroll: true,
        only: ['task'],
    });
}

function remove(item: ChecklistItem) {
    router.visit(destroy({ task: props.taskId, checklistItem: item.id }), {
        preserveScroll: true,
        only: ['task'],
    });
}
</script>

<template>
    <div class="grid gap-3">
        <ProgressBar
            v-if="items.length"
            :value="(done / items.length) * 100"
            :label="`Checklist: ${done} of ${items.length} done`"
            :tone="done === items.length ? 'success' : 'primary'"
            show-value
        />

        <ul v-if="items.length" class="grid gap-0.5">
            <li
                v-for="item in items"
                :key="item.id"
                class="group flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-accent/50"
            >
                <Checkbox
                    :id="`check-${item.id}`"
                    :model-value="item.is_done"
                    :disabled="!editable"
                    @update:model-value="(value) => toggle(item, value)"
                />
                <label
                    :for="`check-${item.id}`"
                    :class="
                        cn(
                            'flex-1 cursor-pointer text-sm',
                            item.is_done &&
                                'text-muted-foreground line-through',
                        )
                    "
                >
                    {{ item.body }}
                </label>
                <button
                    v-if="editable"
                    type="button"
                    class="flex size-6 items-center justify-center rounded text-muted-foreground opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-accent hover:text-foreground"
                    :aria-label="`Remove ${item.body}`"
                    @click="remove(item)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>

        <form
            v-if="editable"
            class="flex items-center gap-2"
            @submit.prevent="add"
        >
            <Plus
                class="ml-2 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <label for="new-checklist-item" class="sr-only"
                >Add a checklist item</label
            >
            <Input
                id="new-checklist-item"
                v-model="form.body"
                maxlength="500"
                placeholder="Add a step and press Enter"
                class="h-8 border-dashed shadow-none"
            />
        </form>
        <InputError :message="form.errors.body" />
        <p
            v-if="!items.length && !editable"
            class="text-sm text-muted-foreground"
        >
            No checklist.
        </p>
    </div>
</template>
