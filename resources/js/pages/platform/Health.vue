<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck, CircleX, RefreshCw, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { dashboard, health } from '@/routes/platform';
import type { HealthCheck, HealthStatus } from '@/types/platform';

const props = defineProps<{
    checks?: HealthCheck[];
    versions: { label: string; value: string }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'Health', href: health() },
    ],
});

const looks: Record<
    HealthStatus,
    { icon: typeof CircleCheck; label: string; text: string; soft: string }
> = {
    ok: {
        icon: CircleCheck,
        label: 'Working',
        text: 'text-success-text',
        soft: 'bg-success-soft',
    },
    warning: {
        icon: TriangleAlert,
        label: 'Needs a look',
        text: 'text-warning-text',
        soft: 'bg-warning-soft',
    },
    failing: {
        icon: CircleX,
        label: 'Failing',
        text: 'text-danger-text',
        soft: 'bg-danger-soft',
    },
};

const overall = computed<HealthStatus | null>(() => {
    if (!props.checks) {
        return null;
    }

    if (props.checks.some((check) => check.status === 'failing')) {
        return 'failing';
    }

    return props.checks.some((check) => check.status === 'warning')
        ? 'warning'
        : 'ok';
});

const headline = computed(() => {
    const checks = props.checks ?? [];
    const failing = checks.filter((check) => check.status === 'failing').length;
    const warnings = checks.filter(
        (check) => check.status === 'warning',
    ).length;

    if (failing > 0) {
        return `${failing} of ${checks.length} checks failing`;
    }

    if (warnings > 0) {
        return `${warnings} of ${checks.length} checks need a look`;
    }

    return 'Everything FlowPilot depends on is working';
});

const refreshing = ref(false);

function refresh() {
    router.reload({
        only: ['checks'],
        onStart: () => (refreshing.value = true),
        onFinish: () => (refreshing.value = false),
    });
}
</script>

<template>
    <Head title="Health" />

    <div
        class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Health"
            description="Live checks of the database, cache, queues, scheduler, storage, mail and AI provider."
        >
            <template #actions>
                <Button
                    variant="outline"
                    :disabled="refreshing || !checks"
                    @click="refresh"
                >
                    <Spinner v-if="refreshing" />
                    <RefreshCw v-else />
                    Check again
                </Button>
            </template>
        </PageHeader>

        <div v-if="!checks || !overall" class="grid gap-3" aria-busy="true">
            <Skeleton class="h-16 rounded-xl" />
            <div class="grid gap-3 sm:grid-cols-2">
                <Skeleton v-for="n in 6" :key="n" class="h-28 rounded-xl" />
            </div>
        </div>

        <template v-else>
            <div
                role="status"
                :class="
                    cn(
                        'flex items-center gap-3 rounded-xl border px-5 py-4',
                        looks[overall].soft,
                        looks[overall].text,
                    )
                "
            >
                <component
                    :is="looks[overall].icon"
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />
                <p class="font-display font-semibold">{{ headline }}</p>
            </div>

            <ul
                :class="
                    cn(
                        'grid gap-3 transition-opacity sm:grid-cols-2',
                        refreshing && 'opacity-60',
                    )
                "
            >
                <li
                    v-for="check in checks"
                    :key="check.key"
                    class="flex gap-3 rounded-xl border bg-card p-4 shadow-xs"
                >
                    <span
                        :class="
                            cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                looks[check.status].soft,
                                looks[check.status].text,
                            )
                        "
                    >
                        <component
                            :is="looks[check.status].icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-3"
                        >
                            <h2 class="font-medium">{{ check.label }}</h2>
                            <span
                                :class="
                                    cn(
                                        'text-xs font-medium',
                                        looks[check.status].text,
                                    )
                                "
                                >{{ looks[check.status].label }}</span
                            >
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ check.summary }}
                        </p>
                        <p
                            v-if="check.detail"
                            class="mt-1.5 text-xs text-pretty text-muted-foreground"
                        >
                            {{ check.detail }}
                        </p>
                    </div>
                </li>
            </ul>
        </template>

        <section
            aria-labelledby="versions-heading"
            class="rounded-xl border bg-card shadow-xs"
        >
            <h2
                id="versions-heading"
                class="border-b px-5 py-3.5 font-display text-sm font-semibold"
            >
                Running versions
            </h2>
            <dl class="grid gap-x-6 gap-y-3 px-5 py-4 sm:grid-cols-4">
                <div v-for="version in versions" :key="version.label">
                    <dt class="text-xs text-muted-foreground">
                        {{ version.label }}
                    </dt>
                    <dd class="figures text-sm font-medium">
                        {{ version.value }}
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
