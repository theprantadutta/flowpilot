<script setup lang="ts">
import NamedIcon from '@/components/NamedIcon.vue';
import { STEP_DRAG_TYPE } from '@/lib/workflowCanvas';
import { toneChip } from '@/lib/workflows';
import { cn } from '@/lib/utils';
import type { NodeKind, NodeTypeOption } from '@/types/workflows';

/**
 * The steps that can be added. Click to add one after the selected step (and
 * connect it), or drag one onto the canvas.
 */
defineProps<{ types: NodeTypeOption[] }>();

const emit = defineEmits<{ add: [type: NodeKind] }>();

function onDragStart(event: DragEvent, type: NodeKind) {
    event.dataTransfer?.setData(STEP_DRAG_TYPE, type);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}
</script>

<template>
    <nav aria-label="Steps you can add" class="grid gap-1">
        <button
            v-for="type in types.filter((option) => option.addable)"
            :key="type.value"
            type="button"
            draggable="true"
            class="group flex items-start gap-3 rounded-lg p-2 text-left transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            @click="emit('add', type.value)"
            @dragstart="onDragStart($event, type.value)"
        >
            <span
                :class="
                    cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                        toneChip[type.tone],
                    )
                "
            >
                <NamedIcon :name="type.icon" class="size-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-medium">{{ type.label }}</span>
                <span class="block text-xs text-muted-foreground">{{
                    type.description
                }}</span>
            </span>
        </button>
    </nav>
</template>
