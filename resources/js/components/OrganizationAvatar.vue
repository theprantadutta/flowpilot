<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * An organization's logo, or its initials on a tinted square when it has none.
 * The tint is derived from the name so each organization keeps its colour.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        logo?: string | null;
        class?: string;
    }>(),
    { logo: null },
);

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase())
        .join(''),
);

const tints = [
    'bg-[#dbe7ff] text-[#1d4ed8] dark:bg-[#172a52] dark:text-[#93c5fd]',
    'bg-[#d6f5fa] text-[#0e7490] dark:bg-[#0b2a35] dark:text-[#67e8f9]',
    'bg-[#e9e1fd] text-[#6d28d9] dark:bg-[#221a3f] dark:text-[#c4b5fd]',
    'bg-[#fdeccc] text-[#a8510a] dark:bg-[#2c2210] dark:text-[#fcd34d]',
    'bg-[#d5f3e6] text-[#047857] dark:bg-[#0d2a26] dark:text-[#6ee7b7]',
];

const tint = computed(() => {
    let hash = 0;

    for (const character of props.name) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
    }

    return tints[hash % tints.length];
});
</script>

<template>
    <img
        v-if="logo"
        :src="logo"
        alt=""
        :class="cn('size-8 shrink-0 rounded-md object-cover', props.class)"
    />
    <span
        v-else
        aria-hidden="true"
        :class="
            cn(
                'flex size-8 shrink-0 items-center justify-center rounded-md font-display text-xs font-semibold',
                tint,
                props.class,
            )
        "
    >
        {{ initials }}
    </span>
</template>
