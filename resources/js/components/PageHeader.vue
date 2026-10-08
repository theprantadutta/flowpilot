<script setup lang="ts">
/**
 * The title block at the top of a page, with an optional description and
 * actions aligned to the right (stacked below on small screens).
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <slot name="eyebrow" />
            <h1
                class="text-2xl leading-tight font-semibold text-balance text-foreground"
            >
                {{ title }}
            </h1>
            <p
                v-if="description || $slots.description"
                class="max-w-2xl text-sm text-pretty text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
