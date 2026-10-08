<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Activity, ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import DateInput from '@/components/DateInput.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFilters } from '@/composables/useFilters';
import { useOrganization } from '@/composables/useOrganization';
import { formatDate } from '@/lib/format';
import { index as activityIndex } from '@/routes/activity';
import type { ActivityEntry } from '@/types/activity';
import type { MemberOption } from '@/types/operations';
import type { Paginated } from '@/types/pagination';

type AuditedEntry = ActivityEntry & {
    audit: {
        ip_address: string | null;
        user_agent: string | null;
        subject_type: string | null;
        subject_id: string | null;
    } | null;
};

const props = defineProps<{
    entries: Paginated<AuditedEntry>;
    filters: {
        area: string | null;
        actor: number | null;
        from: string | null;
        to: string | null;
    };
    areas: { value: string; label: string }[];
    members?: MemberOption[];
    canAudit: boolean;
}>();

setLayoutProps({ breadcrumbs: [{ title: 'Activity', href: activityIndex() }] });

const { organization } = useOrganization();

const { filters } = useFilters(
    {
        area: props.filters.area,
        actor: props.filters.actor ? String(props.filters.actor) : null,
        from: props.filters.from,
        to: props.filters.to,
    },
    { only: ['entries', 'filters'] },
);

const areaModel = computed({
    get: () => filters.area ?? 'any',
    set: (value: string) => (filters.area = value === 'any' ? null : value),
});

const actorModel = computed({
    get: () => filters.actor ?? 'any',
    set: (value: string) => (filters.actor = value === 'any' ? null : value),
});

/** Entries grouped under a heading per day, in the organization's timezone. */
const days = computed(() => {
    const groups = new Map<string, AuditedEntry[]>();

    for (const entry of props.entries.data) {
        const day = formatDate(entry.created_at, organization.value?.timezone, {
            dateStyle: 'full',
        });
        groups.set(day, [...(groups.get(day) ?? []), entry]);
    }

    return [...groups.entries()];
});

const expanded = ref<string | null>(null);

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return 'empty';
    }

    return Array.isArray(value) ? value.join(', ') : String(value);
}

const hasFilters = computed(
    () => !!(filters.area || filters.actor || filters.from || filters.to),
);
</script>

<template>
    <Head title="Activity" />

    <div
        class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Activity"
            :description="
                canAudit
                    ? 'Everything that changed in the organization, with who did it and from where.'
                    : 'Everything that changed in the organization and who did it.'
            "
        />

        <div
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-3 shadow-xs"
        >
            <div class="grid gap-1">
                <span class="text-xs text-muted-foreground">Area</span>
                <Select v-model="areaModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Area"
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
                <span class="text-xs text-muted-foreground">Person</span>
                <Select v-model="actorModel">
                    <SelectTrigger class="h-9 w-48" aria-label="Person"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Anyone</SelectItem>
                        <SelectItem
                            v-for="member in members ?? []"
                            :key="member.id"
                            :value="String(member.id)"
                            >{{ member.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1">
                <label for="activity-from" class="text-xs text-muted-foreground"
                    >From</label
                >
                <DateInput
                    id="activity-from"
                    v-model="filters.from"
                    class="w-40"
                />
            </div>
            <div class="grid gap-1">
                <label for="activity-to" class="text-xs text-muted-foreground"
                    >To</label
                >
                <DateInput
                    id="activity-to"
                    v-model="filters.to"
                    :min="filters.from ?? undefined"
                    class="w-40"
                />
            </div>
            <Button
                v-if="hasFilters"
                variant="link"
                size="sm"
                class="text-muted-foreground"
                @click="
                    Object.assign(filters, {
                        area: null,
                        actor: null,
                        from: null,
                        to: null,
                    })
                "
            >
                Clear filters
            </Button>
        </div>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <EmptyState
                v-if="entries.data.length === 0"
                :icon="Activity"
                :title="
                    hasFilters
                        ? 'Nothing matches these filters'
                        : 'Nothing has happened yet'
                "
                :description="
                    hasFilters
                        ? 'Try a wider date range or another person.'
                        : 'Changes to projects, tasks, members and settings are recorded here.'
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
                    <template v-for="(entry, index) in items" :key="entry.id">
                        <ActivityItem
                            :entry="entry"
                            :timezone="organization?.timezone"
                            :connected="index < items.length - 1"
                        />
                        <li
                            v-if="
                                canAudit &&
                                (Object.keys(entry.changes).length ||
                                    entry.audit)
                            "
                            class="-mt-3 mb-4 ml-11 list-none"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                :aria-expanded="expanded === entry.id"
                                @click="
                                    expanded =
                                        expanded === entry.id ? null : entry.id
                                "
                            >
                                <ChevronDown
                                    class="size-3.5 transition-transform"
                                    :class="
                                        expanded === entry.id && 'rotate-180'
                                    "
                                    aria-hidden="true"
                                />
                                Audit details
                            </button>
                            <dl
                                v-if="expanded === entry.id"
                                class="mt-2 grid gap-1.5 rounded-lg bg-muted/60 p-3 text-xs"
                            >
                                <div
                                    v-for="(change, field) in entry.changes"
                                    :key="field"
                                    class="grid grid-cols-[8rem_1fr] gap-2"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ String(field).replaceAll('_', ' ') }}
                                    </dt>
                                    <dd class="break-words">
                                        <span
                                            class="text-danger-text line-through"
                                            >{{
                                                formatValue(change.from)
                                            }}</span
                                        >
                                        →
                                        <span class="text-success-text">{{
                                            formatValue(change.to)
                                        }}</span>
                                    </dd>
                                </div>
                                <div
                                    v-if="entry.audit?.ip_address"
                                    class="grid grid-cols-[8rem_1fr] gap-2"
                                >
                                    <dt class="text-muted-foreground">
                                        IP address
                                    </dt>
                                    <dd class="figures">
                                        {{ entry.audit.ip_address }}
                                    </dd>
                                </div>
                                <div
                                    v-if="entry.audit?.user_agent"
                                    class="grid grid-cols-[8rem_1fr] gap-2"
                                >
                                    <dt class="text-muted-foreground">
                                        Browser
                                    </dt>
                                    <dd class="break-words">
                                        {{ entry.audit.user_agent }}
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </template>
                </ol>
            </div>

            <Pagination :paginator="entries" noun="entries" />
        </section>
    </div>
</template>
