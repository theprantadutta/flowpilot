<script setup lang="ts">
import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { ArrowRight, FileDown } from '@lucide/vue';
import { computed, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import ExportList from '@/components/reports/ExportList.vue';
import { index as reportsIndex, show } from '@/routes/reports';
import type { ResourceCollection } from '@/types/operations';
import type { ReportExportItem, ReportSummary } from '@/types/reports';

const props = defineProps<{
    reports: ReportSummary[];
    exports: ResourceCollection<ReportExportItem>;
    can: { export: boolean };
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Reports', href: reportsIndex() }] });

const groups = computed(() => {
    const order = ['Work', 'Automation', 'Inventory', 'Organization'];

    return order
        .map((group) => ({
            group,
            reports: props.reports.filter((report) => report.group === group),
        }))
        .filter((section) => section.reports.length > 0);
});

const preparing = computed(() =>
    props.exports.data.some((item) => !item.is_finished),
);

const { start, stop } = usePoll(
    4000,
    { only: ['exports'] },
    { autoStart: preparing.value, keepAlive: false },
);

watch(preparing, (active) => (active ? start() : stop()));
</script>

<template>
    <Head title="Reports" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Reports"
            description="How work, approvals, automation and stock are going, over any period. Export any report as CSV."
        />

        <section
            v-for="section in groups"
            :key="section.group"
            :aria-labelledby="`group-${section.group}`"
            class="grid gap-3"
        >
            <h2
                :id="`group-${section.group}`"
                class="text-sm font-semibold text-muted-foreground"
            >
                {{ section.group }}
            </h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="report in section.reports" :key="report.value">
                    <Link
                        :href="show({ report: report.value })"
                        class="group flex h-full items-start gap-4 rounded-xl border bg-card p-5 shadow-xs transition-[box-shadow,border-color] hover:border-border-strong hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-info-soft text-info-text"
                        >
                            <NamedIcon :name="report.icon" class="size-5" />
                        </span>
                        <span class="grid min-w-0 gap-1">
                            <span
                                class="flex items-center gap-1.5 font-semibold"
                            >
                                {{ report.label }}
                                <ArrowRight
                                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="text-sm text-pretty text-muted-foreground"
                                >{{ report.description }}</span
                            >
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-if="can.export"
            aria-labelledby="exports-heading"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <h2
                id="exports-heading"
                class="border-b px-5 py-3.5 font-display text-sm font-semibold"
            >
                Your exports
            </h2>
            <ExportList
                v-if="exports.data.length"
                :exports="exports.data"
                show-report
            />
            <EmptyState
                v-else
                compact
                :icon="FileDown"
                title="No exports yet"
                description="Open a report, filter it, and export it as CSV. Large exports are prepared in the background and kept for a week."
            />
        </section>
    </div>
</template>
