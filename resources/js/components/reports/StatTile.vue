<script setup lang="ts">
import { CircleAlert, CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { formatValue } from '@/lib/charts';
import { cn } from '@/lib/utils';
import type { ValueFormat } from '@/types/reports';

/**
 * A headline figure: label, value and an optional hint. A tone marks the
 * figure as good or bad, always with an icon as well as a colour.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        value: number | string | null;
        format?: ValueFormat;
        hint?: string | null;
        tone?: 'success' | 'warning' | 'danger' | null;
        currency?: string;
        stale?: boolean;
    }>(),
    { format: 'number', hint: null, tone: null, currency: 'USD', stale: false },
);

const icon = computed(
    () =>
        ({ success: CircleCheck, warning: TriangleAlert, danger: CircleAlert })[
            props.tone ?? 'success'
        ],
);

const toneText: Record<string, string> = {
    success: 'text-success-text',
    warning: 'text-warning-text',
    danger: 'text-danger-text',
};
</script>

<template>
    <div
        :class="
            cn(
                'flex min-w-0 flex-col gap-1.5 rounded-xl border bg-card p-4 shadow-xs transition-opacity',
                stale && 'opacity-60',
            )
        "
    >
        <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
            <component
                :is="icon"
                v-if="tone"
                :class="cn('size-4 shrink-0', toneText[tone])"
                aria-hidden="true"
            />
            <span class="truncate">{{ label }}</span>
        </p>
        <p
            :class="
                cn(
                    'font-display font-semibold',
                    typeof value === 'string'
                        ? 'text-lg leading-7'
                        : 'text-2xl',
                    tone && tone !== 'success' && toneText[tone],
                )
            "
        >
            {{ formatValue(value, format, currency) }}
        </p>
        <p v-if="hint" class="text-xs text-pretty text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
