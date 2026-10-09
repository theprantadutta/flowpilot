<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { formatDate } from '@/lib/format';
import type { LegalDocument } from '@/types/marketing';

/**
 * A legal page. The HTML is rendered on the server from our own Markdown,
 * with any embedded HTML stripped, so it is safe to insert.
 */
defineProps<{
    document: LegalDocument;
    contactEmail: string;
}>();
</script>

<template>
    <Head :title="document.title" />

    <div
        class="mx-auto grid w-full max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:px-8 lg:py-20"
    >
        <header class="grid content-start gap-3 lg:col-span-2">
            <h1
                class="font-display text-4xl leading-tight font-semibold text-balance"
            >
                {{ document.title }}
            </h1>
            <p class="text-muted-foreground">
                Last updated
                <time :datetime="document.updated">{{
                    formatDate(`${document.updated}T00:00:00Z`, 'UTC', {
                        dateStyle: 'long',
                    })
                }}</time>
                · Questions:
                <a
                    :href="`mailto:${contactEmail}`"
                    class="font-medium text-primary hover:underline"
                    >{{ contactEmail }}</a
                >
            </p>
        </header>

        <nav
            v-if="document.sections.length"
            aria-label="On this page"
            class="hidden lg:block"
        >
            <div class="sticky top-24 grid gap-2 text-sm">
                <p class="font-semibold">On this page</p>
                <ul class="grid gap-1.5 text-muted-foreground">
                    <li v-for="section in document.sections" :key="section.id">
                        <a
                            :href="`#${section.id}`"
                            class="hover:text-foreground"
                            >{{ section.title }}</a
                        >
                    </li>
                </ul>
            </div>
        </nav>

        <article
            class="max-w-3xl text-[0.9375rem] [&_a]:font-medium [&_a]:text-primary [&_a:hover]:underline [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:scroll-mt-24 [&_h2]:font-display [&_h2]:text-[1.375rem] [&_h2]:font-semibold [&_h2]:text-foreground [&_h2:first-child]:mt-0 [&_p]:mb-4 [&_p]:leading-7 [&_p]:text-muted-foreground [&_strong]:font-semibold [&_strong]:text-foreground [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:text-muted-foreground"
            v-html="document.html"
        />
    </div>
</template>
