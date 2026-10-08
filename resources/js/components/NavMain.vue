<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup
        v-for="group in groups"
        :key="group.title"
        class="px-2 py-1.5"
    >
        <SidebarGroupLabel
            class="text-[0.6875rem] font-medium text-muted-foreground/80"
        >
            {{ group.title }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="
                        item.matchPrefix
                            ? isCurrentOrParentUrl(item.href) ||
                              (item.alsoMatches ?? []).some((href) =>
                                  isCurrentOrParentUrl(href),
                              )
                            : isCurrentUrl(item.href)
                    "
                    :tooltip="item.title"
                >
                    <Link :href="item.href" prefetch>
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge
                    v-if="item.badge"
                    class="rounded-full bg-warning-soft figures text-warning-text"
                    :aria-label="`${item.badge} waiting`"
                >
                    {{ item.badge }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
