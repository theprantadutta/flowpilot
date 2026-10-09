<script setup lang="ts">
import { Download } from '@lucide/vue';
import EnumBadge from '@/components/EnumBadge.vue';
import { Button } from '@/components/ui/button';
import { useOrganization } from '@/composables/useOrganization';
import { formatValue } from '@/lib/charts';
import { formatDate, plural, timeAgo } from '@/lib/format';
import type { ReportExportItem } from '@/types/reports';

/**
 * The member's own exports: what is still being prepared, what is ready to
 * download and until when.
 */
withDefaults(
    defineProps<{ exports: ReportExportItem[]; showReport?: boolean }>(),
    { showReport: false },
);

const { organization } = useOrganization();
</script>

<template>
    <ul class="divide-y">
        <li
            v-for="item in exports"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
        >
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">
                    <template v-if="showReport"
                        >{{ item.report.label }}, </template
                    >{{ item.range ?? 'All dates' }}
                </p>
                <p
                    class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                >
                    <span>Asked for {{ timeAgo(item.created_at) }}</span>
                    <template
                        v-if="item.download_url && item.row_count !== null"
                    >
                        <span aria-hidden="true">·</span>
                        <span
                            >{{ plural(item.row_count, 'row')
                            }}<template v-if="item.size"
                                >,
                                {{ formatValue(item.size, 'bytes') }}</template
                            ></span
                        >
                        <span aria-hidden="true">·</span>
                        <span
                            >Available until
                            {{
                                formatDate(
                                    item.expires_at,
                                    organization?.timezone,
                                )
                            }}</span
                        >
                    </template>
                    <template v-else-if="item.is_expired">
                        <span aria-hidden="true">·</span>
                        <span>Expired</span>
                    </template>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <EnumBadge v-if="!item.download_url" :option="item.status" />
                <Button v-else as-child size="sm" variant="outline">
                    <a
                        :href="item.download_url"
                        :download="item.filename ?? undefined"
                    >
                        <Download />
                        Download CSV
                    </a>
                </Button>
            </div>
        </li>
    </ul>
</template>
