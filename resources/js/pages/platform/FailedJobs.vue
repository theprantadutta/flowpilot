<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { CircleCheck, RotateCw, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, plural, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/platform';
import {
    destroy,
    index,
    retry as retryRoute,
} from '@/routes/platform/failed-jobs';
import type { FailedJob } from '@/types/platform';

defineProps<{
    jobs: FailedJob[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'Failed jobs', href: index() },
    ],
});

const retrying = ref<string | null>(null);

function retry(job: FailedJob) {
    router.post(
        retryRoute(job.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (retrying.value = job.id),
            onFinish: () => (retrying.value = null),
        },
    );
}

const clearing = ref<FailedJob | null>(null);
const clearOpen = ref(false);
const removing = ref(false);

function askClear(job: FailedJob) {
    clearing.value = job;
    clearOpen.value = true;
}

function clear() {
    if (!clearing.value) {
        return;
    }

    router.delete(destroy(clearing.value.id).url, {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
        onSuccess: () => (clearOpen.value = false),
    });
}
</script>

<template>
    <Head title="Failed jobs" />

    <div
        class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="Failed jobs"
            :description="
                jobs.length
                    ? `${plural(jobs.length, 'background job')} gave up after their retries. Fix the cause, then send them back to the queue.`
                    : 'Background jobs that give up after their retries are kept here to retry or clear.'
            "
        />

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <EmptyState
                v-if="jobs.length === 0"
                :icon="CircleCheck"
                title="No failed jobs"
                description="Mail, notifications, exports, AI briefs and workflow steps are all getting through."
            />
            <ul v-else class="divide-y">
                <li
                    v-for="job in jobs"
                    :key="job.id"
                    class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start sm:gap-4"
                >
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-medium">{{ job.job }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ job.connection }} · {{ job.queue }}</span
                            >
                        </p>
                        <p
                            class="mt-1 font-mono text-xs break-words text-danger-text"
                        >
                            {{ job.error || 'No error message was recorded.' }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Failed
                            <time
                                :datetime="job.failed_at ?? undefined"
                                :title="formatDateTime(job.failed_at)"
                                >{{ timeAgo(job.failed_at) }}</time
                            >
                            · <span class="figures">{{ job.id }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="retrying !== null"
                            @click="retry(job)"
                        >
                            <Spinner v-if="retrying === job.id" />
                            <RotateCw v-else />
                            Retry job
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground"
                            @click="askClear(job)"
                        >
                            <Trash2 />
                            Clear
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <ConfirmDialog
            v-model:open="clearOpen"
            title="Clear this failed job?"
            :description="`${clearing?.job ?? 'The job'} will not run again and its error is discarded.`"
            confirm-label="Clear job"
            destructive
            :processing="removing"
            @confirm="clear"
        />
    </div>
</template>
