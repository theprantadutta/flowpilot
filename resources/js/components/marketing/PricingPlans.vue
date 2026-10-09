<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Minus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, register } from '@/routes';
import type { PlanCard, Trial } from '@/types/marketing';

/**
 * The plans side by side, from the same configuration the app enforces.
 * The trial plan is highlighted; Enterprise leads to sales.
 */
const props = defineProps<{
    plans: PlanCard[];
    trial: Trial;
    salesEmail: string;
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);

function highlighted(plan: PlanCard): boolean {
    return plan.label === props.trial.plan;
}
</script>

<template>
    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <li
            v-for="plan in plans"
            :key="plan.value"
            :class="
                cn(
                    'relative flex flex-col gap-5 rounded-2xl border bg-card p-6 shadow-xs',
                    highlighted(plan) && 'border-primary ring-1 ring-primary',
                )
            "
        >
            <p
                v-if="highlighted(plan)"
                class="absolute -top-3 left-6 rounded-full bg-primary px-2.5 py-0.5 text-xs font-medium text-primary-foreground"
            >
                Free for {{ trial.days }} days
            </p>
            <div class="grid gap-1.5">
                <h3 class="font-display text-lg font-semibold">
                    {{ plan.label }}
                </h3>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{ plan.tagline }}
                </p>
            </div>
            <p class="flex items-baseline gap-1">
                <span class="font-display text-3xl font-semibold">{{
                    plan.price ?? 'Custom'
                }}</span>
                <span v-if="plan.price" class="text-sm text-muted-foreground"
                    >/ month</span
                >
            </p>
            <Button
                v-if="plan.price === null"
                as="a"
                :href="`mailto:${salesEmail}?subject=${encodeURIComponent('FlowPilot Enterprise')}`"
                variant="outline"
            >
                Talk to sales
            </Button>
            <Button
                v-else
                as-child
                :variant="highlighted(plan) ? 'default' : 'outline'"
            >
                <Link :href="signedIn ? dashboard() : register()">{{
                    highlighted(plan)
                        ? `Start your ${trial.days}-day trial`
                        : plan.monthly_price === 0
                          ? 'Start free'
                          : 'Get started'
                }}</Link>
            </Button>
            <ul class="grid gap-2 border-t pt-5 text-sm">
                <li
                    v-for="line in [...plan.limits, ...plan.features]"
                    :key="line.label"
                    :class="
                        cn(
                            'flex items-start gap-2',
                            !line.included && 'text-muted-foreground',
                        )
                    "
                >
                    <Check
                        v-if="line.included"
                        class="mt-0.5 size-4 shrink-0 text-success-text"
                        aria-hidden="true"
                    />
                    <Minus
                        v-else
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        >{{ line.label
                        }}<span v-if="!line.included" class="sr-only">
                            (not included)</span
                        ></span
                    >
                </li>
            </ul>
        </li>
    </ul>
</template>
