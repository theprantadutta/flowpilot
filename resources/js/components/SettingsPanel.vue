<script setup lang="ts">
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * A titled settings form with a footer that shows whether there are unsaved
 * changes and confirms when they were saved.
 */
defineProps<{
    title: string;
    description?: string;
    processing?: boolean;
    dirty?: boolean;
    saved?: boolean;
    submitLabel?: string;
}>();

const emit = defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="overflow-hidden rounded-xl border bg-card shadow-xs"
        @submit.prevent="emit('submit')"
    >
        <div class="space-y-1 border-b px-5 py-4 sm:px-6">
            <h2 class="font-display text-base font-semibold">{{ title }}</h2>
            <p
                v-if="description"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>

        <div class="grid gap-6 px-5 py-5 sm:px-6">
            <slot />
        </div>

        <div
            class="flex items-center justify-end gap-3 border-t bg-muted/30 px-5 py-3 sm:px-6"
        >
            <p class="mr-auto text-xs text-muted-foreground" aria-live="polite">
                <span
                    v-if="saved"
                    class="inline-flex items-center gap-1 text-success-text"
                >
                    <Check class="size-3.5" aria-hidden="true" />
                    Saved
                </span>
                <span v-else-if="dirty">You have unsaved changes</span>
            </p>
            <Button type="submit" :disabled="processing || !dirty">
                <Spinner v-if="processing" />
                {{ submitLabel ?? 'Save changes' }}
            </Button>
        </div>
    </form>
</template>
