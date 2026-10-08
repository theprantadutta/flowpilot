<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

/**
 * What a list shows when there is nothing in it: what the area is for and the
 * one action that fills it. Never just "No data".
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        /** Compact variant for panels and cards. */
        compact?: boolean;
        class?: string;
    }>(),
    { compact: false },
);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center justify-center text-center',
                props.compact ? 'gap-2 px-4 py-8' : 'gap-3 px-6 py-14',
                props.class,
            )
        "
    >
        <div
            v-if="icon"
            class="flex items-center justify-center rounded-xl border bg-card text-muted-foreground shadow-xs"
            :class="compact ? 'size-10' : 'size-12'"
        >
            <component
                :is="icon"
                :class="compact ? 'size-5' : 'size-6'"
                aria-hidden="true"
            />
        </div>
        <div class="max-w-sm space-y-1">
            <p class="font-display text-base font-semibold text-foreground">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap justify-center gap-2"
        >
            <slot />
        </div>
    </div>
</template>
