<script setup lang="ts">
import { Pencil } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Text that turns into a field when clicked. Enter (or Ctrl+Enter for long
 * text) saves; Escape cancels.
 */
const props = withDefaults(
    defineProps<{
        value: string | null;
        label: string;
        editable: boolean;
        multiline?: boolean;
        placeholder?: string;
        maxlength?: number;
        class?: string;
        error?: string;
    }>(),
    { multiline: false, placeholder: 'Add…', maxlength: 200 },
);

const emit = defineEmits<{ save: [value: string] }>();

const editing = ref(false);
const draft = ref('');
const field = ref<{ $el: HTMLElement } | null>(null);

async function start() {
    if (!props.editable) {
        return;
    }

    draft.value = props.value ?? '';
    editing.value = true;
    await nextTick();
    (field.value?.$el as HTMLInputElement | undefined)?.focus();
}

function save() {
    if (draft.value !== (props.value ?? '')) {
        emit('save', draft.value);
    }

    editing.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        editing.value = false;
    } else if (
        event.key === 'Enter' &&
        (!props.multiline || event.metaKey || event.ctrlKey)
    ) {
        event.preventDefault();
        save();
    }
}
</script>

<template>
    <div :class="props.class">
        <template v-if="editing">
            <Textarea
                v-if="multiline"
                ref="field"
                v-model="draft"
                :aria-label="label"
                :maxlength="maxlength"
                rows="5"
                @keydown="onKeydown"
            />
            <Input
                v-else
                ref="field"
                v-model="draft"
                :aria-label="label"
                :maxlength="maxlength"
                @keydown="onKeydown"
            />
            <div class="mt-2 flex gap-2">
                <Button size="sm" @click="save">Save</Button>
                <Button size="sm" variant="ghost" @click="editing = false"
                    >Cancel</Button
                >
            </div>
        </template>
        <button
            v-else
            type="button"
            :disabled="!editable"
            :class="
                cn(
                    'group -mx-2 flex w-[calc(100%+1rem)] items-start gap-2 rounded-md px-2 py-1 text-left',
                    editable && 'hover:bg-accent/50',
                )
            "
            :aria-label="editable ? `Edit ${label}` : undefined"
            @click="start"
        >
            <span
                :class="
                    cn(
                        'min-w-0 flex-1 break-words',
                        multiline && 'whitespace-pre-line',
                        !value && 'text-muted-foreground',
                    )
                "
            >
                <slot :value="value">{{ value || placeholder }}</slot>
            </span>
            <Pencil
                v-if="editable"
                class="mt-1 size-3.5 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100"
                aria-hidden="true"
            />
        </button>
        <p v-if="error" class="mt-1 text-sm text-danger-text">{{ error }}</p>
    </div>
</template>
