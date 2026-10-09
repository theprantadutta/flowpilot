<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BellRing,
    Boxes,
    ChartColumn,
    CircleAlert,
    FileCheck2,
    FingerprintPattern,
    FolderKanban,
    GitBranch,
    History,
    KeyRound,
    Layers,
    LockKeyhole,
    PenLine,
    Play,
    RefreshCcw,
    ShieldCheck,
    Sparkles,
    Stamp,
    UsersRound,
    Webhook,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import BriefPreview from '@/components/marketing/BriefPreview.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import PricingPlans from '@/components/marketing/PricingPlans.vue';
import ProductWindow from '@/components/marketing/ProductWindow.vue';
import SectionHeading from '@/components/marketing/SectionHeading.vue';
import WorkflowPreview from '@/components/marketing/WorkflowPreview.vue';
import { Button } from '@/components/ui/button';
import { dashboard, pricing, register } from '@/routes';
import type { FaqItem, PlanCard, Trial } from '@/types/marketing';

defineProps<{
    plans: PlanCard[];
    faqs: FaqItem[];
    trial: Trial;
    salesEmail: string;
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);

const areas = [
    'Projects',
    'Tasks',
    'Issues',
    'Approvals',
    'Inventory',
    'Purchasing',
    'Reports',
];

const triggers = [
    'Task is created',
    'Task is completed',
    'Issue is reported',
    'Stock runs low',
    'Purchase request is submitted',
    'Started by a person',
];

const steps = [
    {
        icon: Zap,
        title: 'Pick what starts it',
        body: 'A workflow listens for something that happens in your organization, or waits for a person to start it with the details it asks for.',
        chips: triggers,
    },
    {
        icon: PenLine,
        title: 'Draw the steps',
        body: 'Conditions and branches decide the path. Approvals, assigned work, record updates, notifications, delays and webhooks do the work along it.',
        chips: [
            'Condition',
            'Branch',
            'Approval',
            'Assign',
            'Notification',
            'Delay',
            'Webhook',
        ],
    },
    {
        icon: Play,
        title: 'Publish and watch it run',
        body: 'Publish a version when it is ready. Every run is recorded step by step, so anyone can see where a request is and who it is waiting on.',
        chips: ['Versions', 'Run history', 'Retries'],
    },
];

const automation = [
    {
        icon: Layers,
        title: 'No code, by design',
        body: 'Steps come from a fixed, validated set. Nothing in a workflow runs code someone typed, so every workflow behaves predictably.',
    },
    {
        icon: Stamp,
        title: 'Approvals inside the flow',
        body: 'Ask a person or a role, set a due time, and choose whether a late decision sends reminders or is rejected automatically.',
    },
    {
        icon: GitBranch,
        title: 'Safe to change',
        body: 'Edit a draft while the published version keeps running. Runs already under way finish on the version they started with.',
    },
    {
        icon: RefreshCcw,
        title: 'Recovers on its own',
        body: 'Failed steps are retried, delays wake up on time, and runs a stopped worker left behind are picked up again.',
    },
];

const features = [
    {
        icon: FolderKanban,
        title: 'Projects and tasks',
        body: 'Boards, timelines, checklists and dependencies, with owners and due dates everyone can see.',
    },
    {
        icon: CircleAlert,
        title: 'Issues',
        body: 'Report problems with a severity and an owner, and let workflows escalate the serious ones.',
    },
    {
        icon: Stamp,
        title: 'Approvals',
        body: 'Every decision in one inbox, with comments, requested changes and a full history.',
    },
    {
        icon: Boxes,
        title: 'Inventory and purchasing',
        body: 'Stock per location, every movement recorded, suppliers, low-stock alerts and purchase requests.',
    },
    {
        icon: ChartColumn,
        title: 'Reports and exports',
        body: 'Charts with a table view of the same numbers, filters that stay in the link, and CSV exports.',
    },
    {
        icon: BellRing,
        title: 'Notifications',
        body: 'In the app and by email, with each person choosing what reaches their inbox.',
    },
    {
        icon: UsersRound,
        title: 'Roles and permissions',
        body: 'Owner, Admin, Manager, Finance, Procurement, Operations, Employee and Auditor, each with the access its job needs.',
    },
    {
        icon: History,
        title: 'Activity and audit log',
        body: 'Who changed what and when, with the changes themselves and where the request came from.',
    },
];

