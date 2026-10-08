<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Boxes,
    Briefcase,
    Building2,
    ClipboardCheck,
    Cpu,
    Factory,
    FolderKanban,
    GraduationCap,
    HardHat,
    HeartPulse,
    Shapes,
    ShoppingCart,
    Sparkles,
    Store,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ChoiceCard from '@/components/onboarding/ChoiceCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import FocusLayout from '@/layouts/FocusLayout.vue';
import { cn } from '@/lib/utils';
import { store } from '@/routes/onboarding';
import type { Option } from '@/types';

const props = defineProps<{
    isFirstOrganization: boolean;
    industries: Option[];
    companySizes: Option[];
    useCases: Option[];
    currencies: Option[];
}>();

type StepId = 'welcome' | 'name' | 'industry' | 'size' | 'use_case' | 'review';

const steps: { id: StepId; label: string }[] = [
    { id: 'welcome', label: 'Welcome' },
    { id: 'name', label: 'Organization' },
    { id: 'industry', label: 'Industry' },
    { id: 'size', label: 'Team size' },
    { id: 'use_case', label: 'First goal' },
    { id: 'review', label: 'Review' },
];

const industryIcons: Record<string, Component> = {
    manufacturing: Factory,
    construction: HardHat,
    technology: Cpu,
    retail: Store,
    professional_services: Briefcase,
    healthcare: HeartPulse,
    education: GraduationCap,
    other: Shapes,
};

const sizeIcons: Record<string, Component> = {
    '1': UserRound,
    '2-10': UsersRound,
    '11-50': UsersRound,
    '51-200': Building2,
    '201+': Building2,
};

const useCaseIcons: Record<string, Component> = {
    approvals: ClipboardCheck,
    purchasing: ShoppingCart,
    projects: FolderKanban,
    inventory: Boxes,
    people_operations: UsersRound,
    other: Sparkles,
};

const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const form = useForm({
    name: '',
    industry: '',
    company_size: '',
    primary_use_case: '',
    timezone: browserTimezone || 'UTC',
    currency: 'USD',
});

const stepIndex = ref(0);
const step = computed(() => steps[stepIndex.value]);
const heading = ref<HTMLElement | null>(null);

const canContinue = computed(() => {
    switch (step.value.id) {
        case 'name':
            return form.name.trim().length >= 2;
        case 'industry':
            return form.industry !== '';
        case 'size':
            return form.company_size !== '';
        case 'use_case':
            return form.primary_use_case !== '';
        default:
            return true;
    }
});

