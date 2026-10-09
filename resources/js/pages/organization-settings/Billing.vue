<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Check, Mail, Minus, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import FormField from '@/components/FormField.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useOrganization } from '@/composables/useOrganization';
import { formatValue } from '@/lib/charts';
import { formatDate, formatNumber, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { free as moveToFree, show } from '@/routes/billing';
import { update as updateDetails } from '@/routes/billing/details';
import {
    destroy as withdrawRequest,
    store as requestPlan,
} from '@/routes/billing/plan-requests';
import { show as settingsShow } from '@/routes/organization-settings';
import type { EnumOption } from '@/types/operations';

type UsageLine = {
    key: string;
    label: string;
    used: number;
    limit: number | null;
    unit: 'count' | 'mb';
};

type PlanCard = {
    value: string;
    label: string;
    tagline: string;
    price: string | null;
    monthly_price: number | null;
    rank: number;
    limits: { label: string; included: boolean }[];
    features: { value: string; label: string; included: boolean }[];
};

const props = defineProps<{
    billing: {
        plan: { value: string; label: string; price: string | null };
        subscribed_plan: { value: string; label: string } | null;
        status: EnumOption | null;
        on_trial: boolean;
        trial_ends_at: string | null;
        trial_days_left: number | null;
        current_period_end: string | null;
        has_custom_limits: boolean;
        usage: UsageLine[];
    };
    plans: PlanCard[];
    pendingRequest: {
        id: string;
        to: { value: string; label: string };
        requester: string | null;
        created_at: string | null;
    } | null;
    details: {
        legal_name: string | null;
        email: string | null;
        address: string | null;
        country: string | null;
        tax_id: string | null;
    };
    salesEmail: string;
    can: { manage: boolean };
}>();

const { organization } = useOrganization();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: settingsShow() },
        { title: 'Plan and billing', href: show() },
    ],
});

const currentRank = computed(
    () =>
        props.plans.find((plan) => plan.value === props.billing.plan.value)
            ?.rank ?? 0,
);

function usagePercent(line: UsageLine): number | null {
    if (line.limit === null) {
        return null;
    }

    if (line.limit === 0) {
        return line.used > 0 ? 100 : 0;
    }

    return Math.min(100, Math.round((line.used / line.limit) * 100));
}

function usageText(line: UsageLine): string {
    const amount = (value: number) =>
        line.unit === 'mb'
            ? formatValue(value * 1_048_576, 'bytes')
            : formatNumber(value);

    if (line.limit === null) {
        return `${amount(line.used)} used, no limit`;
    }

    if (line.limit === 0) {
        return 'Not included';
    }

    return `${amount(line.used)} of ${amount(line.limit)}`;
}

function meterTone(percent: number | null): string {
    if (percent === null || percent < 80) {
        return 'bg-primary';
    }

    return percent >= 100 ? 'bg-danger' : 'bg-warning';
}

// Asking for a higher plan.
const requesting = ref<PlanCard | null>(null);
const requestOpen = ref(false);
const requestForm = useForm({ plan: '', message: '' });

function askFor(plan: PlanCard) {
    requesting.value = plan;
    requestForm.plan = plan.value;
    requestForm.message = '';
    requestForm.clearErrors();
    requestOpen.value = true;
}

function sendRequest() {
    requestForm.submit(requestPlan(), {
        preserveScroll: true,
        onSuccess: () => (requestOpen.value = false),
    });
}

const withdrawing = ref(false);

function withdraw() {
    if (!props.pendingRequest) {
        return;
    }

    router.delete(
        withdrawRequest({ planRequest: props.pendingRequest.id }).url,
        {
            preserveScroll: true,
            onStart: () => (withdrawing.value = true),
            onFinish: () => (withdrawing.value = false),
        },
    );
}

// Moving to Free.
const confirmingFree = ref(false);
const movingToFree = ref(false);

function confirmFree() {
    router.post(
        moveToFree().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (movingToFree.value = true),
            onFinish: () => (movingToFree.value = false),
            onSuccess: () => (confirmingFree.value = false),
        },
    );
}

function action(plan: PlanCard): 'current' | 'request' | 'free' | 'none' {
    if (plan.value === props.billing.plan.value && !props.billing.on_trial) {
        return 'current';
    }

    if (plan.value === 'free') {
        return currentRank.value > 0 ? 'free' : 'none';
    }

    return plan.rank > currentRank.value || props.billing.on_trial
        ? 'request'
        : 'none';
}

const detailsForm = useForm({
    legal_name: props.details.legal_name ?? '',
    email: props.details.email ?? '',
    address: props.details.address ?? '',
    country: props.details.country ?? '',
    tax_id: props.details.tax_id ?? '',
});

function saveDetails() {
    detailsForm
        .transform((data) => ({
            legal_name: data.legal_name || null,
            email: data.email || null,
            address: data.address || null,
            country: data.country || null,
            tax_id: data.tax_id || null,
        }))
        .submit(updateDetails(), {
            preserveScroll: true,
            onSuccess: () => detailsForm.defaults(),
        });
}

