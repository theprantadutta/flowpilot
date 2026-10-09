<script setup lang="ts">
import { computed } from 'vue';
import { formatValue, seriesColor } from '@/lib/charts';
import type { ChartData } from '@/types/reports';

/**
 * Horizontal bars comparing named categories, each labelled with its value.
 * One series, one colour: the length carries the comparison.
 */
const props = withDefaults(
    defineProps<{ chart: ChartData; currency?: string }>(),
    { currency: 'USD' },
);

const series = computed(() => props.chart.series[0]);

const maximum = computed(() => {
    if (props.chart.format === 'percent') {
        return 100;
    }

    return Math.max(
        1,
        ...(series.value?.values ?? []).map((value) => value ?? 0),
    );
});
</script>

<template>
    <ul v-if="series" class="grid gap-3">
        <li
            v-for="(label, index) in chart.labels"
            :key="index"
            class="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto] items-center gap-3 text-sm"
        >
            <span class="truncate" :title="label">{{ label }}</span>
            <span
                class="h-2.5 overflow-hidden rounded-r-full bg-muted"
                aria-hidden="true"
            >
                <span
                    class="block h-full rounded-r-full"
                    :style="{
                        width: `${Math.max(0, Math.min(100, ((series.values[index] ?? 0) / maximum) * 100))}%`,
                        backgroundColor: seriesColor(series.color),
                    }"
                />
            </span>
            <span class="text-right figures font-medium">{{
                formatValue(series.values[index], chart.format, currency)
            }}</span>
        </li>
    </ul>
</template>
