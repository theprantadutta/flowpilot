<script setup lang="ts">
import { X } from '@lucide/vue';
import { ref } from 'vue';

/**
 * Type a tag and press Enter or comma to add it. Backspace on an empty field
 * removes the last one.
 */
const props = withDefaults(
    defineProps<{
        id?: string;
        max?: number;
        placeholder?: string;
    }>(),
    { id: undefined, max: 10, placeholder: 'Add a tag and press Enter' },
);

const model = defineModel<string[]>({ default: () => [] });
const draft = ref('');

function add() {
    const tag = draft.value
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .slice(0, 30);

    if (tag && !model.value.includes(tag) && model.value.length < props.max) {
        model.value = [...model.value, tag];
    }

    draft.value = '';
}

function remove(tag: string) {
    model.value = model.value.filter((item) => item !== tag);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add();
    } else if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        model.value.length > 0
    ) {
        model.value = model.value.slice(0, -1);
    }
}
</script>

<template>
    <div
        class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1.5 shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
    >
        <span
            v-for="tag in model"
            :key="tag"
            class="inline-flex items-center gap-1 rounded-md bg-secondary py-0.5 pr-1 pl-2 text-xs font-medium text-secondary-foreground"
        >
            {{ tag }}
            <button
                type="button"
                class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-label="`Remove tag ${tag}`"
                @click="remove(tag)"
            >
                <X class="size-3" />
            </button>
        </span>
        <input
            :id="id"
            v-model="draft"
            type="text"
            maxlength="30"
            :placeholder="model.length === 0 ? placeholder : ''"
            :disabled="model.length >= max"
            class="min-w-24 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-muted-foreground"
            @keydown="onKeydown"
            @blur="add"
        />
    </div>
</template>
