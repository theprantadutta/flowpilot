<script setup lang="ts">
import { formatValue } from '@/lib/charts';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { UsageLine } from '@/types/billing';

/**
 * Usage against each plan limit, as labelled meters. The text always says
 * the numbers; the bar and its colour only reinforce them.
 */
const props = withDefaults(
    defineProps<{
        usage: UsageLine[];
        /** Shown under a limit that is used up. */
        usedUpMessage?: string;
        class?: string;
    }>(),
    {
        usedUpMessage: 'Used up. Nothing more can be added until you upgrade.',
        class: undefined,
    },
);

function usagePercent(line: UsageLine): number | null {
    if (line.limit === null) {
        return null;
    }

    if (line.limit === 0) {
        return line.used > 0 ? 100 : 0;
    }

    return Math.min(100, Math.round((line.used / line.limit) * 100));
}

function usageText(line: UsageLine): string {
    const amount = (value: number) =>
        line.unit === 'mb'
            ? formatValue(value * 1_048_576, 'bytes')
            : formatNumber(value);

    if (line.limit === null) {
        return `${amount(line.used)} used, no limit`;
    }

    if (line.limit === 0) {
        return 'Not included';
    }

    return `${amount(line.used)} of ${amount(line.limit)}`;
}

function meterTone(percent: number | null): string {
    if (percent === null || percent < 80) {
        return 'bg-primary';
    }

    return percent >= 100 ? 'bg-danger' : 'bg-warning';
}
</script>

<template>
    <ul
        :class="cn('grid gap-x-8 gap-y-5 sm:grid-cols-2', props.class)"
        aria-label="Usage"
    >
        <li v-for="line in usage" :key="line.key" class="grid gap-1.5">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="font-medium">{{ line.label }}</span>
                <span class="figures text-muted-foreground">{{
                    usageText(line)
                }}</span>
            </div>
            <div
                v-if="line.limit !== null && line.limit > 0"
                class="h-2 overflow-hidden rounded-full bg-primary/15"
                role="meter"
                :aria-label="line.label"
                aria-valuemin="0"
                :aria-valuemax="line.limit"
                :aria-valuenow="Math.min(line.used, line.limit)"
                :aria-valuetext="usageText(line)"
            >
                <div
                    :class="
                        cn('h-full rounded-full', meterTone(usagePercent(line)))
                    "
                    :style="{ width: `${usagePercent(line)}%` }"
                />
            </div>
            <p
                v-if="(usagePercent(line) ?? 0) >= 100 && line.limit !== 0"
                class="text-xs text-danger-text"
            >
                {{ usedUpMessage }}
            </p>
        </li>
    </ul>
</template>
