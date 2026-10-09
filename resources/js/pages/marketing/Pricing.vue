<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Minus } from '@lucide/vue';
import { computed } from 'vue';
import FaqList from '@/components/marketing/FaqList.vue';
import PricingPlans from '@/components/marketing/PricingPlans.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import type {
    FaqItem,
    PlanCard,
    PlanComparison,
    Trial,
} from '@/types/marketing';

defineProps<{
    plans: PlanCard[];
    comparison: PlanComparison;
    faqs: FaqItem[];
    trial: Trial;
    salesEmail: string;
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);
</script>

<template>
    <Head title="Pricing" />

    <section aria-labelledby="pricing-heading" class="relative overflow-hidden">
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-dot-grid [mask-image:radial-gradient(ellipse_at_top,black_25%,transparent_70%)]"
        />
        <div
            class="relative mx-auto grid w-full max-w-7xl gap-14 px-4 pt-16 pb-20 sm:px-6 sm:pt-24 lg:px-8"
        >
            <div class="mx-auto grid max-w-3xl gap-4 text-center">
                <p class="text-sm font-semibold text-primary">Pricing</p>
                <h1
                    id="pricing-heading"
                    class="font-display text-4xl leading-tight font-semibold text-balance sm:text-5xl"
                >
                    Simple plans that grow with your operations
                </h1>
                <p class="text-lg text-pretty text-muted-foreground">
                    One monthly price per organization, not per person. Every
                    new organization starts with {{ trial.days }} days of
                    {{ trial.plan }}, then stays on Free until you choose a
                    plan.
                </p>
            </div>
            <PricingPlans
                :plans="plans"
                :trial="trial"
                :sales-email="salesEmail"
            />
        </div>
    </section>

    <section aria-labelledby="compare-heading" class="border-y bg-card">
        <div
            class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-20 sm:px-6 lg:px-8"
        >
            <div class="grid gap-2">
                <h2
                    id="compare-heading"
                    class="font-display text-2xl font-semibold sm:text-3xl"
                >
                    Compare plans
                </h2>
                <p class="text-muted-foreground">
                    Limits apply per organization. Higher plans include
                    everything in the plans before them.
                </p>
            </div>
            <div class="overflow-x-auto rounded-2xl border bg-background">
                <table class="w-full min-w-[40rem] text-sm">
                    <caption class="sr-only">
                        What each plan includes
                    </caption>
                    <thead>
                        <tr class="border-b text-left">
                            <th
                                scope="col"
                                class="w-1/3 px-5 py-4 font-medium text-muted-foreground"
                            >
                                <span class="sr-only">Limit or feature</span>
                            </th>
                            <th
                                v-for="plan in comparison.plans"
                                :key="plan"
                                scope="col"
                                class="px-4 py-4 text-center font-display text-base font-semibold"
                            >
                                {{ plan }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th
                                colspan="5"
                                scope="colgroup"
                                class="bg-muted/60 px-5 py-2 text-left text-xs font-semibold text-muted-foreground"
                            >
                                Limits
                            </th>
                        </tr>
                        <tr
                            v-for="row in comparison.limits"
                            :key="row.label"
                            class="border-b"
                        >
                            <th
                                scope="row"
                                class="px-5 py-3.5 text-left font-medium"
                            >
                                {{ row.label }}
                            </th>
                            <td
                                v-for="(value, index) in row.values"
                                :key="index"
                                class="px-4 py-3.5 text-center figures"
                            >
                                <template v-if="value !== null">{{
                                    value
                                }}</template>
                                <span
                                    v-else
                                    class="inline-flex text-muted-foreground"
                                    ><Minus
                                        class="size-4"
                                        aria-hidden="true"
                                    /><span class="sr-only"
                                        >Not included</span
                                    ></span
                                >
                            </td>
                        </tr>
                        <tr>
                            <th
                                colspan="5"
                                scope="colgroup"
                                class="bg-muted/60 px-5 py-2 text-left text-xs font-semibold text-muted-foreground"
                            >
                                Features
                            </th>
                        </tr>
                        <tr
                            v-for="row in comparison.features"
                            :key="row.label"
                            class="border-b last:border-0"
                        >
                            <th
                                scope="row"
                                class="px-5 py-3.5 text-left font-medium"
                            >
                                {{ row.label }}
                            </th>
                            <td
                                v-for="(included, index) in row.included"
                                :key="index"
                                class="px-4 py-3.5 text-center"
                            >
                                <span
                                    v-if="included"
                                    class="inline-flex text-success-text"
                                    ><Check
                                        class="size-4"
                                        aria-hidden="true"
                                    /><span class="sr-only"
                                        >Included</span
                                    ></span
                                >
                                <span
                                    v-else
                                    class="inline-flex text-muted-foreground"
                                    ><Minus
                                        class="size-4"
                                        aria-hidden="true"
                                    /><span class="sr-only"
                                        >Not included</span
                                    ></span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-muted-foreground">
                Every plan includes projects, tasks, issues, inventory and
                purchasing, the workflow builder, notifications, roles and
                permissions, two-factor sign-in and the activity log.
            </p>
        </div>
    </section>

    <section
        aria-labelledby="billing-faq-heading"
        class="mx-auto grid w-full max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:px-8"
    >
        <div class="grid content-start gap-3">
            <p class="text-sm font-semibold text-primary">FAQ</p>
            <h2
                id="billing-faq-heading"
                class="font-display text-3xl leading-tight font-semibold text-balance"
            >
                Plans and billing
            </h2>
            <p class="text-muted-foreground">
                Something else?
                <a
                    :href="`mailto:${salesEmail}`"
                    class="font-medium text-primary hover:underline"
                    >Write to us</a
                >
                and we will reply.
            </p>
        </div>
        <FaqList :items="faqs" />
    </section>

    <section aria-labelledby="pricing-cta-heading" class="border-t bg-card">
        <div
            class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-6 px-4 py-14 sm:px-6 lg:px-8"
        >
            <div class="grid gap-1">
                <h2
                    id="pricing-cta-heading"
                    class="font-display text-2xl font-semibold"
                >
                    Try every {{ trial.plan }} feature for {{ trial.days }} days
                </h2>
                <p class="text-muted-foreground">
                    No card needed. Nothing is deleted when the trial ends.
                </p>
            </div>
            <Button v-if="signedIn" as-child size="lg">
                <Link :href="dashboard()">Open FlowPilot<ArrowRight /></Link>
            </Button>
            <Button v-else as-child size="lg">
                <Link :href="register()">Start free</Link>
            </Button>
        </div>
    </section>
</template>
