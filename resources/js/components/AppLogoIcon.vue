<script setup lang="ts">
import { useId } from 'vue';

/**
 * The FlowPilot mark: an F whose top arm becomes a forward arrow, with the
 * amber node standing for the step that is waiting on someone.
 */
withDefaults(
    defineProps<{
        /** Render in a single colour (inherits `currentColor`). */
        mono?: boolean;
    }>(),
    { mono: false },
);

const gradientId = `fp-mark-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        aria-hidden="true"
    >
        <defs v-if="!mono">
            <linearGradient
                :id="gradientId"
                x1="9"
                y1="43"
                x2="40"
                y2="8"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#2563EB" />
                <stop offset="1" stop-color="#06B6D4" />
            </linearGradient>
        </defs>
        <g
            :stroke="mono ? 'currentColor' : `url(#${gradientId})`"
            :fill="mono ? 'currentColor' : `url(#${gradientId})`"
        >
            <path
                d="M11.5 40.5V21.5a10 10 0 0 1 10-10h7.5"
                fill="none"
                stroke-width="7"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <path
                d="M28.5 5.6 39.2 11.5 28.5 17.4Z"
                stroke-width="1.6"
                stroke-linejoin="round"
            />
            <path
                d="M11.5 27.5h8.5"
                fill="none"
                stroke-width="7"
                stroke-linecap="round"
            />
        </g>
        <circle
            cx="32.2"
            cy="27.5"
            r="4.4"
            :fill="mono ? 'currentColor' : '#F59E0B'"
        />
    </svg>
</template>
