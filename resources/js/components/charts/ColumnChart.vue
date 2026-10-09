<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import ChartTooltip from '@/components/charts/ChartTooltip.vue';
import {
    columnPath,
    formatTick,
    formatValue,
    niceScale,
    seriesColor,
} from '@/lib/charts';
import type { ChartData } from '@/types/reports';

/**
 * Columns along a timeline: one series, several side by side, or stacked as
 * parts of a whole. Thin marks with rounded data ends, a 2px surface gap
 * between stacked segments, and a readout on hover or keyboard focus.
 */
const props = withDefaults(
    defineProps<{
        chart: ChartData;
        currency?: string;
        height?: number;
    }>(),
    { currency: 'USD', height: 200 },
);

const container = ref<HTMLElement | null>(null);
const { width } = useElementSize(container);

const padding = { top: 12, right: 8, bottom: 28, left: 48 };
const GAP = 2;
const MAX_BAR = 24;

const count = computed(() => props.chart.labels.length);
const plotWidth = computed(() =>
    Math.max(0, width.value - padding.left - padding.right),
);
const band = computed(() =>
    count.value > 0 ? plotWidth.value / count.value : 0,
);

const totals = computed(() =>
    props.chart.labels.map((_, index) =>
        props.chart.stacked
            ? props.chart.series.reduce(
                  (sum, series) => sum + (series.values[index] ?? 0),
                  0,
              )
            : Math.max(
                  0,
                  ...props.chart.series.map(
                      (series) => series.values[index] ?? 0,
                  ),
              ),
    ),
);

const scale = computed(() => niceScale(Math.max(0, ...totals.value)));

function y(value: number): number {
    return (
        padding.top + props.height - (value / scale.value.max) * props.height
    );
}

type Mark = { key: string; path: string; color: string };

const marks = computed<Mark[]>(() => {
    const result: Mark[] = [];
    const seriesCount = props.chart.series.length;
    const grouped = !props.chart.stacked && seriesCount > 1;
    const groupWidth = grouped
        ? Math.min(
              band.value * 0.8,
              seriesCount * MAX_BAR + (seriesCount - 1) * GAP,
          )
        : Math.min(MAX_BAR, band.value * 0.7);
    const barWidth = grouped
        ? (groupWidth - (seriesCount - 1) * GAP) / seriesCount
        : groupWidth;

    props.chart.labels.forEach((_, index) => {
        const left =
            padding.left + band.value * index + (band.value - groupWidth) / 2;

        if (props.chart.stacked) {
            const filled = props.chart.series
                .map((series) => ({ series, value: series.values[index] ?? 0 }))
                .filter((entry) => entry.value > 0);
            let base = 0;

            filled.forEach((entry, position) => {
                const isTop = position === filled.length - 1;
                const top = y(base + entry.value);
                const bottom = y(base);
                // The gap sits under every segment except the first.
                const height = bottom - top - (position > 0 ? GAP : 0);

                result.push({
                    key: `${entry.series.key}-${index}`,
                    path: isTop
                        ? columnPath(left, top, groupWidth, height, 4)
                        : `M${left},${top} h${groupWidth} v${Math.max(0, height)} h${-groupWidth} Z`,
                    color: seriesColor(entry.series.color),
                });
                base += entry.value;
            });

            return;
        }

        props.chart.series.forEach((series, position) => {
            const value = series.values[index] ?? 0;

            if (value <= 0) {
                return;
            }

            const x = grouped ? left + position * (barWidth + GAP) : left;
            const top = y(value);

            result.push({
                key: `${series.key}-${index}`,
                path: columnPath(x, top, barWidth, y(0) - top, 4),
                color: seriesColor(series.color),
            });
        });
    });

    return result;
});

/** Show every nth label so they never collide. */
const labelStep = computed(() =>
    Math.max(
        1,
        Math.ceil(count.value / Math.max(1, Math.floor(plotWidth.value / 56))),
    ),
);

const active = ref<number | null>(null);

const tooltipStyle = computed(() => {
    if (active.value === null) {
        return {};
    }

    const center = padding.left + band.value * (active.value + 0.5);
    const flip = center > width.value - 180;

    return {
        left: `${flip ? center - 12 : center + 12}px`,
        top: `${padding.top}px`,
        transform: flip ? 'translateX(-100%)' : undefined,
    };
});

function describe(index: number): string {
    const parts = props.chart.series.map(
        (series) =>
            `${series.label} ${formatValue(series.values[index], props.chart.format, props.currency)}`,
    );

    return `${props.chart.labels[index]}: ${parts.join(', ')}`;
}
</script>

<template>
    <div ref="container" class="relative w-full">
        <svg
            v-if="width > 0"
            :width="width"
            :height="height + padding.top + padding.bottom"
            class="block overflow-visible"
            role="group"
            :aria-label="chart.title"
        >
            <g aria-hidden="true">
                <g v-for="tick in scale.ticks" :key="tick">
                    <line
                        :x1="padding.left"
                        :x2="width - padding.right"
                        :y1="y(tick)"
                        :y2="y(tick)"
                        :stroke="
                            tick === 0
                                ? 'var(--chart-axis)'
                                : 'var(--chart-grid)'
                        "
                        stroke-width="1"
                        shape-rendering="crispEdges"
                    />
                    <text
                        :x="padding.left - 8"
                        :y="y(tick)"
                        text-anchor="end"
                        dominant-baseline="middle"
                        class="fill-muted-foreground text-[11px] tabular-nums"
                    >
                        {{ formatTick(tick, chart.format, currency) }}
                    </text>
                </g>
                <template v-for="(label, index) in chart.labels" :key="index">
                    <text
                        v-if="index % labelStep === 0"
                        :x="padding.left + band * (index + 0.5)"
                        :y="height + padding.top + 18"
                        text-anchor="middle"
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ label }}
                    </text>
                </template>
            </g>

            <rect
                v-if="active !== null"
                :x="padding.left + band * active"
                :y="padding.top"
                :width="band"
                :height="height"
                class="fill-muted"
                opacity="0.6"
                aria-hidden="true"
            />

            <path
                v-for="mark in marks"
                :key="mark.key"
                :d="mark.path"
                :fill="mark.color"
                aria-hidden="true"
            />

            <rect
                v-for="(label, index) in chart.labels"
                :key="`hit-${index}`"
                :x="padding.left + band * index"
                :y="padding.top"
                :width="band"
                :height="height"
                fill="transparent"
                tabindex="0"
                role="img"
                :aria-label="describe(index)"
                class="cursor-default outline-none focus-visible:stroke-ring focus-visible:stroke-2"
                @pointerenter="active = index"
                @pointerleave="active = null"
                @focus="active = index"
                @blur="active = null"
            />
        </svg>

        <div v-if="active !== null" class="absolute z-10" :style="tooltipStyle">
            <ChartTooltip
                :title="chart.labels[active]"
                :series="chart.series"
                :index="active"
                :format="chart.format"
                :currency="currency"
                :total="chart.stacked ? totals[active] : null"
            />
        </div>
    </div>
</template>
