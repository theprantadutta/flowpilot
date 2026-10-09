<script setup lang="ts">
import { ChartNoAxesColumn, Table2 } from '@lucide/vue';
import { ref } from 'vue';
import BarList from '@/components/charts/BarList.vue';
import ColumnChart from '@/components/charts/ColumnChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import { Button } from '@/components/ui/button';
import { formatValue, seriesColor } from '@/lib/charts';
import { cn } from '@/lib/utils';
import type { ChartData } from '@/types/reports';

/**
 * A chart with its title, legend and a table view of the same numbers, so
 * every value can be read without hovering or telling colours apart.
 */
const props = withDefaults(
    defineProps<{
        chart: ChartData;
        currency?: string;
        /** Dim while new numbers load, keeping the previous ones in place. */
        stale?: boolean;
        class?: string;
    }>(),
    { currency: 'USD', stale: false },
);

const showTable = ref(false);
</script>

<template>
    <figure
        :class="
            cn(
                'flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs transition-opacity',
                stale && 'opacity-60',
                props.class,
            )
        "
        :aria-busy="stale || undefined"
    >
        <div class="flex items-start justify-between gap-3">
            <figcaption class="min-w-0">
                <h3 class="font-display text-sm font-semibold">
                    {{ chart.title }}
                </h3>
                <p
                    v-if="chart.description"
                    class="text-xs text-muted-foreground"
                >
                    {{ chart.description }}
                </p>
            </figcaption>
            <Button
                v-if="!chart.empty"
                variant="ghost"
                size="sm"
                class="-mt-1 -mr-2 shrink-0"
                :aria-pressed="showTable"
                @click="showTable = !showTable"
            >
                <ChartNoAxesColumn v-if="showTable" />
                <Table2 v-else />
                {{ showTable ? 'Chart' : 'Table' }}
            </Button>
        </div>

        <ul
            v-if="chart.series.length > 1 && !chart.empty"
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            aria-label="Legend"
        >
            <li
                v-for="series in chart.series"
                :key="series.key"
                class="flex items-center gap-1.5"
            >
                <span
                    :class="
                        chart.kind === 'line'
                            ? 'h-0.5 w-3 rounded-full'
                            : 'size-2.5 rounded-[3px]'
                    "
                    :style="{ backgroundColor: seriesColor(series.color) }"
                    aria-hidden="true"
                />
                {{ series.label }}
            </li>
        </ul>

        <p
            v-if="chart.empty"
            class="flex min-h-40 items-center justify-center rounded-lg border border-dashed px-4 text-center text-sm text-muted-foreground"
        >
            Nothing to chart for this period.
        </p>

        <div
            v-else-if="showTable"
            class="max-h-80 overflow-auto rounded-lg border"
        >
            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 bg-muted text-xs text-muted-foreground"
                >
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium">
                            {{ chart.kind === 'bars' ? 'Name' : 'Period' }}
                        </th>
                        <th
                            v-for="series in chart.series"
                            :key="series.key"
                            scope="col"
                            class="px-3 py-2 text-right font-medium"
                        >
                            {{ series.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(label, index) in chart.labels"
                        :key="index"
                        class="border-t"
                    >
                        <th
                            scope="row"
                            class="px-3 py-1.5 text-left font-normal"
                        >
                            {{ label }}
                        </th>
                        <td
                            v-for="series in chart.series"
                            :key="series.key"
                            class="px-3 py-1.5 text-right figures"
                        >
                            {{
                                formatValue(
                                    series.values[index],
                                    chart.format,
                                    currency,
                                )
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BarList
            v-else-if="chart.kind === 'bars'"
            :chart="chart"
            :currency="currency"
        />
        <LineChart
            v-else-if="chart.kind === 'line'"
            :chart="chart"
            :currency="currency"
        />
        <ColumnChart v-else :chart="chart" :currency="currency" />
    </figure>
</template>
