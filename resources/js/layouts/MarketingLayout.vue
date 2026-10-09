<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingFooter from '@/components/marketing/MarketingFooter.vue';
import MarketingHeader from '@/components/marketing/MarketingHeader.vue';

/**
 * The public site: header, the page, footer. Pages that know the sales
 * address pass it as a prop, and the footer offers it.
 */
const page = usePage<{ salesEmail?: string }>();
const salesEmail = computed(() => page.props.salesEmail);
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <a
            href="#main"
            class="sr-only z-50 rounded-md bg-primary px-3 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Skip to content</a
        >
        <MarketingHeader />
        <main id="main" class="flex-1">
            <slot />
        </main>
        <MarketingFooter :sales-email="salesEmail" />
    </div>
</template>