const useCases = [
    {
        team: 'Finance',
        title: 'Spend over a limit gets a second look',
        flow: [
            'Purchase request is submitted',
            'Over $5,000?',
            'Finance approves',
            'Tell procurement',
        ],
    },
    {
        team: 'Operations',
        title: 'Low stock turns into a reorder',
        flow: [
            'Stock runs low',
            'Create a purchase request',
            'Procurement approves',
            'Task to place the order',
        ],
    },
    {
        team: 'Maintenance',
        title: 'Critical issues never wait for a meeting',
        flow: [
            'Issue is reported',
            'Severity is critical?',
            'Assign the shift manager',
            'Notify the team',
        ],
    },
    {
        team: 'People',
        title: 'A new starter is ready on day one',
        flow: [
            'Started by a person',
            'Task for IT: prepare a laptop',
            'Task for payroll',
            'Tell the manager it is ready',
        ],
    },
];

const ai = [
    {
        icon: FileCheck2,
        title: 'Grounded in your records',
        body: 'Every point links to the tasks, issues, approvals or items behind it. Links come from FlowPilot, not from the model.',
    },
    {
        icon: LockKeyhole,
        title: 'Sees only what you can',
        body: 'The brief is written from records the person asking is allowed to open, and nothing else.',
    },
    {
        icon: Activity,
        title: 'Still useful when AI is not',
        body: 'If the AI provider cannot answer, you get a plain brief from the same facts instead of an error.',
    },
];

const security = [
    {
        icon: Layers,
        title: 'Organizations kept apart',
        body: 'Every record belongs to one organization, and every request is checked against the organization in its address and your membership of it.',
    },
    {
        icon: ShieldCheck,
        title: 'Permissions on the server',
        body: 'Roles are sets of permissions, enforced on every request. Hiding a button is never the only protection.',
    },
    {
        icon: FingerprintPattern,
        title: 'Two-factor and passkeys',
        body: 'Sign in with an authenticator app or a passkey, and require two-factor for everyone in your organization.',
    },
    {
        icon: KeyRound,
        title: 'Confirm before it matters',
        body: 'Sensitive actions ask for your password again, and sign-in attempts are rate limited.',
    },
    {
        icon: History,
        title: 'A complete audit trail',
        body: 'Changes are recorded with who made them, when, what changed and the address they came from.',
    },
    {
        icon: Webhook,
        title: 'Signed webhooks',
        body: 'Outgoing webhooks are signed so the receiving system can check they came from you, and private addresses are refused.',
    },
];
</script>

