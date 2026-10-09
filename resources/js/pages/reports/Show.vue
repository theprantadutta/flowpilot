<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { FileDown, Lock, SearchX } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ChartCard from '@/components/charts/ChartCard.vue';
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import ExportList from '@/components/reports/ExportList.vue';
import ReportTable from '@/components/reports/ReportTable.vue';
import StatTile from '@/components/reports/StatTile.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useOrganization } from '@/composables/useOrganization';
import { show as billingShow } from '@/routes/billing';
import { index as reportsIndex, show } from '@/routes/reports';
import { store as storeExport } from '@/routes/reports/exports';
import type { ResourceCollection } from '@/types/operations';
import type {
    ReportExportItem,
    ReportOptions,
    ReportParameters,
    ReportResult,
    ReportTable as ReportTableData,
} from '@/types/reports';

const props = defineProps<{
    report: {
        value: string;
        label: string;
        description: string;
        icon: string;
        uses_range: boolean;
    };
    parameters: ReportParameters;
    options: ReportOptions;
    result?: ReportResult;
    table?: ReportTableData;
    exports: ResourceCollection<ReportExportItem>;
    can: { export: boolean; exportLocked: boolean };
}>();

const { organization, canAny } = useOrganization();
const currency = computed(() => organization.value?.currency ?? 'USD');

setLayoutProps({
    breadcrumbs: [
        { title: 'Reports', href: reportsIndex() },
        {
            title: props.report.label,
            href: show({ report: props.report.value }),
        },
    ],
});

/** The filter row, kept in step with the URL. */
function fromProps() {
    return {
        range: props.parameters.range,
        from: props.parameters.from as string | null,
        to: props.parameters.to as string | null,
        bucket: props.parameters.bucket,
        group: props.parameters.group ?? '',
        filters: { ...props.parameters.filters } as Record<string, string>,
    };
}

const state = reactive(fromProps());
const loading = ref(false);

watch(
    () => props.parameters,
    () => Object.assign(state, fromProps()),
);

function query(): Record<string, string> {
    const values: Record<string, string> = {
        range: state.range,
        bucket: state.bucket,
    };

    if (state.range === 'custom' && state.from && state.to) {
        values.from = state.from;
        values.to = state.to;
    }

    if (state.group) {
        values.group = state.group;
    }

    for (const [key, value] of Object.entries(state.filters)) {
        if (value) {
            values[key] = value;
        }
    }

    return values;
}

