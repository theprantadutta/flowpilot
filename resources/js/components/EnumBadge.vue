<script setup lang="ts">
import NamedIcon from '@/components/NamedIcon.vue';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/operations';

/**
 * A status, priority or severity: its own icon plus its label, tinted by tone.
 * The icon shape and text carry the meaning; colour only reinforces it.
 */
const props = withDefaults(
    defineProps<{
        option: EnumOption;
        /** "pill" for statuses, "plain" for inline priority/severity text. */
        variant?: 'pill' | 'plain';
        class?: string;
    }>(),
    { variant: 'pill' },
);

const pill: Record<string, string> = {
    neutral: 'bg-neutral-soft text-neutral-text',
    info: 'bg-info-soft text-info-text',
    success: 'bg-success-soft text-success-text',
    warning: 'bg-warning-soft text-warning-text',
    danger: 'bg-danger-soft text-danger-text',
    flow: 'bg-flow-soft text-flow-text',
    ai: 'bg-ai-soft text-ai-text',
};

const plain: Record<string, string> = {
    neutral: 'text-muted-foreground',
    info: 'text-info-text',
    success: 'text-success-text',
    warning: 'text-warning-text',
    danger: 'text-danger-text',
    flow: 'text-flow-text',
    ai: 'text-ai-text',
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1.5 text-xs font-medium whitespace-nowrap',
                props.variant === 'pill'
                    ? [
                          'h-6 rounded-full px-2.5',
                          pill[option.tone] ?? pill.neutral,
                      ]
                    : (plain[option.tone] ?? plain.neutral),
                props.class,
            )
        "
    >
        <NamedIcon :name="option.icon" class="size-3.5" />
        {{ option.label }}
    </span>
</template>
