<script setup lang="ts">
import { Plus } from '@lucide/vue';
import type { FaqItem } from '@/types/marketing';

/**
 * Questions and answers as native disclosure widgets, so they work with the
 * keyboard, screen readers and find-in-page without any script.
 */
defineProps<{ items: FaqItem[] }>();
</script>

<template>
    <div class="divide-y rounded-2xl border bg-card">
        <details
            v-for="item in items"
            :key="item.question"
            class="group px-5 sm:px-6"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 font-medium focus-visible:outline-none [&::-webkit-details-marker]:hidden"
            >
                <span
                    class="rounded-sm group-has-[summary:focus-visible]:ring-2 group-has-[summary:focus-visible]:ring-ring"
                    >{{ item.question }}</span
                >
                <Plus
                    class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-45"
                    aria-hidden="true"
                />
            </summary>
            <p
                class="-mt-1 max-w-3xl pb-5 text-[0.9375rem] text-pretty text-muted-foreground"
            >
                {{ item.answer }}
            </p>
        </details>
    </div>
</template>
