<script setup lang="ts">
import { X } from '@lucide/vue';
import { cn } from '@/lib/utils';

/**
 * A date field using the browser's own date picker (keyboard and screen-reader
 * friendly everywhere), with a button to clear it.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: string;
    min?: string;
    clearLabel?: string;
}>();

const model = defineModel<string | null>({ default: null });
</script>

<template>
    <div :class="cn('relative', props.class)">
        <input
            v-bind="$attrs"
            :value="model ?? ''"
            type="date"
            :min="min"
            class="h-9 w-full rounded-md border border-input bg-transparent px-3 pr-9 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30 dark:[color-scheme:dark]"
            @input="model = ($event.target as HTMLInputElement).value || null"
        />
        <button
            v-if="model"
            type="button"
            class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
            :aria-label="clearLabel ?? 'Clear date'"
            @click="model = null"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
