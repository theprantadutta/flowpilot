<script setup lang="ts">
import { formatValue, seriesColor } from '@/lib/charts';
import type { ChartSeries, ValueFormat } from '@/types/reports';

/**
 * The readout for one position on a chart: every series at that point, value
 * first, keyed by a short stroke of the series colour.
 */
defineProps<{
    title: string;
    series: ChartSeries[];
    index: number;
    format: ValueFormat;
    currency?: string;
    total?: number | null;
}>();
</script>

<template>
    <div
        role="status"
        class="pointer-events-none min-w-36 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
    >
        <p class="mb-1.5 font-medium text-muted-foreground">{{ title }}</p>
        <ul class="grid gap-1">
            <li
                v-for="item in series"
                :key="item.key"
                class="flex items-center gap-2 whitespace-nowrap"
            >
                <span
                    class="h-0.5 w-3 shrink-0 rounded-full"
                    :style="{ backgroundColor: seriesColor(item.color) }"
                    aria-hidden="true"
                />
                <span class="figures font-semibold">{{
                    formatValue(item.values[index], format, currency)
                }}</span>
                <span class="text-muted-foreground">{{ item.label }}</span>
            </li>
        </ul>
        <p
            v-if="total !== undefined && total !== null && series.length > 1"
            class="mt-1.5 border-t pt-1.5"
        >
            <span class="figures font-semibold">{{
                formatValue(total, format, currency)
            }}</span>
            <span class="text-muted-foreground"> in total</span>
        </p>
    </div>
</template>
