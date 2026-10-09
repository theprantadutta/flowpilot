<script setup lang="ts">
import { cn } from '@/lib/utils';

/**
 * The title block of a landing page section: a short label, the heading and
 * a sentence or two under it.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        eyebrow: string;
        title: string;
        description?: string;
        align?: 'left' | 'center';
        class?: string;
    }>(),
    { description: undefined, align: 'center', class: undefined },
);
</script>

<template>
    <div
        :class="
            cn(
                'grid max-w-3xl content-start gap-3',
                props.align === 'center' && 'mx-auto text-center',
                props.class,
            )
        "
    >
        <p class="text-sm font-semibold text-primary">{{ eyebrow }}</p>
        <h2
            :id="id"
            class="font-display text-3xl leading-tight font-semibold text-balance sm:text-4xl"
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="text-base text-pretty text-muted-foreground sm:text-lg"
        >
            {{ description }}
        </p>
    </div>
</template>
