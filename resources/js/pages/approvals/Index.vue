<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Clock, Inbox, Plus, Search, Stamp } from '@lucide/vue';
import { computed, ref } from 'vue';
import NewApprovalDialog from '@/components/approvals/NewApprovalDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
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
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as approvalsIndex, show } from '@/routes/approvals';
import type { ApprovalItem } from '@/types/approvals';
import type {
    EnumOption,
    MemberOption,
    PaginatedResource,
} from '@/types/operations';

const props = defineProps<{
    approvals: PaginatedResource<ApprovalItem>;
    filters: {
        view: 'waiting' | 'mine' | 'all';
        status: 'open' | 'decided' | 'all';
        q: string;
    };
    counts: { waiting: number; mine: number };
    priorities: EnumOption[];
    roles?: { value: string; label: string }[];
    members?: MemberOption[];
    can: { create: boolean; decide: boolean; viewAll: boolean };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Approvals', href: approvalsIndex() }],
});

const { filters } = useFilters(
    {
        view: props.filters.view,
        status: props.filters.status,
        q: props.filters.q,
    },
    { only: ['approvals', 'filters', 'counts'] },
);

const statusModel = computed({
    get: () => filters.status,
    set: (value: string) => (filters.status = value as typeof filters.status),
});

const creating = ref(
    props.can.create &&
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('create'),
);

const tabs = computed(() =>
    [
        {
            value: 'waiting' as const,
            label: 'Waiting for me',
            count: props.counts.waiting,
            show: props.can.decide,
        },
        {
            value: 'mine' as const,
            label: 'My requests',
            count: props.counts.mine,
            show: true,
        },
        {
            value: 'all' as const,
            label: 'All requests',
            count: null,
            show: props.can.viewAll,
        },
    ].filter((tab) => tab.show),
);

function waitingOn(approval: ApprovalItem): string {
    return (
        approval.approver?.name ??
        (approval.approver_role
            ? `Anyone in ${approval.approver_role.label}`
            : '—')
    );
}
</script>

<template>
    <Head title="Approvals" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Approvals"
            description="Requests waiting for a yes or no, and the decisions already made."
        >
            <template #actions>
                <Button v-if="can.create" @click="creating = true">
                    <Plus />
                    New request
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div
                class="flex flex-wrap items-center gap-x-1 gap-y-2 border-b px-3 pt-2"
                role="tablist"
                aria-label="Which requests"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.view === tab.value"
                    :class="
                        cn(
                            '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            filters.view === tab.value
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="filters.view = tab.value"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count"
                        class="rounded-full bg-warning-soft px-1.5 figures text-xs text-warning-text"
                        >{{ tab.count }}</span
                    >
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by title or A-number"
                        aria-label="Search requests"
                        class="pl-8"
                    />
                </div>
                <Select v-if="filters.view !== 'waiting'" v-model="statusModel">
                    <SelectTrigger class="h-9 w-40" aria-label="Status"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="open">Still open</SelectItem>
                        <SelectItem value="decided">Decided</SelectItem>
                        <SelectItem value="all">Everything</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <ul v-if="approvals.data.length" class="divide-y">
                <li v-for="approval in approvals.data" :key="approval.id">
                    <Link
                        :href="show({ approval: approval.id })"
                        class="grid gap-3 px-4 py-3.5 transition-colors hover:bg-secondary/40 focus-visible:bg-secondary/40 focus-visible:outline-none sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ approval.reference }}</span
                                >
                                <p class="truncate font-medium">
                                    {{ approval.title }}
                                </p>
                                <EnumBadge
                                    v-if="
                                        approval.priority.value === 'high' ||
                                        approval.priority.value === 'urgent'
                                    "
                                    :option="approval.priority"
                                    variant="plain"
                                />
                            </div>
                            <p
                                class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="approval.requester"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    <MemberAvatar
                                        :name="approval.requester.name"
                                        :avatar="approval.requester.avatar"
                                        class="size-4 text-[0.5rem]"
                                    />
                                    {{ approval.requester.name }}
                                </span>
                                <span v-else>Raised by a workflow</span>
                                <span aria-hidden="true">·</span>
                                <span>For {{ waitingOn(approval) }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ timeAgo(approval.created_at) }}</span>
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-3 sm:justify-end"
                        >
                            <span
                                v-if="approval.amount"
                                class="font-display figures text-base font-semibold"
                                >{{ approval.amount.formatted }}</span
                            >
                            <span
                                v-if="approval.is_open && approval.due_at"
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 text-xs',
                                        approval.is_overdue
                                            ? 'font-medium text-danger-text'
                                            : 'text-muted-foreground',
                                    )
                                "
                                :title="formatDateTime(approval.due_at)"
                            >
                                <Clock class="size-3.5" aria-hidden="true" />
                                {{ approval.is_overdue ? 'Overdue' : 'Due' }}
                                {{ timeAgo(approval.due_at) }}
                            </span>
                            <EnumBadge :option="approval.status" />
                        </div>
                    </Link>
                </li>
            </ul>

            <EmptyState
                v-else-if="filters.q"
                :icon="Search"
                title="No requests match"
                description="Try another title or A-number."
            >
                <Button variant="outline" @click="filters.q = ''"
                    >Clear search</Button
                >
            </EmptyState>
            <EmptyState
                v-else-if="filters.view === 'waiting'"
                :icon="Inbox"
                title="Nothing is waiting for you"
                description="When someone needs your decision, the request shows up here and in your notifications."
            />
            <EmptyState
                v-else
                :icon="Stamp"
                title="No requests here"
                description="Raise a request when something needs a yes before it can go ahead, such as a purchase or time off."
            >
                <Button v-if="can.create" @click="creating = true"
                    ><Plus />New request</Button
                >
            </EmptyState>

            <Pagination :paginator="approvals" noun="requests" />
        </div>

        <NewApprovalDialog
            v-if="can.create"
            v-model:open="creating"
            :members="members ?? []"
            :roles="roles ?? []"
            :priorities="priorities"
        />
    </div>
</template>
