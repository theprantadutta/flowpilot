<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import type { PaginatedResource } from '@/types/operations';
import type { Paginated } from '@/types/pagination';

/**
 * Previous / next paging with a "21–40 of 132" summary. Accepts Laravel's
 * paginator or an API resource collection. Page numbers are left out on
 * purpose: lists are filtered and searched, not paged through.
 */
const props = withDefaults(
    defineProps<{
        paginator: Paginated<unknown> | PaginatedResource<unknown>;
        noun?: string;
    }>(),
    { noun: 'results' },
);

const page = computed(() => {
    const paginator = props.paginator;

    if ('meta' in paginator) {
        return {
            from: paginator.meta.from,
            to: paginator.meta.to,
            total: paginator.meta.total,
            lastPage: paginator.meta.last_page,
            prev: paginator.links.prev,
            next: paginator.links.next,
        };
    }

    return {
        from: paginator.from,
        to: paginator.to,
        total: paginator.total,
        lastPage: paginator.last_page,
        prev: paginator.prev_page_url,
        next: paginator.next_page_url,
    };
});

const summary = computed(() => {
    const { from, to, total } = page.value;

    if (!from || !to) {
        return `0 ${props.noun}`;
    }

    return `${formatNumber(from)}–${formatNumber(to)} of ${formatNumber(total)} ${props.noun}`;
});
</script>

<template>
    <nav
        v-if="page.lastPage > 1"
        aria-label="Pagination"
        class="flex items-center justify-between gap-4 border-t px-4 py-3 sm:px-5"
    >
        <p class="figures text-sm text-muted-foreground">{{ summary }}</p>
        <div class="flex gap-2">
            <Button
                :as="page.prev ? Link : 'button'"
                :href="page.prev ?? undefined"
                :disabled="!page.prev"
                variant="outline"
                size="sm"
                preserve-scroll
            >
                <ChevronLeft />
                Previous
            </Button>
            <Button
                :as="page.next ? Link : 'button'"
                :href="page.next ?? undefined"
                :disabled="!page.next"
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
