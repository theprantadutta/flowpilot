<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';
import type { Tone } from '@/types';

/**
 * A status pill. Colour reinforces the label; it never replaces it, so the
 * text (and optional icon) must carry the meaning on its own.
 */
const props = withDefaults(
    defineProps<{
        tone?: Tone;
        icon?: Component;
        /** Show a leading dot when there is no icon. */
        dot?: boolean;
        /** Animate the dot, for things that are in progress right now. */
        live?: boolean;
        class?: string;
    }>(),
    { tone: 'neutral', dot: true, live: false },
);

const toneClasses: Record<Tone, { pill: string; dot: string }> = {
    neutral: {
        pill: 'bg-neutral-soft text-neutral-text',
        dot: 'bg-neutral-text/70',
    },
    info: { pill: 'bg-info-soft text-info-text', dot: 'bg-info' },
    success: { pill: 'bg-success-soft text-success-text', dot: 'bg-success' },
    warning: { pill: 'bg-warning-soft text-warning-text', dot: 'bg-warning' },
    danger: { pill: 'bg-danger-soft text-danger-text', dot: 'bg-danger' },
    flow: { pill: 'bg-flow-soft text-flow-text', dot: 'bg-flow' },
    ai: { pill: 'bg-ai-soft text-ai-text', dot: 'bg-ai' },
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex h-6 shrink-0 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium whitespace-nowrap',
                toneClasses[props.tone].pill,
                props.class,
            )
        "
    >
        <component :is="icon" v-if="icon" class="size-3.5" aria-hidden="true" />
        <span
            v-else-if="dot"
            aria-hidden="true"
            :class="
                cn(
                    'size-1.5 rounded-full',
                    toneClasses[props.tone].dot,
                    live && 'animate-pulse-ring',
                )
            "
        />
        <slot />
    </span>
</template>
