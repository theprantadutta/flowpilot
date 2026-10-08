<script setup lang="ts">
import { Check } from '@lucide/vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

/**
 * One option in a single-choice group, rendered as a card around a native
 * radio input so keyboard and screen-reader behaviour come for free.
 */
defineProps<{
    name: string;
    value: string;
    label: string;
    description?: string;
    icon?: Component;
}>();

const model = defineModel<string>();
</script>

<template>
    <label
        :class="
            cn(
                'group relative flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 text-left shadow-xs transition-[border-color,background-color,box-shadow]',
                'hover:border-border-strong',
                'has-[:checked]:border-primary has-[:checked]:shadow-[0_0_0_1px_var(--primary)]',
                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
            )
        "
    >
        <input
            v-model="model"
            type="radio"
            :name="name"
            :value="value"
            class="sr-only"
        />
        <span
            v-if="icon"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground transition-colors group-has-[:checked]:bg-primary group-has-[:checked]:text-primary-foreground"
        >
            <component :is="icon" class="size-[1.125rem]" aria-hidden="true" />
        </span>
        <span class="grid min-w-0 gap-0.5 pr-6">
            <span class="text-sm font-medium text-foreground">{{ label }}</span>
            <span
                v-if="description"
                class="text-xs leading-relaxed text-muted-foreground"
                >{{ description }}</span
            >
        </span>
        <span
            aria-hidden="true"
            class="absolute top-3.5 right-3.5 flex size-5 items-center justify-center rounded-full border border-border-strong text-transparent transition-colors group-has-[:checked]:border-primary group-has-[:checked]:bg-primary group-has-[:checked]:text-primary-foreground"
        >
            <Check class="size-3" :stroke-width="3" />
        </span>
    </label>
</template>
