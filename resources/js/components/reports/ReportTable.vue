<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EnumBadge from '@/components/EnumBadge.vue';
import { useOrganization } from '@/composables/useOrganization';
import { formatValue } from '@/lib/charts';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/operations';
import type {
    LinkCell,
    MoneyCell,
    ReportCell,
    ReportColumn,
} from '@/types/reports';

/**
 * The rows behind a report, formatted by column. Numbers align right with
 * tabular figures so they can be compared down a column.
 */
withDefaults(
    defineProps<{
        columns: ReportColumn[];
        rows: Record<string, ReportCell>[];
        caption: string;
        stale?: boolean;
    }>(),
    { stale: false },
);

const { organization } = useOrganization();

function isLink(value: ReportCell): value is LinkCell {
    return (
        typeof value === 'object' &&
        value !== null &&
        'label' in value &&
        'url' in value
    );
}

function isMoney(value: ReportCell): value is MoneyCell {
    return (
        typeof value === 'object' &&
        value !== null &&
        'amount' in value &&
        'currency' in value
    );
}

function isOption(value: ReportCell): value is EnumOption {
    return (
        typeof value === 'object' &&
        value !== null &&
        'tone' in value &&
        'value' in value
    );
}

/** Long free text wraps; everything else stays on one line and the table scrolls. */
function wraps(column: ReportColumn): boolean {
    return ['error', 'last_error', 'areas'].includes(column.key);
}

function text(column: ReportColumn, value: ReportCell): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const format = column.format;

    switch (format) {
        case 'money':
            return isMoney(value)
                ? formatMoney(value.amount, value.currency)
                : '—';
        case 'date':
            return typeof value === 'string'
                ? formatDate(`${value}T00:00:00`)
                : '—';
        case 'datetime':
            return typeof value === 'string'
                ? formatDateTime(value, organization.value?.timezone)
                : '—';
        case 'link':
        case 'status':
            return isLink(value) || isOption(value) ? value.label : '—';
        case 'text':
            return typeof value === 'string' || typeof value === 'number'
                ? String(value)
                : '—';
        default:
            if (typeof value === 'number') {
                return formatValue(value, format, organization.value?.currency);
            }

            return typeof value === 'string' ? value : '—';
    }
}
</script>

<template>
    <div
        :class="cn('overflow-x-auto transition-opacity', stale && 'opacity-60')"
    >
        <table class="w-full text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr class="border-b text-left text-xs text-muted-foreground">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        :class="
                            cn(
                                'px-4 py-2.5 font-medium whitespace-nowrap first:pl-5 last:pr-5',
                                column.numeric && 'text-right',
                            )
                        "
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in rows"
                    :key="index"
                    class="border-b last:border-0 hover:bg-accent/40"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        :class="
                            cn(
                                'px-4 py-2.5 align-top first:pl-5 last:pr-5',
                                column.numeric &&
                                    'text-right figures whitespace-nowrap',
                                wraps(column)
                                    ? 'max-w-md min-w-64 text-pretty text-muted-foreground'
                                    : 'whitespace-nowrap',
                            )
                        "
                    >
                        <template
                            v-if="
                                column.format === 'link' &&
                                isLink(row[column.key])
                            "
                        >
                            <Link
                                v-if="(row[column.key] as LinkCell).url"
                                :href="(row[column.key] as LinkCell).url!"
                                class="font-medium hover:text-primary hover:underline"
                                >{{ (row[column.key] as LinkCell).label }}</Link
                            >
                            <span v-else>{{
                                (row[column.key] as LinkCell).label
                            }}</span>
                        </template>
                        <EnumBadge
                            v-else-if="
                                column.format === 'status' &&
                                isOption(row[column.key])
                            "
                            :option="row[column.key] as EnumOption"
                        />
                        <template v-else>{{
                            text(column, row[column.key])
                        }}</template>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
