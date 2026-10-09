<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Ban,
    CalendarPlus,
    Check,
    History,
    Inbox,
    Layers,
    ShieldCheck,
    ShieldOff,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import UsageMeters from '@/components/billing/UsageMeters.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatTile from '@/components/reports/StatTile.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, plural, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { audit as auditLog, dashboard } from '@/routes/platform';
import { index, show } from '@/routes/platform/organizations';
import { update as changePlan } from '@/routes/platform/organizations/plan';
import {
    destroy as reactivate,
    store as suspend,
} from '@/routes/platform/organizations/suspension';
import { update as extendTrial } from '@/routes/platform/organizations/trial';
import { decline as declineRequest } from '@/routes/platform/plan-requests';
import type { ActivityEntry } from '@/types/activity';
import type { BillingSummary } from '@/types/billing';
import type {
    PlanChoice,
    PlatformMember,
    PlatformOrganizationRequest,
} from '@/types/platform';
import type { Tone } from '@/types/ui';

const props = defineProps<{
    tenant: {
        id: string;
        name: string;
        slug: string;
        status: 'active' | 'suspended';
        industry: string | null;
        timezone: string;
        currency: string;
        owner: { name: string; email: string };
        created_at: string | null;
    };
    billing: BillingSummary;
    limits: { key: string; label: string }[];
    limitOverrides: Record<string, number | null> | null;
    plans: PlanChoice[];
    activity: {
        workflows: number;
        runs: number;
        failed_runs: number;
        ai_briefs: number;
    };
    members: PlatformMember[];
    requests: PlatformOrganizationRequest[];
    openInvitations: number;
    audit?: ActivityEntry[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'Organizations', href: index() },
        { title: props.tenant.name, href: show(props.tenant.slug) },
    ],
});

const suspended = computed(() => props.tenant.status === 'suspended');

const requestTones: Record<PlatformOrganizationRequest['status'], Tone> = {
    pending: 'warning',
    approved: 'success',
    declined: 'danger',
    withdrawn: 'neutral',
};

const requestLabels: Record<PlatformOrganizationRequest['status'], string> = {
    pending: 'Waiting',
    approved: 'Approved',
    declined: 'Declined',
    withdrawn: 'Withdrawn',
};

// Changing the plan, directly or to settle a request.
const planOpen = ref(false);
const planForm = useForm({
    plan: props.billing.subscribed_plan?.value ?? 'free',
    note: '',
    limits: Object.fromEntries(
        props.limits.map((limit) => [
            limit.key,
            props.limitOverrides?.[limit.key]?.toString() ?? '',
        ]),
    ) as Record<string, string>,
});

function openPlan(plan?: string) {
    planForm.reset();
    planForm.clearErrors();

    if (plan) {
        planForm.plan = plan;
    }

    planOpen.value = true;
}

function savePlan() {
    planForm
        .transform((data) => ({
            plan: data.plan,
            note: data.note || null,
            limits: data.plan === 'enterprise' ? data.limits : null,
        }))
        .submit(changePlan(props.tenant.slug), {
            preserveScroll: true,
            onSuccess: () => (planOpen.value = false),
        });
}

// Extending a trial.
const trialOpen = ref(false);
const paidPlans = computed(() =>
    props.plans.filter((plan) => plan.value !== 'free'),
);
const trialForm = useForm({
    days: 14,
    plan:
        props.billing.subscribed_plan &&
        props.billing.subscribed_plan.value !== 'free'
            ? props.billing.subscribed_plan.value
            : 'business',
});

function openTrial() {
    trialForm.reset();
    trialForm.clearErrors();
    trialOpen.value = true;
}

function saveTrial() {
    trialForm.submit(extendTrial(props.tenant.slug), {
        preserveScroll: true,
        onSuccess: () => (trialOpen.value = false),
    });
}

// Suspending and reactivating.
const suspendOpen = ref(false);
const suspendForm = useForm({ note: '' });

function openSuspend() {
    suspendForm.reset();
    suspendForm.clearErrors();
    suspendOpen.value = true;
}

