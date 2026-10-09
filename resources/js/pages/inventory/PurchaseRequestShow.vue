<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Check,
    GitBranch,
    PackageCheck,
    Stamp,
    Truck,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import FormField from '@/components/FormField.vue';
import MovementList from '@/components/inventory/MovementList.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as showApproval } from '@/routes/approvals';
import {
    index as itemsIndex,
    show as showItem,
} from '@/routes/inventory/items';
import {
    index as purchaseRequestsIndex,
    show,
} from '@/routes/purchase-requests';
import { store as storeReceipt } from '@/routes/purchase-requests/receipts';
import { update as updateStatus } from '@/routes/purchase-requests/status';
import { show as showRun } from '@/routes/workflow-runs';
import type { ActivityEntry } from '@/types/activity';
import type { ApprovalItem } from '@/types/approvals';
import type {
    MovementData,
    NamedRef,
    PurchaseRequestData,
} from '@/types/inventory';
import type { ResourceCollection } from '@/types/operations';
import type { WorkflowRunItem } from '@/types/workflows';

const props = defineProps<{
    purchaseRequest: PurchaseRequestData;
    approvals: ResourceCollection<ApprovalItem>;
    runs: ResourceCollection<WorkflowRunItem>;
    receipts: ResourceCollection<MovementData>;
    history?: ActivityEntry[];
    locations: NamedRef[];
    hasApprovalWorkflow: boolean;
    can: { decide: boolean; order: boolean; receive: boolean; cancel: boolean };
}>();

const { organization, can: hasPermission } = useOrganization();
const purchase = computed(() => props.purchaseRequest);

setLayoutProps({
    breadcrumbs: [
        ...(hasPermission('inventory.view')
            ? [{ title: 'Inventory', href: itemsIndex() }]
            : []),
        { title: 'Purchase requests', href: purchaseRequestsIndex() },
        {
            title: props.purchaseRequest.reference,
            href: show({ purchaseRequest: props.purchaseRequest.id }),
        },
    ],
});

/** Submitted, approved, ordered, received: where this request has got to. */
const stages = computed(() => {
    const status = purchase.value.status.value;
    const reached = {
        submitted: 0,
        approved: 1,
        ordered: 2,
        received: 3,
    } as Record<string, number>;
    const at =
        reached[status] ??
        (purchase.value.ordered_at ? 2 : purchase.value.decided_at ? 1 : 0);

    return [
        { label: 'Submitted', time: purchase.value.created_at, done: true },
        {
            label: 'Approved',
            time: status === 'rejected' ? null : purchase.value.decided_at,
            done: at >= 1 && status !== 'rejected',
        },
        { label: 'Ordered', time: purchase.value.ordered_at, done: at >= 2 },
        { label: 'Received', time: purchase.value.received_at, done: at >= 3 },
    ];
});

const outstanding = computed(() =>
    Math.max(0, purchase.value.quantity - purchase.value.received_quantity),
);

const statusForm = useForm({ status: '', supplier_reference: '' });

