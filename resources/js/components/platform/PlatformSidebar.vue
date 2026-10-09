<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CircleGauge,
    HeartPulse,
    History,
    ServerCrash,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard as appHome } from '@/routes';
import { audit, dashboard, health } from '@/routes/platform';
import { index as failedJobs } from '@/routes/platform/failed-jobs';
import { index as organizations } from '@/routes/platform/organizations';
import { index as users } from '@/routes/platform/users';
import type { NavGroup } from '@/types';

/**
 * Navigation for platform administration. Kept apart from any organization's
 * sidebar so it is always clear which side of FlowPilot you are on.
 */
const groups: NavGroup[] = [
    {
        title: 'Customers',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: CircleGauge },
            {
                title: 'Organizations',
                href: organizations(),
                icon: Building2,
                matchPrefix: true,
            },
            { title: 'People', href: users(), icon: Users },
        ],
    },
    {
        title: 'System',
        items: [
            { title: 'Health', href: health(), icon: HeartPulse },
            { title: 'Failed jobs', href: failedJobs(), icon: ServerCrash },
            { title: 'Audit log', href: audit(), icon: History },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="gap-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-transparent active:bg-transparent"
                    >
                        <Link :href="dashboard()">
                            <AppLogo class="text-foreground" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <p
                class="mx-2 rounded-md border border-primary/25 bg-primary/8 px-2.5 py-1.5 text-xs font-medium text-primary group-data-[collapsible=icon]:hidden"
            >
                Platform administration
            </p>
        </SidebarHeader>

        <SidebarSeparator class="mx-0" />

        <SidebarContent class="scrollbar-thin">
            <NavMain :groups="groups" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Back to FlowPilot">
                        <Link :href="appHome()">
                            <ArrowLeft />
                            <span>Back to FlowPilot</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
