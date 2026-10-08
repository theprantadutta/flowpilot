<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    Archive,
    Bell,
    Boxes,
    Building2,
    CheckSquare,
    Circle,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleDot,
    CircleSlash,
    CircleX,
    Eye,
    FolderKanban,
    GitBranch,
    Info,
    OctagonAlert,
    Package,
    Paperclip,
    Pause,
    PencilRuler,
    Play,
    Search,
    Settings,
    SignalHigh,
    SignalLow,
    SignalMedium,
    Siren,
    Sparkles,
    Stamp,
    TriangleAlert,
    User,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Renders an icon named by the server (statuses, activity entries, search
 * results), so PHP never needs to know about the icon library.
 */
const props = defineProps<{ name: string }>();

const icons: Record<string, Component> = {
    activity: Activity,
    'alert-triangle': AlertTriangle,
    archive: Archive,
    bell: Bell,
    boxes: Boxes,
    building: Building2,
    'check-square': CheckSquare,
    circle: Circle,
    'circle-alert': CircleAlert,
    'circle-check': CircleCheck,
    'circle-dashed': CircleDashed,
    'circle-dot': CircleDot,
    'circle-slash': CircleSlash,
    'circle-x': CircleX,
    eye: Eye,
    folder: FolderKanban,
    'git-branch': GitBranch,
    info: Info,
    'octagon-alert': OctagonAlert,
    package: Package,
    paperclip: Paperclip,
    pause: Pause,
    'pencil-ruler': PencilRuler,
    play: Play,
    search: Search,
    settings: Settings,
    'signal-high': SignalHigh,
    'signal-low': SignalLow,
    'signal-medium': SignalMedium,
    siren: Siren,
    sparkles: Sparkles,
    stamp: Stamp,
    'triangle-alert': TriangleAlert,
    user: User,
};

const icon = computed(() => icons[props.name] ?? Activity);
</script>

<template>
    <component :is="icon" aria-hidden="true" />
</template>