function labelFor(options: Option[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? '';
}

function next() {
    if (!canContinue.value) {
        return;
    }

    if (step.value.id === 'review') {
        submit();

        return;
    }

    stepIndex.value = Math.min(stepIndex.value + 1, steps.length - 1);
}

function back() {
    stepIndex.value = Math.max(stepIndex.value - 1, 0);
}

function goTo(id: StepId) {
    stepIndex.value = steps.findIndex((item) => item.id === id);
}

function submit() {
    form.submit(store(), {
        onError: (errors) => {
            // Send the person back to the first step with a problem.
            const order: [keyof typeof errors, StepId][] = [
                ['name', 'name'],
                ['industry', 'industry'],
                ['company_size', 'size'],
                ['primary_use_case', 'use_case'],
            ];
            const first = order.find(([field]) => errors[field]);

            if (first) {
                goTo(first[1]);
            }
        },
    });
}

// Move focus to the new step's heading so screen readers announce it.
watch(stepIndex, async () => {
    await nextTick();
    heading.value?.focus();
});
</script>

<template>
    <Head title="Set up your organization" />

    <FocusLayout>
        <template #header>
            <p class="figures text-sm text-muted-foreground" aria-live="polite">
                Step {{ stepIndex + 1 }} of {{ steps.length }}
            </p>
        </template>

        <div
            class="mx-auto flex w-full max-w-5xl flex-1 gap-12 px-5 pt-4 pb-12 sm:px-8 lg:pt-10"
        >
            <!-- Progress rail -->
            <nav aria-label="Setup steps" class="hidden w-48 shrink-0 lg:block">
                <ol class="relative space-y-1">
                    <li
                        v-for="(item, index) in steps"
                        :key="item.id"
                        class="relative"
                    >
                        <span
                            v-if="index < steps.length - 1"
                            aria-hidden="true"
                            :class="
                                cn(
                                    'absolute top-7 left-[0.6875rem] h-5 w-px',
                                    index < stepIndex
                                        ? 'bg-primary'
                                        : 'bg-border',
                                )
                            "
                        />
                        <button
                            type="button"
                            :disabled="index > stepIndex"
                            :aria-current="
                                index === stepIndex ? 'step' : undefined
                            "
                            class="flex w-full items-center gap-3 rounded-md py-1.5 text-left text-sm disabled:cursor-default"
                            @click="stepIndex = index"
                        >
                            <span
                                :class="
                                    cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full figures text-xs font-semibold transition-colors',
                                        index < stepIndex &&
                                            'bg-primary text-primary-foreground',
                                        index === stepIndex &&
                                            'bg-primary/12 text-primary ring-1 ring-primary',
                                        index > stepIndex &&
                                            'bg-secondary text-muted-foreground',
                                    )
                                "
                            >
                                {{ index + 1 }}
                            </span>
                            <span
                                :class="
                                    index === stepIndex
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ item.label }}
                            </span>
                        </button>
                    </li>
                </ol>
            </nav>

            <!-- Current step -->
            <form class="flex min-w-0 flex-1 flex-col" @submit.prevent="next">
                <div
                    class="mb-8 h-1 overflow-hidden rounded-full bg-secondary lg:hidden"
                    aria-hidden="true"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-[width] duration-300"
                        :style="{
                            width: `${((stepIndex + 1) / steps.length) * 100}%`,
                        }"
                    />
                </div>

                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-1.5 opacity-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-to-class="opacity-0"
                >
                    <section :key="step.id" class="flex flex-col gap-8">
                        <!-- Welcome -->
                        <template v-if="step.id === 'welcome'">
                            <div class="max-w-xl space-y-4">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-[2rem] leading-[1.15] font-semibold text-balance outline-none sm:text-[2.5rem]"
                                >
                                    {{
                                        isFirstOrganization
                                            ? 'Let’s set up your workspace.'
                                            : 'Add another organization.'
                                    }}
                                </h1>
                                <p
                                    class="text-base leading-relaxed text-pretty text-muted-foreground"
                                >
                                    A few questions so FlowPilot starts out
                                    shaped around how your team works. It takes
                                    about a minute, and you can change any of it
                                    later in settings.
                                </p>
                            </div>
                            <ul
                                class="grid max-w-xl gap-3 text-sm sm:grid-cols-3"
                            >
                                <li
                                    class="rounded-xl border bg-card p-4 shadow-xs"
                                >
                                    <ClipboardCheck
                                        class="mb-3 size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <p class="font-medium">Route requests</p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Approvals go to the right people, in
                                        order.
                                    </p>
                                </li>
                                <li
                                    class="rounded-xl border bg-card p-4 shadow-xs"
                                >
                                    <FolderKanban
                                        class="mb-3 size-5 text-flow"
                                        aria-hidden="true"
                                    />
                                    <p class="font-medium">Track the work</p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Projects, tasks and issues in one place.
                                    </p>
                                </li>
                                <li
                                    class="rounded-xl border bg-card p-4 shadow-xs"
                                >
                                    <Sparkles
                                        class="mb-3 size-5 text-ai"
                                        aria-hidden="true"
                                    />
                                    <p class="font-medium">See what’s stuck</p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        A daily brief of what needs attention.
                                    </p>
                                </li>
                            </ul>
                        </template>

                        <!-- Name -->
                        <template v-else-if="step.id === 'name'">
                            <div class="max-w-xl space-y-2">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-2xl font-semibold outline-none sm:text-3xl"
                                >
                                    What is your organization called?
                                </h1>
                                <p class="text-muted-foreground">
                                    This is the name your team sees when they
                                    sign in.
                                </p>
                            </div>
                            <div class="grid max-w-md gap-2">
                                <Label for="organization-name"
                                    >Organization name</Label
                                >
                                <Input
                                    id="organization-name"
                                    v-model="form.name"
                                    v-focus
                                    autocomplete="organization"
                                    placeholder="Northstar Manufacturing"
                                    maxlength="120"
                                    class="h-11 text-base"
                                    :aria-invalid="!!form.errors.name"
                                    aria-describedby="organization-name-error"
                                />
                                <InputError
                                    id="organization-name-error"
                                    :message="form.errors.name"
                                />
                            </div>
                        </template>

                        <!-- Industry -->
                        <template v-else-if="step.id === 'industry'">
                            <div class="max-w-xl space-y-2">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-2xl font-semibold outline-none sm:text-3xl"
                                >
                                    Which industry are you in?
                                </h1>
                                <p class="text-muted-foreground">
                                    We use this to suggest workflows that fit.
                                </p>
                            </div>
                            <fieldset class="grid gap-3 sm:grid-cols-2">
                                <legend class="sr-only">Industry</legend>
                                <ChoiceCard
                                    v-for="option in industries"
                                    :key="option.value"
                                    v-model="form.industry"
                                    name="industry"
                                    :value="option.value"
                                    :label="option.label"
                                    :icon="industryIcons[option.value]"
                                />
                            </fieldset>
                            <InputError :message="form.errors.industry" />
                        </template>

                        <!-- Size -->
                        <template v-else-if="step.id === 'size'">
                            <div class="max-w-xl space-y-2">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-2xl font-semibold outline-none sm:text-3xl"
                                >
                                    How many people will use FlowPilot?
                                </h1>
                                <p class="text-muted-foreground">
                                    A rough number is fine.
                                </p>
                            </div>
                            <fieldset
                                class="grid max-w-2xl gap-3 sm:grid-cols-2"
                            >
                                <legend class="sr-only">Company size</legend>
                                <ChoiceCard
                                    v-for="option in companySizes"
                                    :key="option.value"
                                    v-model="form.company_size"
                                    name="company_size"
                                    :value="option.value"
                                    :label="option.label"
                                    :icon="sizeIcons[option.value]"
                                />
                            </fieldset>
                            <InputError :message="form.errors.company_size" />
                        </template>

                        <!-- Use case -->
                        <template v-else-if="step.id === 'use_case'">
                            <div class="max-w-xl space-y-2">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-2xl font-semibold outline-none sm:text-3xl"
                                >
                                    What do you want to sort out first?
                                </h1>
                                <p class="text-muted-foreground">
                                    Pick the process that costs your team the
                                    most time today.
                                </p>
                            </div>
                            <fieldset class="grid gap-3 sm:grid-cols-2">
                                <legend class="sr-only">
                                    Primary use case
                                </legend>
                                <ChoiceCard
                                    v-for="option in useCases"
                                    :key="option.value"
                                    v-model="form.primary_use_case"
                                    name="primary_use_case"
                                    :value="option.value"
                                    :label="option.label"
                                    :description="option.description"
                                    :icon="useCaseIcons[option.value]"
                                />
                            </fieldset>
                            <InputError
                                :message="form.errors.primary_use_case"
                            />
                        </template>

                        <!-- Review -->
                        <template v-else>
                            <div class="max-w-xl space-y-2">
                                <h1
                                    ref="heading"
                                    tabindex="-1"
                                    class="text-2xl font-semibold outline-none sm:text-3xl"
                                >
                                    Ready to create {{ form.name }}?
                                </h1>
                                <p class="text-muted-foreground">
                                    Check the details. You will be the owner,
                                    and you can invite your team next.
                                </p>
                            </div>

                            <dl
                                class="max-w-xl divide-y rounded-xl border bg-card shadow-xs"
                            >
                                <div
                                    v-for="row in [
                                        {
                                            label: 'Organization',
                                            value: form.name,
                                            step: 'name' as StepId,
                                        },
                                        {
                                            label: 'Industry',
                                            value: labelFor(
                                                industries,
                                                form.industry,
                                            ),
                                            step: 'industry' as StepId,
                                        },
                                        {
                                            label: 'Team size',
                                            value: labelFor(
                                                companySizes,
                                                form.company_size,
                                            ),
                                            step: 'size' as StepId,
                                        },
                                        {
                                            label: 'First goal',
                                            value: labelFor(
                                                useCases,
                                                form.primary_use_case,
                                            ),
                                            step: 'use_case' as StepId,
                                        },
                                    ]"
                                    :key="row.label"
                                    class="flex items-center justify-between gap-4 px-4 py-3"
                                >
                                    <dt class="text-sm text-muted-foreground">
                                        {{ row.label }}
                                    </dt>
                                    <dd
                                        class="flex min-w-0 items-center gap-3 text-sm font-medium"
                                    >
                                        <span class="truncate">{{
                                            row.value
                                        }}</span>
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            class="h-auto px-0"
                                            @click="goTo(row.step)"
                                        >
                                            Change<span class="sr-only">
                                                {{
                                                    row.label.toLowerCase()
                                                }}</span
                                            >
                                        </Button>
                                    </dd>
                                </div>
                            </dl>

                            <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="organization-timezone"
                                        >Timezone</Label
                                    >
                                    <Input
                                        id="organization-timezone"
                                        v-model="form.timezone"
                                        autocomplete="off"
                                        aria-describedby="organization-timezone-help"
                                    />
                                    <p
                                        id="organization-timezone-help"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Detected from your browser. Due dates
                                        and reports use it.
                                    </p>
                                    <InputError
                                        :message="form.errors.timezone"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="organization-currency"
                                        >Currency</Label
                                    >
                                    <Select v-model="form.currency">
                                        <SelectTrigger
                                            id="organization-currency"
                                            class="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="currency in currencies"
                                                :key="currency.value"
                                                :value="currency.value"
                                            >
                                                {{ currency.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground">
                                        Used for purchase requests and budgets.
                                    </p>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>
                        </template>
                    </section>
                </Transition>

                <div class="mt-10 flex items-center gap-3">
                    <Button
                        v-if="stepIndex > 0"
                        type="button"
                        variant="ghost"
                        :disabled="form.processing"
                        @click="back"
                    >
                        <ArrowLeft />
                        Back
                    </Button>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="!canContinue || form.processing"
                        class="min-w-36"
                    >
                        <Spinner v-if="form.processing" />
                        <template v-if="step.id === 'welcome'"
                            >Get started</template
                        >
                        <template v-else-if="step.id === 'review'"
                            >Create organization</template
                        >
                        <template v-else>Continue</template>
                        <ArrowRight
                            v-if="step.id !== 'review' && !form.processing"
                        />
                    </Button>
                </div>
            </form>
        </div>
    </FocusLayout>
</template>
