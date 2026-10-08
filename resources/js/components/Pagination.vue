<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import type { Paginated } from '@/types/pagination';

/**
 * Previous / next paging with a "21–40 of 132" summary. Page numbers are left
 * out on purpose: lists are filtered and searched, not paged through.
 */
const props = withDefaults(
    defineProps<{
        paginator: Paginated<unknown>;
        noun?: string;
    }>(),
    { noun: 'results' },
);

const summary = computed(() => {
    const { from, to, total } = props.paginator;

    if (!from || !to) {
        return `0 ${props.noun}`;
    }

    return `${formatNumber(from)}–${formatNumber(to)} of ${formatNumber(total)} ${props.noun}`;
});
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        aria-label="Pagination"
        class="flex items-center justify-between gap-4 border-t px-4 py-3 sm:px-5"
    >
        <p class="figures text-sm text-muted-foreground">{{ summary }}</p>
        <div class="flex gap-2">
            <Button
                :as="paginator.prev_page_url ? Link : 'button'"
                :href="paginator.prev_page_url ?? undefined"
                :disabled="!paginator.prev_page_url"
                variant="outline"
                size="sm"
                preserve-scroll
            >
                <ChevronLeft />
                Previous
            </Button>
            <Button
                :as="paginator.next_page_url ? Link : 'button'"
                :href="paginator.next_page_url ?? undefined"
                :disabled="!paginator.next_page_url"
                variant="outline"
                size="sm"
                preserve-scroll
            >
                Next
                <ChevronRight />
            </Button>
        </div>
    </nav>
</template>
