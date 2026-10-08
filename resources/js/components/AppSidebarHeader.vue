<script setup lang="ts">
import { Moon, Search, Sun } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import {
    shortcutLabel,
    useCommandPalette,
} from '@/composables/useCommandPalette';
import { useOrganization } from '@/composables/useOrganization';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { open: openPalette } = useCommandPalette();
const { organization } = useOrganization();
const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear sm:px-6 md:px-4"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <button
                type="button"
                class="hidden h-9 w-64 items-center gap-2 rounded-lg border bg-card px-3 text-sm text-muted-foreground shadow-xs transition-colors hover:border-border-strong hover:text-foreground md:flex"
                @click="openPalette"
            >
                <Search class="size-4" aria-hidden="true" />
                <span class="flex-1 text-left">Search or jump to…</span>
                <kbd
                    class="rounded border bg-muted px-1.5 py-px font-sans text-[0.6875rem] font-medium"
                    >{{ shortcutLabel() }}</kbd
                >
            </button>
            <Button
                variant="ghost"
                size="icon"
                class="md:hidden"
                aria-label="Search"
                @click="openPalette"
            >
                <Search />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :aria-label="
                    resolvedAppearance === 'dark'
                        ? 'Switch to light mode'
                        : 'Switch to dark mode'
                "
                @click="toggleTheme"
            >
                <Sun v-if="resolvedAppearance === 'dark'" />
                <Moon v-else />
            </Button>
            <NotificationBell v-if="organization" />
        </div>
    </header>
</template>