const salesLink = computed(
    () =>
        `mailto:${props.salesEmail}?subject=${encodeURIComponent(`Enterprise plan for ${organization.value?.name ?? 'our organization'}`)}`,
);
</script>

<template>
    <Head title="Plan and billing" />

    <div class="grid gap-6">
        <section
            aria-labelledby="plan-heading"
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-4 px-5 py-5 sm:px-6"
            >
                <div class="grid gap-1">
                    <p class="text-sm text-muted-foreground">Current plan</p>
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
                            You are trying
                            {{ billing.subscribed_plan?.label }} until
                            {{
                                formatDate(
                                    billing.trial_ends_at,
                                    organization?.timezone,
                                )
                            }}
                            ({{ billing.trial_days_left }}
                            {{ billing.trial_days_left === 1 ? 'day' : 'days' }}
                            left). After that the organization moves to Free
                            unless you choose a plan.
                        </template>
                        <template
                            v-else-if="
                                billing.plan.price &&
                                billing.plan.value !== 'free'
                            "
                            >{{ billing.plan.price }} a month<template
                                v-if="billing.current_period_end"
                                >, renews
                                {{
                                    formatDate(
                                        billing.current_period_end,
                                        organization?.timezone,
                                    )
                                }}</template
                            >.</template
                        >
                        <template v-else-if="billing.plan.value === 'free'"
                            >Free for as long as you like, within the limits
                            below.</template
                        >
                        <template v-else
                            >Price agreed with the FlowPilot team.</template
                        >
                        <template v-if="billing.has_custom_limits">
                            Limits were set for your organization.</template
                        >
                    </p>
                </div>
            </div>

            <div
                v-if="pendingRequest"
                class="flex flex-wrap items-center justify-between gap-3 border-t bg-info-soft/40 px-5 py-3 text-sm sm:px-6"
                role="status"
            >
                <p>
                    {{ pendingRequest.requester ?? 'Someone' }} asked to move to
                    <strong class="font-semibold">{{
                        pendingRequest.to.label
                    }}</strong>
                    {{ timeAgo(pendingRequest.created_at) }}. The FlowPilot team
                    will confirm it with you.
                </p>
                <Button
                    v-if="can.manage"
                    variant="ghost"
                    size="sm"
                    :disabled="withdrawing"
                    @click="withdraw"
                >
                    <Spinner v-if="withdrawing" />
                    <Undo2 v-else />
                    Withdraw request
                </Button>
            </div>

            <ul
                class="grid gap-x-8 gap-y-5 border-t px-5 py-5 sm:grid-cols-2 sm:px-6"
                aria-label="Usage"
            >
                <li
                    v-for="line in billing.usage"
                    :key="line.key"
                    class="grid gap-1.5"
                >
                    <div
                        class="flex items-baseline justify-between gap-3 text-sm"
                    >
                        <span class="font-medium">{{ line.label }}</span>
                        <span class="figures text-muted-foreground">{{
                            usageText(line)
                        }}</span>
                    </div>
                    <div
                        v-if="line.limit !== null && line.limit > 0"
                        class="h-2 overflow-hidden rounded-full bg-primary/15"
                        role="meter"
                        :aria-label="line.label"
                        aria-valuemin="0"
                        :aria-valuemax="line.limit"
                        :aria-valuenow="Math.min(line.used, line.limit)"
                        :aria-valuetext="usageText(line)"
                    >
                        <div
                            :class="
                                cn(
                                    'h-full rounded-full',
                                    meterTone(usagePercent(line)),
                                )
                            "
                            :style="{ width: `${usagePercent(line)}%` }"
                        />
                    </div>
                    <p
                        v-if="
                            (usagePercent(line) ?? 0) >= 100 && line.limit !== 0
                        "
                        class="text-xs text-danger-text"
                    >
                        Used up. Nothing more can be added until you upgrade.
                    </p>
                </li>
            </ul>
        </section>

        <section aria-labelledby="plans-heading" class="grid gap-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2
                    id="plans-heading"
                    class="font-display text-base font-semibold"
                >
                    Plans
                </h2>
                <p v-if="!can.manage" class="text-sm text-muted-foreground">
                    Only the owner can change the plan.
                </p>
            </div>
            <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <li
                    v-for="plan in plans"
                    :key="plan.value"
                    :class="
                        cn(
                            'flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs',
                            plan.value === billing.plan.value &&
                                'border-primary ring-1 ring-primary',
                        )
                    "
                >
                    <div class="grid gap-1">
                        <h3 class="font-display text-lg font-semibold">
                            {{ plan.label }}
                        </h3>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ plan.tagline }}
                        </p>
                    </div>
                    <p>
                        <span class="font-display text-2xl font-semibold">{{
                            plan.price ?? 'Custom'
                        }}</span>
                        <span
                            v-if="plan.price"
                            class="text-sm text-muted-foreground"
                        >
                            / month</span
                        >
                    </p>
                    <ul class="grid gap-1.5 text-sm">
                        <li
                            v-for="limit in plan.limits"
                            :key="limit.label"
                            :class="
                                cn(
                                    'flex items-start gap-2',
                                    !limit.included && 'text-muted-foreground',
                                )
                            "
                        >
                            <Check
                                v-if="limit.included"
                                class="mt-0.5 size-4 shrink-0 text-success-text"
                                aria-hidden="true"
                            />
                            <Minus
                                v-else
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {{ limit.label }}
                        </li>
                        <li
                            v-for="feature in plan.features"
                            :key="feature.value"
                            :class="
                                cn(
                                    'flex items-start gap-2',
                                    !feature.included &&
                                        'text-muted-foreground',
                                )
                            "
                        >
                            <Check
                                v-if="feature.included"
                                class="mt-0.5 size-4 shrink-0 text-success-text"
                                aria-hidden="true"
                            />
                            <Minus
                                v-else
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span
                                >{{ feature.label
                                }}<span class="sr-only">{{
                                    feature.included
                                        ? ', included'
                                        : ', not included'
                                }}</span></span
                            >
                        </li>
                    </ul>
                    <div class="mt-auto grid gap-2 pt-2">
                        <Button
                            v-if="action(plan) === 'current'"
                            variant="outline"
                            disabled
                            >Your current plan</Button
                        >
                        <template
                            v-else-if="can.manage && action(plan) === 'request'"
                        >
                            <Button
                                :disabled="
                                    pendingRequest?.to.value === plan.value
                                "
                                @click="askFor(plan)"
                            >
                                {{
                                    pendingRequest?.to.value === plan.value
                                        ? 'Requested'
                                        : `Move to ${plan.label}`
                                }}
                            </Button>
                            <Button
                                v-if="plan.value === 'enterprise'"
                                as-child
                                variant="outline"
                            >
                                <a :href="salesLink"><Mail />Talk to us</a>
                            </Button>
                        </template>
                        <Button
                            v-else-if="can.manage && action(plan) === 'free'"
                            variant="outline"
                            @click="confirmingFree = true"
                            >Move to Free</Button
                        >
                    </div>
                </li>
            </ul>
            <p class="text-xs text-pretty text-muted-foreground">
                Prices are per organization, per month. Moving to a paid plan is
                confirmed by the FlowPilot team; nothing is charged from this
                page.
            </p>
        </section>

        <SettingsPanel
            v-if="can.manage"
            title="Billing details"
            description="Who invoices are addressed to. This can differ from the organization's public name and contact."
            :processing="detailsForm.processing"
            :dirty="detailsForm.isDirty"
            :saved="detailsForm.recentlySuccessful"
            @submit="saveDetails"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField
                    v-slot="field"
                    label="Legal name"
                    optional
                    :error="detailsForm.errors.legal_name"
                >
                    <Input
                        v-bind="field"
                        v-model="detailsForm.legal_name"
                        maxlength="160"
                        autocomplete="organization"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Billing email"
                    optional
                    :error="detailsForm.errors.email"
                >
                    <Input
                        v-bind="field"
                        v-model="detailsForm.email"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                    />
                </FormField>
            </div>
            <FormField
                v-slot="field"
                label="Address"
                optional
                :error="detailsForm.errors.address"
            >
                <Textarea
                    v-bind="field"
                    v-model="detailsForm.address"
                    rows="3"
                    maxlength="500"
                    autocomplete="street-address"
                />
            </FormField>
            <div class="grid gap-5 sm:grid-cols-2">
                <FormField
                    v-slot="field"
                    label="Country"
                    optional
                    help="Two-letter code, such as US or BD."
                    :error="detailsForm.errors.country"
                >
                    <Input
                        v-bind="field"
                        v-model="detailsForm.country"
                        maxlength="2"
                        class="uppercase sm:w-24"
                        autocomplete="country"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Tax ID"
                    optional
                    :error="detailsForm.errors.tax_id"
                >
                    <Input
                        v-bind="field"
                        v-model="detailsForm.tax_id"
                        maxlength="40"
                    />
                </FormField>
            </div>
        </SettingsPanel>
    </div>

    <Dialog v-model:open="requestOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Move to {{ requesting?.label }}</DialogTitle>
                <DialogDescription>
                    The FlowPilot team will confirm the change and how you would
                    like to pay. Your plan changes once it is agreed.
                </DialogDescription>
            </DialogHeader>
            <form
                id="plan-request"
                class="grid gap-4"
                @submit.prevent="sendRequest"
            >
                <FormField
                    v-slot="field"
                    label="Anything we should know"
                    optional
                    :error="
                        requestForm.errors.message ?? requestForm.errors.plan
                    "
                >
                    <Textarea
                        v-bind="field"
                        v-model="requestForm.message"
                        rows="3"
                        maxlength="2000"
                        placeholder="How many people will use FlowPilot, billing preferences, questions…"
                    />
                </FormField>
            </form>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="requestForm.processing"
                    @click="requestOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="plan-request"
                    :disabled="requestForm.processing"
                >
                    <Spinner v-if="requestForm.processing" />
                    Request {{ requesting?.label }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="confirmingFree"
        title="Move to the Free plan?"
        description="The change is immediate. Nothing is deleted, but features outside Free stop working and nothing more can be added past the Free limits."
        confirm-label="Move to Free"
        destructive
        :processing="movingToFree"
        @confirm="confirmFree"
    />
</template>
