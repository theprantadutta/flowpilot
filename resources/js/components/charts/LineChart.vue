<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import ChartTooltip from '@/components/charts/ChartTooltip.vue';
import { formatTick, formatValue, niceScale, seriesColor } from '@/lib/charts';
import type { ChartData } from '@/types/reports';

/**
 * Lines along a timeline for rates and averages. 2px lines, the last value
 * labelled at the end of each line, and a crosshair that snaps to the nearest
 * period on hover or with the arrow keys. Gaps in the data stay gaps.
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

const labelled = computed(() => props.chart.series.length <= 4);
const padding = computed(() => ({
    top: 12,
    right: labelled.value ? 56 : 12,
    bottom: 28,
    left: 48,
}));

const count = computed(() => props.chart.labels.length);
const plotWidth = computed(() =>
    Math.max(0, width.value - padding.value.left - padding.value.right),
);
const band = computed(() =>
    count.value > 0 ? plotWidth.value / count.value : 0,
);

const scale = computed(() =>
    niceScale(
        Math.max(
            0,
            ...props.chart.series.flatMap((series) =>
                series.values.map((value) => value ?? 0),
            ),
        ),
    ),
);

function x(index: number): number {
    return padding.value.left + band.value * (index + 0.5);
}

function y(value: number): number {
    return (
        padding.value.top +
        props.height -
        (value / scale.value.max) * props.height
    );
}

const lines = computed(() =>
    props.chart.series.map((series) => {
        let path = '';
        let drawing = false;
        let last: { index: number; value: number } | null = null;

        series.values.forEach((value, index) => {
            if (value === null) {
                drawing = false;

                return;
            }

            path += `${drawing ? 'L' : 'M'}${x(index)},${y(value)} `;
            drawing = true;
            last = { index, value };
        });

        const end = last as { index: number; value: number } | null;

        return {
            key: series.key,
            color: seriesColor(series.color),
            path: path.trim(),
            end,
            // A soft wash under a lone series.
            area:
                props.chart.series.length === 1 && path !== ''
                    ? `${path.trim()} V${y(0)} H${x(series.values.findIndex((value) => value !== null))} Z`
                    : null,
        };
    }),
);

/** End labels, dropping any that would sit on top of one already placed. */
const endLabels = computed(() => {
    if (!labelled.value) {
        return [];
    }

    const placed: number[] = [];

    return lines.value
        .filter((line) => line.end !== null)
        .map((line) => {
            const series = props.chart.series.find(
                (item) => item.key === line.key,
            );
            const top = y(line.end!.value);

            if (placed.some((other) => Math.abs(other - top) < 14)) {
                return null;
            }

            placed.push(top);

            return {
                key: line.key,
                x: x(line.end!.index) + 8,
                y: top,
                text: formatValue(
                    line.end!.value,
                    props.chart.format,
                    props.currency,
                ),
                label: series?.label ?? '',
            };
        })
        .filter((label) => label !== null);
});

const labelStep = computed(() =>
    Math.max(
        1,
        Math.ceil(count.value / Math.max(1, Math.floor(plotWidth.value / 56))),
    ),
);

const active = ref<number | null>(null);

function track(event: PointerEvent) {
    const bounds = (
        event.currentTarget as SVGRectElement
    ).getBoundingClientRect();
    const index = Math.floor((event.clientX - bounds.left) / band.value);

    active.value = Math.min(count.value - 1, Math.max(0, index));
}

function step(delta: number) {
    const from = active.value ?? (delta > 0 ? -1 : count.value);

    active.value = Math.min(count.value - 1, Math.max(0, from + delta));
}

const tooltipStyle = computed(() => {
    if (active.value === null) {
        return {};
    }

    const center = x(active.value);
    const flip = center > width.value - 190;

    return {
        left: `${flip ? center - 12 : center + 12}px`,
        top: `${padding.value.top}px`,
        transform: flip ? 'translateX(-100%)' : undefined,
    };
});

const summary = computed(() =>
    props.chart.series
        .map((series) => {
            const last = [...series.values]
                .reverse()
                .find((value) => value !== null);

            return `${series.label}, latest ${formatValue(last ?? null, props.chart.format, props.currency)}`;
        })
        .join('; '),
);
</script>

<template>
    <div ref="container" class="relative w-full">
        <svg
            v-if="width > 0"
            :width="width"
            :height="height + padding.top + padding.bottom"
            class="block overflow-visible"
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
                        :x="x(index)"
                        :y="height + padding.top + 18"
                        text-anchor="middle"
                        class="fill-muted-foreground text-[11px]"
                    >
                        {{ label }}
                    </text>
                </template>

                <template v-for="line in lines" :key="line.key">
                    <path
                        v-if="line.area"
                        :d="line.area"
                        :fill="line.color"
                        opacity="0.1"
                    />
                    <path
                        :d="line.path"
                        fill="none"
                        :stroke="line.color"
                        stroke-width="2"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <circle
                        v-if="line.end"
                        :cx="x(line.end.index)"
                        :cy="y(line.end.value)"
                        r="4"
                        :fill="line.color"
                        stroke="var(--card)"
                        stroke-width="2"
                    />
                </template>

                <text
                    v-for="label in endLabels"
                    :key="label.key"
                    :x="label.x"
                    :y="label.y"
                    dominant-baseline="middle"
                    class="fill-foreground text-[11px] font-medium tabular-nums"
                >
                    {{ label.text }}
                </text>

                <g v-if="active !== null">
                    <line
                        :x1="x(active)"
                        :x2="x(active)"
                        :y1="padding.top"
                        :y2="padding.top + height"
                        stroke="var(--chart-axis)"
                        stroke-width="1"
                    />
                    <template v-for="series in chart.series" :key="series.key">
                        <circle
                            v-if="series.values[active] !== null"
                            :cx="x(active)"
                            :cy="y(series.values[active] ?? 0)"
                            r="4"
                            :fill="seriesColor(series.color)"
                            stroke="var(--card)"
                            stroke-width="2"
                        />
                    </template>
                </g>
            </g>

            <rect
                :x="padding.left"
                :y="padding.top"
                :width="plotWidth"
                :height="height"
                fill="transparent"
                tabindex="0"
                role="img"
                :aria-label="`${chart.title}. ${summary}. Use the arrow keys to read each period.`"
                class="outline-none focus-visible:stroke-ring focus-visible:stroke-2"
                @pointermove="track"
                @pointerleave="active = null"
                @blur="active = null"
                @keydown.right.prevent="step(1)"
                @keydown.left.prevent="step(-1)"
            />
        </svg>

        <div v-if="active !== null" class="absolute z-10" :style="tooltipStyle">
            <ChartTooltip
                :title="chart.labels[active]"
                :series="chart.series"
                :index="active"
                :format="chart.format"
                :currency="currency"
            />
        </div>
    </div>
</template>
