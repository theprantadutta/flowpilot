<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Boxes,
    ChartColumn,
    CircleAlert,
    FolderKanban,
    GitBranch,
    Hourglass,
    LayoutDashboard,
    ListChecks,
    Settings,
    ShoppingCart,
    Stamp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OrganizationAvatar from '@/components/OrganizationAvatar.vue';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useOrganization } from '@/composables/useOrganization';
import { dashboard, overview } from '@/routes';
import { index as activity } from '@/routes/activity';
import { index as approvals } from '@/routes/approvals';
import { show as billingShow } from '@/routes/billing';
import { index as inventory } from '@/routes/inventory/items';
import { index as issues } from '@/routes/issues';
import { index as members } from '@/routes/members';
import { show as organizationSettings } from '@/routes/organization-settings';
import { index as projects } from '@/routes/projects';
import { index as purchaseRequests } from '@/routes/purchase-requests';
import { index as reports } from '@/routes/reports';
import { index as tasks } from '@/routes/tasks';
import { index as workflowRuns } from '@/routes/workflow-runs';
import { index as workflows } from '@/routes/workflows';
import type { NavGroup } from '@/types';

const page = usePage();
const { organization, can } = useOrganization();

/**
 * Navigation for the current organization, trimmed to what the member may
 * open. Groups with nothing left in them are dropped.
 */
const groups = computed<NavGroup[]>(() => {
    if (!organization.value) {
        return [];
    }

    const all: NavGroup[] = [
        {
            title: 'Operate',
            items: [
                {
                    title: 'Overview',
                    href: overview(),
                    icon: LayoutDashboard,
                    permission: 'dashboard.view',
                },
                {
                    title: 'Projects',
                    href: projects(),
                    icon: FolderKanban,
                    permission: 'projects.view',
                    matchPrefix: true,
                },
                {
                    title: 'Tasks',
                    href: tasks(),
                    icon: ListChecks,
                    permission: 'tasks.view',
                    matchPrefix: true,
                },
                {
                    title: 'Issues',
                    href: issues(),
                    icon: CircleAlert,
                    permission: 'issues.view',
                    matchPrefix: true,
                },
                can('inventory.view')
                    ? {
                          title: 'Inventory',
                          href: inventory(),
                          icon: Boxes,
                          permission: 'inventory.view',
                          matchPrefix: true,
                          alsoMatches: [purchaseRequests()],
                      }
                    : {
                          // Members who may ask to buy things but not see stock.
                          title: 'Purchase requests',
                          href: purchaseRequests(),
                          icon: ShoppingCart,
                          permission: 'inventory.request',
                          matchPrefix: true,
                      },
            ],
        },
        {
            title: 'Automate',
            items: [
                {
                    title: 'Workflows',
                    href: workflows(),
                    icon: GitBranch,
                    permission: 'workflows.view',
                    matchPrefix: true,
                    alsoMatches: [workflowRuns()],
                },
                {
                    title: 'Approvals',
                    href: approvals(),
                    icon: Stamp,
                    permission: 'approvals.view',
                    matchPrefix: true,
                    badge: page.props.pendingApprovals || undefined,
                },
            ],
        },
        {
            title: 'Insight',
            items: [
                {
                    title: 'Reports',
                    href: reports(),
                    icon: ChartColumn,
                    permission: 'reports.view',
                    matchPrefix: true,
                },
                {
                    title: 'Activity',
                    href: activity(),
                    icon: Activity,
                    permission: 'dashboard.view',
                },
            ],
        },
        {
            title: 'Admin',
            items: [
                {
                    title: 'Members',
                    href: members(),
                    icon: Users,
                    permission: 'members.view',
                    matchPrefix: true,
                },
                {
                    title: 'Settings',
                    href: organizationSettings(),
                    icon: Settings,
                    permission: 'settings.manage',
                    matchPrefix: true,
                },
            ],
        },
    ];

    return all
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
});

const organizations = computed(() => page.props.organizations ?? []);

/** A reminder for the people who look after the plan, while a trial runs. */
const trial = computed(() => {
    const plan = organization.value?.plan;

    if (
        !plan ||
        plan.status !== 'trialing' ||
        plan.trial_days_left === null ||
        !(can('settings.manage') || can('billing.manage'))
    ) {
        return null;
    }

    return { label: plan.label, days: plan.trial_days_left };
});
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
            <OrganizationSwitcher v-if="organizations.length > 0" />
        </SidebarHeader>

        <SidebarSeparator class="mx-0" />

        <SidebarContent class="scrollbar-thin">
            <NavMain v-if="organization" :groups="groups" />

            <!-- Outside an organization (account settings): list the ones you can open. -->
            <SidebarGroup v-else-if="organizations.length > 0" class="px-2">
                <SidebarGroupLabel>Your organizations</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in organizations"
                        :key="item.id"
                    >
                        <SidebarMenuButton as-child :tooltip="item.name">
                            <Link :href="overview(item.slug)">
                                <OrganizationAvatar
                                    :name="item.name"
                                    :logo="item.logo"
                                    class="size-5 rounded text-[0.625rem]"
                                />
                                <span>{{ item.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <Link
                v-if="trial"
                :href="billingShow()"
                class="mx-1 flex items-center gap-2 rounded-lg border border-info/30 bg-info-soft/60 px-3 py-2 text-xs text-info-text transition-colors group-data-[collapsible=icon]:hidden hover:bg-info-soft"
            >
                <Hourglass class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="min-w-0">
                    <span class="font-medium">{{ trial.label }} trial</span>:
                    {{ trial.days }}
                    {{ trial.days === 1 ? 'day' : 'days' }} left
                </span>
            </Link>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
