<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard } from '@/routes';

/**
 * A distraction-free page for single-purpose flows (onboarding, accepting an
 * invitation): the logo, the task, and nothing else.
 */
withDefaults(
    defineProps<{
        /** Where the logo links to. */
        homeHref?: string;
    }>(),
    { homeHref: undefined },
);
</script>

<template>
    <div class="relative flex min-h-dvh flex-col bg-background">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(60%_100%_at_50%_0%,color-mix(in_srgb,var(--primary)_9%,transparent),transparent)]"
        />
        <header
            class="relative flex items-center justify-between px-5 py-5 sm:px-8"
        >
            <Link
                :href="homeHref ?? dashboard()"
                class="rounded-md text-foreground"
                aria-label="FlowPilot home"
            >
                <AppLogo />
            </Link>
            <slot name="header" />
        </header>
        <main class="relative flex flex-1 flex-col">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
