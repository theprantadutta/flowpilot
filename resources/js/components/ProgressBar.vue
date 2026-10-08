<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * A thin horizontal meter with its value announced to assistive technology.
 */
const props = withDefaults(
    defineProps<{
        value: number;
        label: string;
        /** Colour role for the filled part. */
        tone?: 'primary' | 'success' | 'warning' | 'danger' | 'flow';
        showValue?: boolean;
        class?: string;
    }>(),
    { tone: 'primary', showValue: false },
);

const clamped = computed(() =>
    Math.max(0, Math.min(100, Math.round(props.value))),
);

const fills: Record<string, string> = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    flow: 'bg-flow',
};
</script>

<template>
    <div :class="cn('flex items-center gap-2.5', props.class)">
        <div
            role="progressbar"
            :aria-label="label"
            :aria-valuenow="clamped"
            aria-valuemin="0"
            aria-valuemax="100"
            class="h-1.5 flex-1 overflow-hidden rounded-full bg-secondary"
        >
            <div
                :class="
                    cn(
                        'h-full rounded-full transition-[width] duration-500',
                        fills[tone],
                    )
                "
                :style="{ width: `${clamped}%` }"
            />
        </div>
        <span
            v-if="showValue"
            class="w-9 text-right figures text-xs font-medium text-muted-foreground"
        >
            {{ clamped }}%
        </span>
    </div>
</template>