function apply() {
    router.get(show({ report: props.report.value }).url, query(), {
        only: ['parameters', 'result', 'table'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

const rangeModel = computed({
    get: () => state.range,
    set: (value: string) => {
        state.range = value;

        if (value !== 'custom') {
            apply();
        }
    },
});

const bucketModel = computed({
    get: () => state.bucket,
    set: (value: string) => {
        state.bucket = value;
        apply();
    },
});

const groupModel = computed({
    get: () => state.group,
    set: (value: string) => {
        state.group = value;
        apply();
    },
});

function setFilter(key: string, value: string) {
    state.filters[key] = value === 'any' ? '' : value;
    apply();
}

const customValid = computed(
    () => !!state.from && !!state.to && state.from <= state.to,
);

const exporting = ref(false);

function exportReport() {
    const { range, from, to, bucket, group, filters } = state;

    router.post(
        storeExport({ report: props.report.value }).url,
        {
            range,
            ...(range === 'custom' ? { from, to } : {}),
            bucket,
            group: group || null,
            filters: Object.fromEntries(
                Object.entries(filters).filter(([, value]) => value),
            ),
        },
        {
            only: ['exports'],
            preserveScroll: true,
            onStart: () => (exporting.value = true),
            onFinish: () => (exporting.value = false),
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'The export could not be started.',
                ),
        },
    );
}

const preparing = computed(() =>
    props.exports.data.some((item) => !item.is_finished),
);
const { start, stop } = usePoll(
    3000,
    { only: ['exports'] },
    { autoStart: preparing.value, keepAlive: false },
);

watch(preparing, (active) => (active ? start() : stop()));

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <Head :title="report.label" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader :title="report.label" :description="report.description">
            <template #eyebrow>
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-info-soft text-info-text"
                >
                    <NamedIcon :name="report.icon" class="size-5" />
                </span>
            </template>
            <template #actions>
                <Button
                    v-if="can.export"
                    :disabled="exporting"
                    @click="exportReport"
                >
                    <Spinner v-if="exporting" />
                    <FileDown v-else />
                    Export CSV
                </Button>
                <span
                    v-else-if="can.exportLocked"
                    class="inline-flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Lock class="size-4" aria-hidden="true" />
                    CSV exports come with the Business plan.
                    <Link
                        v-if="canAny('settings.manage', 'billing.manage')"
                        :href="billingShow()"
                        class="font-medium text-primary hover:underline"
                        >See plans</Link
                    >
                </span>
            </template>
        </PageHeader>

        <div
            class="flex flex-wrap items-end gap-2"
            role="group"
            aria-label="Report filters"
        >
            <template v-if="report.uses_range">
                <Select v-model="rangeModel">
                    <SelectTrigger class="h-9 w-44" aria-label="Date range"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in options.ranges"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <template v-if="state.range === 'custom'">
                    <DateInput
                        v-model="state.from"
                        :max="today"
                        class="w-40"
                        aria-label="From"
                        clear-label="Clear start date"
                    />
                    <DateInput
                        v-model="state.to"
                        :min="state.from ?? undefined"
                        class="w-40"
                        aria-label="To"
                        clear-label="Clear end date"
                    />
                    <Button
                        variant="outline"
                        :disabled="!customValid || loading"
                        @click="apply"
                        >Apply range</Button
                    >
                </template>
                <Select v-model="bucketModel">
                    <SelectTrigger class="h-9 w-32" aria-label="Chart periods"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in options.buckets"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
            <Select v-if="options.groups.length" v-model="groupModel">
                <SelectTrigger class="h-9 w-44" aria-label="Group the table"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in options.groups"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select
                v-for="filter in options.filters"
                :key="filter.key"
                :model-value="state.filters[filter.key] || 'any'"
                @update:model-value="
                    (value) => setFilter(filter.key, String(value))
                "
            >
                <SelectTrigger class="h-9 w-48" :aria-label="filter.label"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="any">{{ filter.any_label }}</SelectItem>
                    <SelectItem
                        v-for="option in filter.options"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <span
                class="ml-auto flex items-center gap-2 self-center text-sm text-muted-foreground"
            >
                <Spinner
                    :class="['size-4', !loading && 'invisible']"
                    :aria-hidden="!loading"
                />
                {{ parameters.label }}
            </span>
        </div>

        <div
            v-if="result === undefined"
            class="grid grid-cols-2 gap-3 lg:grid-cols-5"
            aria-busy="true"
        >
            <Skeleton v-for="n in 5" :key="n" class="h-24 rounded-xl" />
        </div>
        <div v-else class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatTile
                v-for="tile in result.tiles"
                :key="tile.key"
                :label="tile.label"
                :value="tile.value"
                :format="tile.format"
                :hint="tile.hint"
                :tone="tile.tone"
                :currency="currency"
                :stale="loading"
            />
        </div>

        <div
            v-if="result === undefined"
            class="grid gap-4 lg:grid-cols-2"
            aria-busy="true"
        >
            <Skeleton class="h-80 rounded-xl" />
            <Skeleton class="h-80 rounded-xl" />
        </div>
        <div
            v-else-if="result.charts.length"
            :class="[
                'grid gap-4',
                result.charts.length > 1 && 'lg:grid-cols-2',
            ]"
        >
            <ChartCard
                v-for="chart in result.charts"
                :key="chart.key"
                :chart="chart"
                :currency="currency"
                :stale="loading"
            />
        </div>

        <section
            aria-labelledby="table-heading"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <div
                class="flex flex-wrap items-baseline justify-between gap-2 border-b px-5 py-3.5"
            >
                <h2
                    id="table-heading"
                    class="font-display text-sm font-semibold"
                >
                    Details
                </h2>
                <p
                    v-if="table?.truncated"
                    class="text-xs text-muted-foreground"
                >
                    Showing the first {{ table.limit }} rows. Export the report
                    for all of them.
                </p>
            </div>
            <div
                v-if="table === undefined"
                class="grid gap-3 p-5"
                aria-busy="true"
            >
                <Skeleton v-for="n in 5" :key="n" class="h-8 w-full" />
            </div>
            <ReportTable
                v-else-if="table.rows.length"
                :columns="table.columns"
                :rows="table.rows"
                :caption="`${report.label}, ${parameters.label}`"
                :stale="loading"
            />
            <EmptyState
                v-else
                compact
                :icon="SearchX"
                title="Nothing to show"
                description="Nothing matches this period and these filters. Try a longer range or fewer filters."
            />
        </section>

        <section
            v-if="can.export && exports.data.length"
            aria-labelledby="exports-heading"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <h2
                id="exports-heading"
                class="border-b px-5 py-3.5 font-display text-sm font-semibold"
            >
                Your exports of this report
            </h2>
            <ExportList :exports="exports.data" />
        </section>
    </div>
</template>
