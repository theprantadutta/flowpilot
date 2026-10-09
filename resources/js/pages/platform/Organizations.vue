<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Building2, Search } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { formatDate, formatNumber, plural } from '@/lib/format';
import { dashboard } from '@/routes/platform';
import { index, show } from '@/routes/platform/organizations';
import type { Paginated } from '@/types/pagination';
import type { PlanChoice, PlatformOrganizationRow } from '@/types/platform';

const props = defineProps<{
    tenants: Paginated<PlatformOrganizationRow>;
    filters: { q: string; plan: string | null; status: string | null };
    plans: PlanChoice[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'Organizations', href: index() },
    ],
});

const { filters, reset } = useFilters(
    {
        q: props.filters.q,
        plan: props.filters.plan,
        status: props.filters.status,
    },
    { only: ['tenants', 'filters'] },
);

const planModel = computed({
    get: () => filters.plan ?? 'any',
    set: (value: string) => (filters.plan = value === 'any' ? null : value),
});

const statusModel = computed({
    get: () => filters.status ?? 'any',
    set: (value: string) => (filters.status = value === 'any' ? null : value),
});

const hasFilters = computed(
    () => !!(filters.q || filters.plan || filters.status),
);

function clearFilters() {
    reset({ q: '', plan: null, status: null });
}

function trialHint(row: PlatformOrganizationRow): string | null {
    const days = row.plan?.trial_days_left;

    if (
        row.plan?.status.value !== 'trialing' ||
        days === null ||
        days === undefined
    ) {
        return null;
    }

    return days === 0 ? 'Trial ends today' : `${plural(days, 'day')} left`;
}
</script>

<template>
    <Head title="Organizations" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Organizations"
            :description="`${formatNumber(tenants.total)} ${tenants.total === 1 ? 'organization' : 'organizations'} on FlowPilot. Open one to change its plan, extend a trial or suspend it.`"
        />

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by name or slug"
                        aria-label="Search organizations"
                        class="pl-8"
                    />
                </div>
                <Select v-model="planModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Plan"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any plan</SelectItem>
                        <SelectItem
                            v-for="plan in plans"
                            :key="plan.value"
                            :value="plan.value"
                            >{{ plan.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="statusModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="any">Any status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="suspended">Suspended</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <template v-if="tenants.data.length">
                <table class="hidden w-full text-sm md:table">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th
                                scope="col"
                                class="py-2.5 pr-3 pl-5 font-medium"
                            >
                                Organization
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Owner
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Plan
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2.5 text-right font-medium"
                            >
                                Members
                            </th>
                            <th
                                scope="col"
                                class="py-2.5 pr-5 pl-3 text-right font-medium"
                            >
                                Joined
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in tenants.data"
                            :key="row.id"
                            class="group border-b last:border-0 hover:bg-accent/40"
                        >
                            <td class="max-w-0 py-3 pr-3 pl-5">
                                <Link
                                    :href="show(row.slug)"
                                    class="flex min-w-0 items-center gap-2"
                                >
                                    <span
                                        class="truncate font-medium group-hover:text-primary"
                                        >{{ row.name }}</span
                                    >
                                    <StatusBadge
                                        v-if="row.status === 'suspended'"
                                        tone="danger"
                                        >Suspended</StatusBadge
                                    >
                                </Link>
                                <p
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ row.slug }}
                                </p>
                            </td>
                            <td class="max-w-64 px-3 py-3">
                                <p class="truncate">{{ row.owner.name }}</p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ row.owner.email }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <div
                                    v-if="row.plan"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span class="font-medium">{{
                                        row.plan.label
                                    }}</span>
                                    <EnumBadge :option="row.plan.status" />
                                    <span
                                        v-if="trialHint(row)"
                                        class="text-xs whitespace-nowrap text-muted-foreground"
                                        >{{ trialHint(row) }}</span
                                    >
                                </div>
                                <span v-else class="text-muted-foreground"
                                    >No subscription</span
                                >
                            </td>
                            <td class="px-3 py-3 text-right figures">
                                {{ formatNumber(row.members) }}
                            </td>
                            <td
                                class="py-3 pr-5 pl-3 text-right whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(row.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y md:hidden">
                    <li v-for="row in tenants.data" :key="row.id">
                        <Link
                            :href="show(row.slug)"
                            class="grid gap-1.5 px-4 py-3 hover:bg-accent/40"
                        >
                            <span class="flex items-center gap-2">
                                <span class="truncate font-medium">{{
                                    row.name
                                }}</span>
                                <StatusBadge
                                    v-if="row.status === 'suspended'"
                                    tone="danger"
                                    >Suspended</StatusBadge
                                >
                            </span>
                            <span
                                class="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <span v-if="row.plan">{{
                                    row.plan.label
                                }}</span>
                                <EnumBadge
                                    v-if="row.plan"
                                    :option="row.plan.status"
                                />
                                <span class="text-xs text-muted-foreground">{{
                                    plural(row.members, 'member')
                                }}</span>
                            </span>
                            <span class="truncate text-xs text-muted-foreground"
                                >{{ row.owner.name }} ·
                                {{ row.owner.email }}</span
                            >
                        </Link>
                    </li>
                </ul>
            </template>

            <EmptyState
                v-else-if="hasFilters"
                :icon="Search"
                title="No organizations match"
                description="Try another name, or clear the plan and status filters."
            >
                <Button variant="outline" @click="clearFilters"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="Building2"
                title="No organizations yet"
                description="Every organization created through sign-up is listed here."
            />

            <Pagination :paginator="tenants" noun="organizations" />
        </div>
    </div>
</template>