<template>
    <Head title="Workflow automation for operations teams" />

    <!-- Hero -->
    <section class="relative overflow-hidden" aria-labelledby="hero-heading">
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-dot-grid [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)]"
        />
        <div
            aria-hidden="true"
            class="absolute -top-56 left-1/2 size-[46rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,color-mix(in_srgb,var(--primary)_18%,transparent),transparent)]"
        />
        <div
            class="relative mx-auto grid w-full max-w-7xl justify-items-center gap-8 px-4 pt-16 pb-12 text-center sm:px-6 sm:pt-24 lg:px-8"
        >
            <p
                class="inline-flex items-center gap-2 rounded-full border bg-card px-3 py-1 text-sm text-muted-foreground shadow-xs"
            >
                <span
                    class="size-1.5 rounded-full bg-flow"
                    aria-hidden="true"
                />
                Workflow automation for operations teams
            </p>
            <h1
                id="hero-heading"
                class="max-w-4xl font-display text-5xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl"
            >
                Move work forward.
                <span
                    class="bg-gradient-to-r from-primary to-flow bg-clip-text text-transparent"
                    >Automatically.</span
                >
            </h1>
            <p
                class="max-w-2xl text-lg text-pretty text-muted-foreground sm:text-xl"
            >
                FlowPilot turns repetitive business processes into clear,
                connected workflows. Requests reach the right people, decisions
                happen on time, and every step is on record.
            </p>
            <div class="flex flex-wrap justify-center gap-3">
                <Button v-if="signedIn" as-child size="lg">
                    <Link :href="dashboard()"
                        >Open FlowPilot<ArrowRight
                    /></Link>
                </Button>
                <Button v-else as-child size="lg">
                    <Link :href="register()">Start free</Link>
                </Button>
                <Button as-child size="lg" variant="outline">
                    <a href="#how-it-works">See how it works</a>
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ trial.days }} days of {{ trial.plan }} free. No card needed.
            </p>
        </div>

        <div
            id="product"
            class="relative mx-auto w-full max-w-6xl scroll-mt-24 px-4 pb-16 sm:px-6 lg:px-8"
        >
            <ProductWindow />
        </div>
    </section>

    <!-- What it connects -->
    <section
        aria-label="What FlowPilot brings together"
        class="border-y bg-card"
    >
        <ul
            class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-4 py-6 text-sm font-medium text-muted-foreground sm:px-6 lg:px-8"
        >
            <li class="text-foreground">One place for</li>
            <li
                v-for="area in areas"
                :key="area"
                class="flex items-center gap-2"
            >
                <span
                    class="size-1 rounded-full bg-border-strong"
                    aria-hidden="true"
                />
                {{ area }}
            </li>
        </ul>
    </section>

    <!-- How it works -->
    <section
        id="how-it-works"
        aria-labelledby="how-heading"
        class="mx-auto w-full max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 lg:px-8 lg:py-28"
    >
        <SectionHeading
            id="how-heading"
            eyebrow="How it works"
            title="From a process on paper to one that runs itself"
            description="Draw the process once. FlowPilot follows it every time, and shows you exactly where each request stands."
        />
        <ol class="mt-14 grid gap-6 lg:grid-cols-3">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="relative flex flex-col gap-4 rounded-2xl border bg-card p-6 shadow-xs"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <component
                            :is="step.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="figures text-sm font-medium text-muted-foreground"
                        >Step {{ index + 1 }}</span
                    >
                </div>
                <h3 class="font-display text-xl font-semibold">
                    {{ step.title }}
                </h3>
                <p class="text-pretty text-muted-foreground">{{ step.body }}</p>
                <ul class="mt-auto flex flex-wrap gap-1.5 pt-2">
                    <li
                        v-for="chip in step.chips"
                        :key="chip"
                        class="rounded-md border bg-background px-2 py-1 text-xs text-muted-foreground"
                    >
                        {{ chip }}
                    </li>
                </ul>
            </li>
        </ol>
    </section>

    <!-- Workflow automation -->
    <section
        id="automation"
        aria-labelledby="automation-heading"
        class="scroll-mt-20 border-y bg-card"
    >
        <div
            class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)] lg:px-8 lg:py-28"
        >
            <div class="grid gap-8">
                <SectionHeading
                    id="automation-heading"
                    eyebrow="Workflow automation"
                    title="Every request takes the right path"
                    description="Conditions route the work, approvals wait for the right people, and the next step starts the moment a decision is made."
                    align="left"
                />
                <ul class="grid gap-6 sm:grid-cols-2">
                    <li
                        v-for="item in automation"
                        :key="item.title"
                        class="grid gap-2"
                    >
                        <span class="flex items-center gap-2 font-medium">
                            <component
                                :is="item.icon"
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {{ item.title }}
                        </span>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ item.body }}
                        </p>
                    </li>
                </ul>
            </div>
            <WorkflowPreview />
        </div>
    </section>

    <!-- Features -->
    <section
        id="features"
        aria-labelledby="features-heading"
        class="mx-auto w-full max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 lg:px-8 lg:py-28"
    >
        <SectionHeading
            id="features-heading"
            eyebrow="Features"
            title="Everything operations runs on, already connected"
            description="Each area works on its own, and every one of them can start a workflow or be changed by one."
        />
        <ul
            class="mt-14 grid gap-px overflow-hidden rounded-2xl border bg-border sm:grid-cols-2 lg:grid-cols-4"
        >
            <li
                v-for="feature in features"
                :key="feature.title"
                class="flex flex-col gap-3 bg-card p-6"
            >
                <component
                    :is="feature.icon"
                    class="size-5 text-primary"
                    aria-hidden="true"
                />
                <h3 class="font-display font-semibold">{{ feature.title }}</h3>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{ feature.body }}
                </p>
            </li>
        </ul>
    </section>

    <!-- Use cases -->
    <section
        id="use-cases"
        aria-labelledby="use-cases-heading"
        class="scroll-mt-20 border-y bg-card"
    >
        <div
            class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28"
        >
            <SectionHeading
                id="use-cases-heading"
                eyebrow="Use cases"
                title="Built from the processes teams repeat every week"
                description="Start from a template or draw your own. These are the workflows teams set up first."
            />
            <ul class="mt-14 grid gap-6 md:grid-cols-2">
                <li
                    v-for="useCase in useCases"
                    :key="useCase.title"
                    class="flex flex-col gap-5 rounded-2xl border bg-background p-6"
                >
                    <div class="grid gap-1">
                        <p class="text-sm font-medium text-flow-text">
                            {{ useCase.team }}
                        </p>
                        <h3 class="font-display text-lg font-semibold">
                            {{ useCase.title }}
                        </h3>
                    </div>
                    <ol class="grid gap-2">
                        <li
                            v-for="(step, index) in useCase.flow"
                            :key="step"
                            class="flex items-center gap-3 text-sm"
                        >
                            <span
                                :class="
                                    index === 0
                                        ? 'bg-flow-soft text-flow-text'
                                        : 'bg-muted text-muted-foreground'
                                "
                                class="flex size-6 shrink-0 items-center justify-center rounded-full figures text-xs font-semibold"
                                >{{ index + 1 }}</span
                            >
                            <span>{{ step }}</span>
                        </li>
                    </ol>
                </li>
            </ul>
        </div>
    </section>

    <!-- AI -->
    <section
        id="ai"
        aria-labelledby="ai-heading"
        class="mx-auto grid w-full max-w-7xl scroll-mt-20 items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28"
    >
        <div class="grid gap-8 lg:order-2">
            <div class="grid max-w-3xl gap-3">
                <p
                    class="flex items-center gap-2 text-sm font-semibold text-ai-text"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                    AI operations brief
                </p>
                <h2
                    id="ai-heading"
                    class="font-display text-3xl leading-tight font-semibold text-balance sm:text-4xl"
                >
                    Know what needs you before you go looking
                </h2>
                <p
                    class="text-base text-pretty text-muted-foreground sm:text-lg"
                >
                    FlowPilot AI reads the overdue work, waiting decisions,
                    failed automation and low stock you are responsible for, and
                    tells you what to deal with first.
                </p>
            </div>
            <ul class="grid gap-5">
                <li v-for="item in ai" :key="item.title" class="flex gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-ai-soft text-ai-text"
                    >
                        <component
                            :is="item.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="grid gap-1">
                        <span class="font-medium">{{ item.title }}</span>
                        <span
                            class="text-sm text-pretty text-muted-foreground"
                            >{{ item.body }}</span
                        >
                    </span>
                </li>
            </ul>
        </div>
        <BriefPreview class="lg:order-1" />
    </section>

    <!-- Security -->
    <section
        id="security"
        aria-labelledby="security-heading"
        class="dark scroll-mt-20 border-y border-border bg-background text-foreground"
    >
        <div
            class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28"
        >
            <SectionHeading
                id="security-heading"
                eyebrow="Security"
                title="Built for the records your company runs on"
                description="Security is part of how FlowPilot is built, not a setting you have to find."
            />
            <ul class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="item in security"
                    :key="item.title"
                    class="flex flex-col gap-3 rounded-2xl border bg-card p-6"
                >
                    <component
                        :is="item.icon"
                        class="size-5 text-flow"
                        aria-hidden="true"
                    />
                    <h3 class="font-display font-semibold">{{ item.title }}</h3>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ item.body }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <!-- Pricing -->
    <section
        id="pricing"
        aria-labelledby="pricing-heading"
        class="mx-auto w-full max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 lg:px-8 lg:py-28"
    >
        <SectionHeading
            id="pricing-heading"
            eyebrow="Pricing"
            title="Plans that grow with your operations"
            :description="`One monthly price per organization, not per person. Every new organization starts with ${trial.days} days of ${trial.plan}.`"
        />
        <PricingPlans
            class="mt-14"
            :plans="plans"
            :trial="trial"
            :sales-email="salesEmail"
        />
        <p class="mt-8 text-center">
            <Link
                :href="pricing()"
                class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                >Compare every plan in detail<ArrowRight class="size-4"
            /></Link>
        </p>
    </section>

    <!-- FAQ -->
    <section
        id="faq"
        aria-labelledby="faq-heading"
        class="scroll-mt-20 border-t bg-card"
    >
        <div
            class="mx-auto grid w-full max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:px-8 lg:py-28"
        >
            <SectionHeading
                id="faq-heading"
                eyebrow="FAQ"
                title="Questions teams ask first"
                align="left"
            />
            <FaqList :items="faqs" class="bg-background" />
        </div>
    </section>

    <!-- Call to action -->
    <section aria-labelledby="cta-heading" class="px-4 py-20 sm:px-6 lg:px-8">
        <div
            class="dark relative mx-auto grid w-full max-w-6xl justify-items-center gap-6 overflow-hidden rounded-3xl bg-background px-6 py-16 text-center text-foreground sm:px-12"
        >
            <div
                aria-hidden="true"
                class="absolute inset-0 bg-dot-grid opacity-60"
            />
            <div
                aria-hidden="true"
                class="absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,color-mix(in_srgb,var(--primary)_32%,transparent),transparent)]"
            />
            <h2
                id="cta-heading"
                class="relative max-w-2xl font-display text-3xl leading-tight font-semibold text-balance sm:text-4xl"
            >
                Put your first workflow to work this week
            </h2>
            <p class="relative max-w-xl text-pretty text-muted-foreground">
                Set up your organization in a few minutes, invite your team, and
                start from a template for approvals, reorders or onboarding.
            </p>
            <div class="relative flex flex-wrap justify-center gap-3">
                <Button v-if="signedIn" as-child size="lg">
                    <Link :href="dashboard()"
                        >Open FlowPilot<ArrowRight
                    /></Link>
                </Button>
                <Button v-else as-child size="lg">
                    <Link :href="register()">Start free</Link>
                </Button>
                <Button as-child size="lg" variant="outline">
                    <a :href="`mailto:${salesEmail}`">Talk to sales</a>
                </Button>
            </div>
        </div>
    </section>
</template>