function changeStatus(
    status: 'approved' | 'rejected' | 'ordered' | 'cancelled',
    onDone?: () => void,
) {
    statusForm.status = status;
    statusForm
        .transform((data) => ({
            status: data.status,
            supplier_reference: data.supplier_reference || null,
        }))
        .submit(updateStatus({ purchaseRequest: purchase.value.id }), {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
        });
}

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now().toString(16)}-0000-4000-8000-${Math.random().toString(16).slice(2, 14).padEnd(12, '0')}`;
}

const receiveForm = useForm({
    quantity: String(outstanding.value || ''),
    location_id:
        props.purchaseRequest.deliver_to?.id ?? props.locations[0]?.id ?? '',
    request_key: newKey(),
});

function receive() {
    receiveForm
        .transform((data) => ({
            quantity: Number(data.quantity),
            location_id: data.location_id || null,
            request_key: data.request_key,
        }))
        .post(storeReceipt({ purchaseRequest: purchase.value.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                receiveForm.defaults({
                    quantity: String(outstanding.value || ''),
                    location_id: receiveForm.location_id,
                    request_key: newKey(),
                });
                receiveForm.reset();
            },
        });
}

const cancelling = ref(false);

const outcome = computed(() => {
    const status = purchase.value.status.value;

    if (status === 'rejected') {
        return {
            tone: 'bg-danger-soft text-danger-text',
            text: `${purchase.value.decider?.name ?? 'An approver'} rejected this request.`,
        };
    }

    if (status === 'cancelled') {
        return {
            tone: 'bg-neutral-soft text-neutral-text',
            text: 'This request was cancelled.',
        };
    }

    if (status === 'received') {
        return {
            tone: 'bg-success-soft text-success-text',
            text: `Received in full${purchase.value.item ? ' and booked into stock' : ''}.`,
        };
    }

    return null;
});
</script>

<template>
    <Head :title="`${purchase.reference} ${purchase.item_name}`" />

    <div
        class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:py-8"
    >
        <main class="grid min-w-0 content-start gap-6">
            <header class="grid gap-3">
                <p class="figures text-sm text-muted-foreground">
                    {{ purchase.reference }}
                </p>
                <h1 class="text-2xl leading-tight font-semibold text-balance">
                    {{ purchase.summary }}
                </h1>
                <div class="flex flex-wrap items-center gap-3">
                    <EnumBadge :option="purchase.status" />
                    <span class="font-display figures text-2xl font-semibold">{{
                        purchase.total
                    }}</span>
                    <span class="figures text-sm text-muted-foreground"
                        >{{ formatNumber(purchase.quantity) }} ×
                        {{ purchase.unit_cost }}</span
                    >
                </div>
            </header>

            <ol class="grid grid-cols-4 gap-2" aria-label="Progress">
                <li
                    v-for="stage in stages"
                    :key="stage.label"
                    class="grid gap-1.5"
                >
                    <span
                        :class="
                            cn(
                                'h-1.5 rounded-full',
                                stage.done ? 'bg-flow' : 'bg-secondary',
                            )
                        "
                        aria-hidden="true"
                    />
                    <span
                        :class="
                            cn(
                                'text-xs font-medium',
                                stage.done
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )
                        "
                    >
                        {{ stage.label
                        }}<span class="sr-only">{{
                            stage.done ? ' (done)' : ' (not yet)'
                        }}</span>
                    </span>
                    <span
                        v-if="stage.done && stage.time"
                        class="text-xs text-muted-foreground"
                        >{{
                            formatDateTime(stage.time, organization?.timezone)
                        }}</span
                    >
                </li>
            </ol>

            <p
                v-if="outcome"
                role="status"
                :class="cn('rounded-lg px-4 py-3 text-sm', outcome.tone)"
            >
                {{ outcome.text }}
            </p>

            <section
                v-if="can.decide"
                aria-labelledby="decide-heading"
                class="grid gap-3 rounded-xl border bg-card p-5 shadow-xs"
            >
                <div>
                    <h2 id="decide-heading" class="font-semibold">
                        Your decision
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        No approval workflow is running for purchase requests,
                        so approve or reject it here.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        :disabled="statusForm.processing"
                        @click="changeStatus('approved')"
                    >
                        <Spinner
                            v-if="
                                statusForm.processing &&
                                statusForm.status === 'approved'
                            "
                        />
                        <Check v-else />
                        Approve request
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="statusForm.processing"
                        @click="changeStatus('rejected')"
                    >
                        <Spinner
                            v-if="
                                statusForm.processing &&
                                statusForm.status === 'rejected'
                            "
                        />
                        <X v-else />
                        Reject request
                    </Button>
                </div>
            </section>
            <p
                v-else-if="
                    purchase.status.value === 'submitted' && hasApprovalWorkflow
                "
                class="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning-text"
            >
                Waiting for approval through a workflow. The approvers are
                notified and decide from their approvals inbox.
            </p>

            <form
                v-if="can.order"
                aria-labelledby="order-heading"
                class="grid gap-3 rounded-xl border bg-card p-5 shadow-xs"
                @submit.prevent="changeStatus('ordered')"
            >
                <div>
                    <h2 id="order-heading" class="font-semibold">
                        Place the order
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Approved. Once you have ordered it from
                        {{ purchase.supplier?.name ?? 'the supplier' }}, mark it
                        here.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <FormField
                        v-slot="field"
                        label="Supplier order number"
                        optional
                        :error="statusForm.errors.supplier_reference"
                        class="min-w-56 flex-1 sm:max-w-xs"
                    >
                        <Input
                            v-bind="field"
                            v-model="statusForm.supplier_reference"
                            maxlength="80"
                        />
                    </FormField>
                    <Button type="submit" :disabled="statusForm.processing">
                        <Spinner
                            v-if="
                                statusForm.processing &&
                                statusForm.status === 'ordered'
                            "
                        />
                        <Truck v-else />
                        Mark as ordered
                    </Button>
                </div>
            </form>

            <form
                v-if="can.receive"
                aria-labelledby="receive-heading"
                class="grid gap-3 rounded-xl border bg-card p-5 shadow-xs"
                @submit.prevent="receive"
            >
                <div>
                    <h2 id="receive-heading" class="font-semibold">
                        Receive the delivery
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ formatNumber(outstanding) }} of
                        {{ formatNumber(purchase.quantity) }} still to come.
                        {{
                            purchase.item
                                ? 'What you receive is booked into stock.'
                                : ''
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <FormField
                        v-slot="field"
                        label="Quantity received"
                        :error="receiveForm.errors.quantity"
                        class="w-40"
                    >
                        <Input
                            v-bind="field"
                            v-model="receiveForm.quantity"
                            type="number"
                            min="1"
                            :max="outstanding"
                            class="figures"
                            required
                        />
                    </FormField>
                    <FormField
                        v-if="purchase.item"
                        v-slot="field"
                        label="Received at"
                        :error="receiveForm.errors.location_id"
                        class="min-w-48 flex-1 sm:max-w-xs"
                    >
                        <Select v-model="receiveForm.location_id">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue placeholder="Choose a location"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="location.id"
                                    >{{ location.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <Button type="submit" :disabled="receiveForm.processing">
                        <Spinner v-if="receiveForm.processing" />
                        <PackageCheck v-else />
                        Receive delivery
                    </Button>
                </div>
            </form>

            <section
                v-if="purchase.reason"
                aria-labelledby="reason-heading"
                class="grid gap-2"
            >
                <h2 id="reason-heading" class="text-sm font-semibold">
                    Why it is needed
                </h2>
                <p class="text-sm leading-relaxed whitespace-pre-line">
                    {{ purchase.reason }}
                </p>
            </section>

            <section
                v-if="approvals.data.length"
                aria-labelledby="approvals-heading"
                class="grid gap-3"
            >
                <h2 id="approvals-heading" class="text-sm font-semibold">
                    Approvals
                </h2>
                <ul
                    class="divide-y overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <li v-for="approval in approvals.data" :key="approval.id">
                        <Link
                            :href="showApproval({ approval: approval.id })"
                            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-accent/40"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <Stamp
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span
                                    class="figures text-xs text-muted-foreground"
                                    >{{ approval.reference }}</span
                                >
                                <span class="truncate text-sm font-medium">{{
                                    approval.title
                                }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="text-xs text-muted-foreground">{{
                                    approval.decider?.name ??
                                    approval.approver?.name ??
                                    (approval.approver_role
                                        ? `Anyone in ${approval.approver_role.label}`
                                        : '')
                                }}</span>
                                <EnumBadge :option="approval.status" />
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section
                v-if="receipts.data.length"
                aria-labelledby="receipts-heading"
                class="grid gap-3"
            >
                <h2 id="receipts-heading" class="text-sm font-semibold">
                    Deliveries received
                </h2>
                <div
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <MovementList :movements="receipts.data" />
                </div>
            </section>

            <section aria-labelledby="history-heading" class="grid gap-3">
                <h2 id="history-heading" class="text-sm font-semibold">
                    Audit trail
                </h2>
                <div v-if="history === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-9 w-full" />
                </div>
                <ol v-else-if="history.length">
                    <ActivityItem
                        v-for="(entry, index) in history"
                        :key="entry.id"
                        :entry="entry"
                        :timezone="organization?.timezone"
                        :connected="index < history.length - 1"
                    />
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    Nothing recorded yet.
                </p>
            </section>
        </main>

        <aside
            class="grid h-fit gap-5 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6"
        >
            <div class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground">
                    Requested by
                </p>
                <p
                    v-if="purchase.requester"
                    class="flex items-center gap-2 text-sm"
                >
                    <MemberAvatar
                        :name="purchase.requester.name"
                        :avatar="purchase.requester.avatar"
                        class="size-6"
                    />
                    {{ purchase.requester.name }}
                </p>
                <p v-else class="text-sm">A workflow</p>
                <p class="text-xs text-muted-foreground">
                    {{
                        formatDateTime(
                            purchase.created_at,
                            organization?.timezone,
                        )
                    }}
                </p>
            </div>

            <div v-if="purchase.item" class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground">
                    Stock item
                </p>
                <Link
                    :href="showItem({ item: purchase.item.id })"
                    class="text-sm font-medium hover:underline"
                >
                    <span
                        class="figures text-xs font-normal text-muted-foreground"
                        >{{ purchase.item.sku }}</span
                    >
                    {{ purchase.item.name }}
                </Link>
                <p class="text-xs text-muted-foreground">
                    {{ purchase.item.stock_label }} on hand
                </p>
            </div>

            <dl class="grid gap-4 text-sm">
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Supplier
                    </dt>
                    <dd>{{ purchase.supplier?.name ?? 'Not decided' }}</dd>
                </div>
                <div v-if="purchase.supplier_reference" class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Supplier order number
                    </dt>
                    <dd class="figures">{{ purchase.supplier_reference }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Deliver to
                    </dt>
                    <dd>
                        {{ purchase.deliver_to?.name ?? 'Decide on arrival' }}
                    </dd>
                </div>
                <div v-if="purchase.needed_by" class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Needed by
                    </dt>
                    <dd>
                        <DueDate
                            :date="purchase.needed_by"
                            :done="!purchase.is_open"
                            class="text-sm"
                        />
                    </dd>
                </div>
                <div v-if="purchase.received_quantity > 0" class="grid gap-1">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Received so far
                    </dt>
                    <dd class="figures">
                        {{ formatNumber(purchase.received_quantity) }} of
                        {{ formatNumber(purchase.quantity) }}
                    </dd>
                </div>
            </dl>

            <div v-if="runs.data.length" class="grid gap-2">
                <p class="text-xs font-medium text-muted-foreground">
                    Workflow runs
                </p>
                <Link
                    v-for="run in runs.data"
                    :key="run.id"
                    :href="showRun({ run: run.id })"
                    class="flex items-center justify-between gap-2 text-sm font-medium hover:underline"
                >
                    <span class="flex min-w-0 items-center gap-1.5">
                        <GitBranch class="size-4 shrink-0" aria-hidden="true" />
                        <span class="truncate">{{
                            run.workflow?.name ?? run.reference
                        }}</span>
                    </span>
                    <EnumBadge :option="run.status" variant="plain" />
                </Link>
            </div>

            <Button
                v-if="can.cancel"
                variant="outline"
                class="justify-self-start"
                @click="cancelling = true"
            >
                <Ban />
                Cancel request
            </Button>
        </aside>

        <ConfirmDialog
            v-model:open="cancelling"
            :title="`Cancel ${purchase.reference}?`"
            description="Nobody will order or receive it. The request and its history stay on record."
            confirm-label="Cancel request"
            destructive
            :processing="statusForm.processing"
            @confirm="changeStatus('cancelled', () => (cancelling = false))"
        />
    </div>
</template>