function saveSuspend() {
    suspendForm.submit(suspend(props.tenant.slug), {
        preserveScroll: true,
        onSuccess: () => (suspendOpen.value = false),
    });
}

const reactivateOpen = ref(false);
const reactivating = ref(false);

function confirmReactivate() {
    router.delete(reactivate(props.tenant.slug).url, {
        preserveScroll: true,
        onStart: () => (reactivating.value = true),
        onFinish: () => (reactivating.value = false),
        onSuccess: () => (reactivateOpen.value = false),
    });
}

// Declining an upgrade request.
const declining = ref<PlatformOrganizationRequest | null>(null);
const declineOpen = ref(false);
const declineForm = useForm({ note: '' });

function openDecline(request: PlatformOrganizationRequest) {
    declining.value = request;
    declineForm.reset();
    declineForm.clearErrors();
    declineOpen.value = true;
}

function saveDecline() {
    if (!declining.value) {
        return;
    }

    declineForm.submit(declineRequest(declining.value.id), {
        preserveScroll: true,
        onSuccess: () => (declineOpen.value = false),
    });
}
</script>

<template>
    <Head :title="tenant.name" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader :title="tenant.name">
            <template #eyebrow>
                <p
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="figures">{{ tenant.slug }}</span>
                    <StatusBadge v-if="suspended" tone="danger"
                        >Suspended</StatusBadge
                    >
                    <StatusBadge v-else tone="success">Active</StatusBadge>
                </p>
            </template>
            <template #description>
                Owned by {{ tenant.owner.name }} ({{ tenant.owner.email }}).
                Joined {{ formatDate(tenant.created_at) }}.
                {{
                    [tenant.industry, tenant.timezone, tenant.currency]
                        .filter(Boolean)
                        .join(' · ')
                }}
            </template>
            <template #actions>
                <Button @click="openPlan()">
                    <Layers />
                    Change plan
                </Button>
                <Button variant="outline" @click="openTrial">
                    <CalendarPlus />
                    Extend trial
                </Button>
                <Button
                    v-if="suspended"
                    variant="outline"
                    @click="reactivateOpen = true"
                >
                    <ShieldCheck />
                    Reactivate organization
                </Button>
                <Button
                    v-else
                    variant="outline"
                    class="text-danger-text hover:text-danger-text"
                    @click="openSuspend"
                >
                    <Ban />
                    Suspend organization
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="suspended"
            role="status"
            class="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-soft/60 px-4 py-3 text-sm text-danger-text"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                Members cannot open this organization and its workflows do not
                start. Nothing has been deleted; reactivating restores
                everything.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid min-w-0 content-start gap-6">
                <section
                    aria-labelledby="plan-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div class="grid gap-1 px-5 py-5 sm:px-6">
                        <p class="text-sm text-muted-foreground">Plan</p>
                        <h2
                            id="plan-heading"
                            class="flex flex-wrap items-center gap-3"
                        >
                            <span class="font-display text-2xl font-semibold">{{
                                billing.plan.label
                            }}</span>
                            <EnumBadge
                                v-if="billing.status"
                                :option="billing.status"
                            />
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="billing.on_trial">
                                Trying {{ billing.subscribed_plan?.label }}
                                until
                                {{
                                    formatDate(
                                        billing.trial_ends_at,
                                        tenant.timezone,
                                    )
                                }}
                                ({{ billing.trial_days_left }}
                                {{
                                    billing.trial_days_left === 1
                                        ? 'day'
                                        : 'days'
                                }}
                                left).
                            </template>
                            <template v-else-if="billing.current_period_end">
                                Current period ends
                                {{
                                    formatDate(
                                        billing.current_period_end,
                                        tenant.timezone,
                                    )
                                }}.
                            </template>
                            <template v-else-if="!billing.status"
                                >No subscription yet; Free limits
                                apply.</template
                            >
                            <template v-if="billing.has_custom_limits">
                                Custom limits are set.</template
                            >
                        </p>
                    </div>
                    <UsageMeters
                        :usage="billing.usage"
                        used-up-message="Used up. Nothing more can be added on this plan."
                        class="border-t px-5 py-5 sm:px-6"
                    />
                </section>

                <section
                    aria-labelledby="requests-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <h2
                        id="requests-heading"
                        class="border-b px-5 py-3.5 font-display text-sm font-semibold"
                    >
                        Plan requests
                    </h2>
                    <EmptyState
                        v-if="requests.length === 0"
                        compact
                        :icon="Inbox"
                        title="No requests"
                        description="When the owner asks for another plan from Plan and billing, it shows up here."
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="request in requests"
                            :key="request.id"
                            class="grid gap-2 px-5 py-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="text-sm">
                                    <span class="font-medium">{{
                                        request.from === request.to.label
                                            ? `Keep ${request.to.label} after the trial`
                                            : `${request.from} to ${request.to.label}`
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        · asked by
                                        {{
                                            request.requester ??
                                            'a former member'
                                        }}
                                        {{ timeAgo(request.created_at) }}</span
                                    >
                                </p>
                                <StatusBadge
                                    :tone="requestTones[request.status]"
                                    >{{
                                        requestLabels[request.status]
                                    }}</StatusBadge
                                >
                            </div>
                            <p
                                v-if="request.message"
                                class="text-sm text-pretty text-muted-foreground"
                            >
                                “{{ request.message }}”
                            </p>
                            <p
                                v-if="request.decision_note"
                                class="text-sm text-pretty"
                            >
                                <span class="text-muted-foreground"
                                    >Reply:</span
                                >
                                {{ request.decision_note }}
                            </p>
                            <div
                                v-if="request.status === 'pending'"
                                class="flex flex-wrap gap-2 pt-1"
                            >
                                <Button
                                    size="sm"
                                    @click="openPlan(request.to.value)"
                                >
                                    <Check />
                                    Move to {{ request.to.label }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="openDecline(request)"
                                >
                                    <X />
                                    Decline request
                                </Button>
                            </div>
                        </li>
                    </ul>
                </section>

                <section
                    aria-labelledby="members-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-3 border-b px-5 py-3.5"
                    >
                        <h2
                            id="members-heading"
                            class="font-display text-sm font-semibold"
                        >
                            Members
                        </h2>
                        <p
                            v-if="openInvitations > 0"
                            class="text-xs text-muted-foreground"
                        >
                            {{ plural(openInvitations, 'invitation') }} not
                            accepted yet, holding
                            {{ openInvitations === 1 ? 'a seat' : 'seats' }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th
                                        scope="col"
                                        class="py-2.5 pr-3 pl-5 font-medium"
                                    >
                                        Person
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        Role
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                    >
                                        Two-factor
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-2.5 pr-5 pl-3 text-right font-medium"
                                    >
                                        Last active
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                    :class="
                                        cn(
                                            'border-b last:border-0',
                                            member.status === 'suspended' &&
                                                'text-muted-foreground',
                                        )
                                    "
                                >
                                    <td class="py-3 pr-3 pl-5">
                                        <p
                                            class="flex items-center gap-2 font-medium whitespace-nowrap"
                                        >
                                            {{ member.name }}
                                            <StatusBadge
                                                v-if="
                                                    member.status ===
                                                    'suspended'
                                                "
                                                tone="neutral"
                                                >Suspended</StatusBadge
                                            >
                                        </p>
                                        <p
                                            class="text-xs whitespace-nowrap text-muted-foreground"
                                        >
                                            {{ member.email }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        {{ member.role }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span
                                            v-if="member.two_factor"
                                            class="inline-flex items-center gap-1.5 text-success-text"
                                            ><ShieldCheck
                                                class="size-4"
                                                aria-hidden="true"
                                            />On</span
                                        >
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                            ><ShieldOff
                                                class="size-4"
                                                aria-hidden="true"
                                            />Off</span
                                        >
                                    </td>
                                    <td
                                        class="py-3 pr-5 pl-3 text-right whitespace-nowrap text-muted-foreground"
                                    >
                                        {{
                                            member.last_active_at
                                                ? timeAgo(member.last_active_at)
                                                : 'Never'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="grid min-w-0 content-start gap-6">
                <section aria-label="Usage, last 30 days" class="grid gap-3">
                    <h2
                        class="font-display text-sm font-semibold text-muted-foreground"
                    >
                        Last 30 days
                    </h2>
                    <div class="grid grid-cols-2 gap-3">
                        <StatTile
                            label="Workflows"
                            :value="activity.workflows"
                        />
                        <StatTile
                            label="Workflow runs"
                            :value="activity.runs"
                        />
                        <StatTile
                            label="Failed runs"
                            :value="activity.failed_runs"
                            :tone="activity.failed_runs > 0 ? 'warning' : null"
                        />
                        <StatTile
                            label="AI briefs"
                            :value="activity.ai_briefs"
                        />
                    </div>
                </section>

                <section
                    aria-labelledby="audit-heading"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b px-5 py-3"
                    >
                        <h2
                            id="audit-heading"
                            class="font-display text-sm font-semibold"
                        >
                            Recent activity
                        </h2>
                        <Button
                            as-child
                            variant="ghost"
                            size="sm"
                            class="-mr-2"
                        >
                            <Link
                                :href="
                                    auditLog({
                                        query: { organization: tenant.slug },
                                    })
                                "
                                >All activity<ArrowRight
                            /></Link>
                        </Button>
                    </div>
                    <div
                        v-if="audit === undefined"
                        class="grid gap-4 p-5"
                        aria-busy="true"
                    >
                        <div v-for="n in 4" :key="n" class="flex gap-3">
                            <Skeleton class="size-8 rounded-full" />
                            <div class="flex-1 space-y-2 pt-1">
                                <Skeleton class="h-3.5 w-5/6" />
                                <Skeleton class="h-3 w-1/3" />
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else-if="audit.length === 0"
                        compact
                        :icon="History"
                        title="Nothing recorded yet"
                        description="Changes made in the organization are listed here."
                    />
                    <ol v-else class="px-5 pt-4 pb-4">
                        <ActivityItem
                            v-for="(entry, position) in audit"
                            :key="entry.id"
                            :entry="entry"
                            :timezone="tenant.timezone"
                            :connected="position < audit.length - 1"
                        />
                    </ol>
                </section>
            </div>
        </div>

        <Dialog v-model:open="planOpen">
            <DialogContent class="sm:max-w-lg">
                <form class="grid gap-5" @submit.prevent="savePlan">
                    <DialogHeader>
                        <DialogTitle>Change plan</DialogTitle>
                        <DialogDescription>
                            The plan applies straight away and ends any trial.
                            The owner and billing contacts are told.
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Plan</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="plan in plans"
                                :key="plan.value"
                                :class="
                                    cn(
                                        'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors has-focus-visible:ring-2 has-focus-visible:ring-ring',
                                        planForm.plan === plan.value
                                            ? 'border-primary bg-primary/5 font-medium'
                                            : 'hover:bg-muted/50',
                                    )
                                "
                            >
                                <input
                                    v-model="planForm.plan"
                                    type="radio"
                                    name="plan"
                                    :value="plan.value"
                                    class="size-4 accent-primary"
                                />
                                {{ plan.label }}
                            </label>
                        </div>
                        <p
                            v-if="planForm.errors.plan"
                            class="text-sm text-danger-text"
                        >
                            {{ planForm.errors.plan }}
                        </p>
                    </fieldset>

                    <fieldset
                        v-if="planForm.plan === 'enterprise'"
                        class="grid gap-3"
                    >
                        <legend class="mb-1 text-sm font-medium">
                            Custom limits
                        </legend>
                        <p class="-mt-1 text-sm text-muted-foreground">
                            Leave a field empty for no limit.
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <FormField
                                v-for="limit in limits"
                                :key="limit.key"
                                v-slot="field"
                                :label="limit.label"
                                :error="
                                    (planForm.errors as Record<string, string>)[
                                        `limits.${limit.key}`
                                    ]
                                "
                            >
                                <Input
                                    v-bind="field"
                                    v-model="planForm.limits[limit.key]"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    placeholder="No limit"
                                />
                            </FormField>
                        </div>
                    </fieldset>

                    <FormField
                        v-slot="field"
                        label="Note to the owner"
                        optional
                        help="Added to the message the owner receives."
                        :error="planForm.errors.note"
                    >
                        <Textarea
                            v-bind="field"
                            v-model="planForm.note"
                            rows="3"
                            maxlength="500"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="planForm.processing"
                            @click="planOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="planForm.processing">
                            <Spinner v-if="planForm.processing" />
                            Change plan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="trialOpen">
            <DialogContent class="sm:max-w-md">
                <form class="grid gap-5" @submit.prevent="saveTrial">
                    <DialogHeader>
                        <DialogTitle>Extend trial</DialogTitle>
                        <DialogDescription>
                            Days are added to a running trial. Without one, a
                            new trial starts today.
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Plan to try
                        </legend>
                        <div class="grid grid-cols-3 gap-2">
                            <label
                                v-for="plan in paidPlans"
                                :key="plan.value"
                                :class="
                                    cn(
                                        'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors has-focus-visible:ring-2 has-focus-visible:ring-ring',
                                        trialForm.plan === plan.value
                                            ? 'border-primary bg-primary/5 font-medium'
                                            : 'hover:bg-muted/50',
                                    )
                                "
                            >
                                <input
                                    v-model="trialForm.plan"
                                    type="radio"
                                    name="trial-plan"
                                    :value="plan.value"
                                    class="size-4 accent-primary"
                                />
                                {{ plan.label }}
                            </label>
                        </div>
                        <p
                            v-if="trialForm.errors.plan"
                            class="text-sm text-danger-text"
                        >
                            {{ trialForm.errors.plan }}
                        </p>
                    </fieldset>

                    <FormField
                        v-slot="field"
                        label="Days to add"
                        help="Between 1 and 90."
                        :error="trialForm.errors.days"
                    >
                        <Input
                            v-bind="field"
                            v-model.number="trialForm.days"
                            type="number"
                            min="1"
                            max="90"
                            inputmode="numeric"
                            class="w-32"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="trialForm.processing"
                            @click="trialOpen = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="trialForm.processing">
                            <Spinner v-if="trialForm.processing" />
                            Extend trial
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="suspendOpen">
            <DialogContent class="sm:max-w-md">
                <form class="grid gap-5" @submit.prevent="saveSuspend">
                    <DialogHeader>
                        <DialogTitle>Suspend {{ tenant.name }}?</DialogTitle>
                        <DialogDescription>
                            Members lose access and workflows stop starting
                            until you reactivate it. Nothing is deleted.
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        v-slot="field"
                        label="Reason"
                        help="Kept in the audit log."
                        :error="suspendForm.errors.note"
                    >
                        <Textarea
                            v-bind="field"
                            v-model="suspendForm.note"
                            rows="3"
                            maxlength="500"
                            required
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="suspendForm.processing"
                            @click="suspendOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="suspendForm.processing"
                        >
                            <Spinner v-if="suspendForm.processing" />
                            Suspend organization
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="reactivateOpen"
            :title="`Reactivate ${tenant.name}?`"
            description="Members can open the organization again and its workflows start as usual."
            confirm-label="Reactivate organization"
            :processing="reactivating"
            @confirm="confirmReactivate"
        />

        <Dialog v-model:open="declineOpen">
            <DialogContent class="sm:max-w-md">
                <form class="grid gap-5" @submit.prevent="saveDecline">
                    <DialogHeader>
                        <DialogTitle
                            >Decline the move to
                            {{ declining?.to.label }}?</DialogTitle
                        >
                        <DialogDescription>
                            The owner and billing contacts receive your reply.
                            The plan stays as it is.
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        v-slot="field"
                        label="Reply to the owner"
                        :error="declineForm.errors.note"
                    >
                        <Textarea
                            v-bind="field"
                            v-model="declineForm.note"
                            rows="3"
                            maxlength="500"
                            required
                        />
                    </FormField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="declineForm.processing"
                            @click="declineOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="declineForm.processing"
                        >
                            <Spinner v-if="declineForm.processing" />
                            Decline request
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
