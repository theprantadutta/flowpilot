<script setup lang="ts">
import { Link, router, usePoll } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    CircleDot,
    RefreshCw,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { store as requestBrief } from '@/routes/ai/brief';
import type { BriefItem, OperationsBrief } from '@/types/ai';

/**
 * The AI operations brief: what most needs the member's attention, written by
 * FlowPilot AI from records they can see. Every line links back to the
 * records it is about; links come from FlowPilot, never from the model.
 */
const props = defineProps<{ brief: OperationsBrief | null }>();

const requesting = ref(false);
const writing = computed(() => props.brief?.status === 'pending');

function write() {
    router.post(
        requestBrief().url,
        {},
        {
            only: ['brief'],
            preserveScroll: true,
            onStart: () => (requesting.value = true),
            onFinish: () => (requesting.value = false),
            onError: (errors) =>
                toast.error(errors.brief ?? 'The brief could not be started.'),
        },
    );
}

const { start, stop } = usePoll(
    3000,
    { only: ['brief'] },
    { autoStart: writing.value, keepAlive: true },
);

watch(writing, (pending) => (pending ? start() : stop()));

const severity: Record<
    BriefItem['severity'],
    { label: string; icon: typeof CircleAlert; class: string }
> = {
    high: {
        label: 'Urgent',
        icon: CircleAlert,
        class: 'bg-danger-soft text-danger-text',
    },
    medium: {
        label: 'Soon',
        icon: TriangleAlert,
        class: 'bg-warning-soft text-warning-text',
    },
    low: {
        label: 'When you can',
        icon: CircleDot,
        class: 'bg-neutral-soft text-neutral-text',
    },
};
</script>

<template>
    <section
        aria-labelledby="brief-heading"
        class="overflow-hidden rounded-xl border border-ai/25 bg-card shadow-xs"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-ai/15 bg-ai-soft/40 px-5 py-3.5"
        >
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ai-soft text-ai-text"
                >
                    <Sparkles class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2
                        id="brief-heading"
                        class="font-display text-sm font-semibold"
                    >
                        Operations brief
                    </h2>
                    <p class="truncate text-xs text-muted-foreground">
                        <template
                            v-if="
                                brief?.status === 'completed' &&
                                brief.used_fallback
                            "
                            >Written without AI: {{ brief.error }}</template
                        >
                        <template v-else-if="brief?.status === 'completed'"
                            >Written by FlowPilot AI
                            {{ timeAgo(brief.completed_at) }}</template
                        >
                        <template v-else
                            >Written by FlowPilot AI from what you can
                            see</template
                        >
                    </p>
                </div>
            </div>
            <Button
                v-if="brief && !writing"
                variant="ghost"
                size="sm"
                :disabled="requesting"
                @click="write"
            >
                <Spinner v-if="requesting" />
                <RefreshCw v-else />
                Write a new brief
            </Button>
        </div>

        <div
            v-if="writing"
            class="grid gap-3 p-5"
            aria-busy="true"
            role="status"
        >
            <p class="flex items-center gap-2 text-sm text-ai-text">
                <Spinner class="size-4" />
                Reading what is overdue, waiting and failing, then writing your
                brief. This takes up to a minute.
            </p>
            <Skeleton class="h-5 w-2/3" />
            <Skeleton class="h-4 w-full" />
            <Skeleton class="h-4 w-5/6" />
        </div>

        <div
            v-else-if="!brief"
            class="flex flex-wrap items-center justify-between gap-4 p-5"
        >
            <p class="max-w-xl text-sm text-pretty text-muted-foreground">
                FlowPilot AI reads the overdue work, waiting decisions, failed
                automation and low stock you are allowed to see, and tells you
                what to deal with first.
            </p>
            <Button :disabled="requesting" @click="write">
                <Spinner v-if="requesting" />
                <Sparkles v-else />
                Write my brief
            </Button>
        </div>

        <div
            v-else-if="brief.status === 'failed'"
            class="flex flex-wrap items-center justify-between gap-4 p-5"
        >
            <p class="text-sm text-danger-text">
                {{ brief.error ?? 'The brief could not be written.' }}
            </p>
            <Button variant="outline" :disabled="requesting" @click="write">
                <Spinner v-if="requesting" />
                <RefreshCw v-else />
                Try again
            </Button>
        </div>

        <div v-else class="grid gap-5 p-5">
            <p
                class="font-display text-lg leading-snug font-semibold text-balance"
            >
                {{ brief.headline }}
            </p>

            <ol v-if="brief.items.length" class="grid gap-4">
                <li
                    v-for="(item, index) in brief.items"
                    :key="index"
                    class="flex gap-3"
                >
                    <span
                        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted figures text-xs font-semibold"
                        >{{ index + 1 }}</span
                    >
                    <div class="grid min-w-0 gap-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ item.title }}</span>
                            <span
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                        severity[item.severity].class,
                                    )
                                "
                            >
                                <component
                                    :is="severity[item.severity].icon"
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                {{ severity[item.severity].label }}
                            </span>
                        </p>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ item.detail }}
                        </p>
                        <p
                            v-if="item.links.length"
                            class="flex flex-wrap gap-x-3 gap-y-1 text-sm"
                        >
                            <template
                                v-for="link in item.links"
                                :key="link.label"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="font-medium text-primary hover:underline"
                                    >{{ link.label }}</Link
                                >
                            </template>
                        </p>
                    </div>
                </li>
            </ol>

            <div v-if="brief.actions.length" class="grid gap-2 border-t pt-4">
                <p class="text-xs font-medium text-muted-foreground">
                    Suggested next steps
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="action in brief.actions"
                        :key="action.url + action.label"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link :href="action.url"
                            >{{ action.label }}<ArrowRight
                        /></Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
