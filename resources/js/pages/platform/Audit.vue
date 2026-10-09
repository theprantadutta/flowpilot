<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { History, Search } from '@lucide/vue';
import { computed } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import { audit, dashboard } from '@/routes/platform';
import { show as organizationShow } from '@/routes/platform/organizations';
import type { Paginated } from '@/types/pagination';
import type { PlatformAuditEntry } from '@/types/platform';

const props = defineProps<{
    entries: Paginated<PlatformAuditEntry>;
    filters: {
        organization: string;
        area: string | null;
        actor: string | null;
    };
    areas: { value: string; label: string }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'Audit log', href: audit() },
    ],
});

const actors = [
    { value: 'user', label: 'People' },
    { value: 'workflow', label: 'Workflows' },
    { value: 'system', label: 'FlowPilot' },
    { value: 'ai', label: 'FlowPilot AI' },
    { value: 'platform', label: 'FlowPilot support' },
];

const { filters, reset } = useFilters(
    {
        organization: props.filters.organization,
        area: props.filters.area,
        actor: props.filters.actor,
    },
    { only: ['entries', 'filters'], debounced: ['organization'] },
);

const areaModel = computed({
    get: () => filters.area ?? 'any',
    set: (value: string) => (filters.area = value === 'any' ? null : value),
});

const actorModel = computed({
    get: () => filters.actor ?? 'any',
    set: (value: string) => (filters.actor = value === 'any' ? null : value),
});

const hasFilters = computed(
    () => !!(filters.organization || filters.area || filters.actor),
);

/** Entries under a heading per day, in the viewer's timezone. */
const days = computed(() => {
    const groups = new Map<string, PlatformAuditEntry[]>();

    for (const entry of props.entries.data) {
        const day = formatDate(entry.created_at, undefined, {
            dateStyle: 'full',
        });
        groups.set(day, [...(groups.get(day) ?? []), entry]);
    }

    return [...groups.entries()];
});
</script>

<template>
    <Head title="Audit log" />

    <div
        class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Audit log"
            description="Every recorded change across all organizations, including what the FlowPilot team did."
        />

        <div
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-3 shadow-xs"
        >
            <div class="grid gap-1">
                <label
                    for="audit-organization"
                    class="text-xs text-muted-foreground"
                    >Organization</label
                >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="audit-organization"
                        v-model="filters.organization"
                        type="search"
                        placeholder="Name or slug"
                        class="h-9 w-52 pl-8"
                    />
                </div>
            </div>
            <div class="grid gap-1">
                <span class="text-xs text-muted-foreground">Area</span>
                <Select v-model="areaModel">
                    <SelectTrigger class="h-9 w-44" aria-label="Area"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Everything</SelectItem>
                        <SelectItem
                            v-for="area in areas"
                            :key="area.value"
                            :value="area.value"
                            >{{ area.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1">
                <span class="text-xs text-muted-foreground">Done by</span>
                <Select v-model="actorModel">
                    <SelectTrigger class="h-9 w-44" aria-label="Done by"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Anyone</SelectItem>
                        <SelectItem
                            v-for="actor in actors"
                            :key="actor.value"
                            :value="actor.value"
                            >{{ actor.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <Button
                v-if="hasFilters"
                variant="link"
                size="sm"
                class="text-muted-foreground"
                @click="reset({ organization: '', area: null, actor: null })"
            >
                Clear filters
            </Button>
        </div>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <EmptyState
                v-if="entries.data.length === 0"
                :icon="History"
                :title="
                    hasFilters
                        ? 'Nothing matches these filters'
                        : 'Nothing recorded yet'
                "
                :description="
                    hasFilters
                        ? 'Check the organization name, or widen the filters.'
                        : 'Changes made in any organization are recorded here.'
                "
            />

            <div
                v-for="[day, items] in days"
                :key="day"
                class="border-b last:border-0"
            >
                <h2
                    class="sticky top-0 z-10 border-b bg-card/95 px-5 py-2 text-xs font-medium text-muted-foreground backdrop-blur"
                >
                    {{ day }}
                </h2>
                <ol class="px-5 pt-4 pb-1">
                    <template
                        v-for="(entry, position) in items"
                        :key="entry.id"
                    >
                        <ActivityItem
                            :entry="entry"
                            :connected="position < items.length - 1"
                        />
                        <li
                            class="-mt-3 mb-4 ml-11 flex list-none flex-wrap gap-x-3 text-xs text-muted-foreground"
                        >
                            <Link
                                v-if="entry.organization"
                                :href="
                                    organizationShow(entry.organization.slug)
                                "
                                class="font-medium text-foreground hover:text-primary hover:underline"
                                >{{ entry.organization.name }}</Link
                            >
                            <span v-else>No organization</span>
                            <span v-if="entry.staff">by {{ entry.staff }}</span>
                            <span v-if="entry.ip_address" class="figures"
                                >from {{ entry.ip_address }}</span
                            >
                        </li>
                    </template>
                </ol>
            </div>

            <Pagination :paginator="entries" noun="entries" />
        </section>
    </div>
</template>
