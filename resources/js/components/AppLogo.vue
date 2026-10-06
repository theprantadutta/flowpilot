<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        mono?: boolean;
        /** Hide the wordmark and keep only the mark (collapsed sidebar, tight spaces). */
        compact?: boolean;
    }>(),
    { mono: false, compact: false },
);
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <AppLogoIcon :mono="mono" class="size-7 shrink-0" />
        <span
            v-if="!compact"
            class="font-display text-[1.0625rem] leading-none font-semibold tracking-tight"
        >
            FlowPilot
        </span>
        <span v-else class="sr-only">FlowPilot</span>
    </span>
</template>
