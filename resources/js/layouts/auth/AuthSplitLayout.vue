<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import RunShowcase from '@/components/auth/RunShowcase.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)]">
        <div class="flex flex-col px-6 py-6 sm:px-10 lg:px-14">
            <Link :href="home()" class="self-start rounded-md text-foreground">
                <AppLogo />
            </Link>

            <main class="flex flex-1 items-center py-10">
                <div class="mx-auto w-full max-w-[23.5rem]">
                    <div class="mb-8 space-y-2">
                        <h1
                            v-if="title"
                            class="text-[1.625rem] leading-tight font-semibold text-balance"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-[0.9375rem] text-pretty text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </main>

            <p class="text-xs text-muted-foreground">
                Your work data stays inside your organization.
            </p>
        </div>

        <!-- Brand panel: always dark, whatever the theme. -->
        <aside
            class="dark relative hidden overflow-hidden bg-[#0b1220] text-foreground lg:flex lg:flex-col lg:justify-between lg:p-14"
        >
            <div
                aria-hidden="true"
                class="absolute inset-0 bg-dot-grid opacity-70"
            />
            <div
                aria-hidden="true"
                class="absolute -top-40 -right-32 size-[34rem] rounded-full bg-[radial-gradient(closest-side,rgb(37_99_235/0.32),transparent)]"
            />
            <div
                aria-hidden="true"
                class="absolute -bottom-48 -left-24 size-[30rem] rounded-full bg-[radial-gradient(closest-side,rgb(6_182_212/0.16),transparent)]"
            />

            <div class="relative max-w-md">
                <p
                    class="font-display text-[2.125rem] leading-[1.12] font-semibold text-balance"
                >
                    Move work forward. Automatically.
                </p>
                <p
                    class="mt-4 text-[0.9375rem] leading-relaxed text-pretty text-muted-foreground"
                >
                    FlowPilot turns repetitive business processes into clear,
                    connected workflows, so every request knows where it goes
                    next and who it is waiting on.
                </p>
            </div>

            <div class="relative mt-12 flex justify-start">
                <RunShowcase />
            </div>
        </aside>
    </div>
</template>
