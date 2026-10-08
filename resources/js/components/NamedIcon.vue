<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    Bell,
    Boxes,
    Building2,
    CheckSquare,
    CircleCheck,
    CircleX,
    FolderKanban,
    GitBranch,
    Package,
    Settings,
    Sparkles,
    Stamp,
    User,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Renders an icon named by the server (activity entries, search results,
 * notifications), so PHP never needs to know about the icon library.
 */
const props = defineProps<{ name: string }>();

const icons: Record<string, Component> = {
    activity: Activity,
    'alert-triangle': AlertTriangle,
    bell: Bell,
    boxes: Boxes,
    building: Building2,
    'check-square': CheckSquare,
    'circle-check': CircleCheck,
    'circle-x': CircleX,
    folder: FolderKanban,
    'git-branch': GitBranch,
    package: Package,
    settings: Settings,
    sparkles: Sparkles,
    stamp: Stamp,
    user: User,
};

const icon = computed(() => icons[props.name] ?? Activity);
</script>

<template>
    <component :is="icon" aria-hidden="true" />
</template>
