<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus } from '@lucide/vue';
import { computed } from 'vue';
import OrganizationAvatar from '@/components/OrganizationAvatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { overview } from '@/routes';
import { show as onboarding } from '@/routes/onboarding';

const page = usePage();
const { isMobile } = useSidebar();

const current = computed(() => page.props.organization);
const organizations = computed(() => page.props.organizations ?? []);
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent"
                        :aria-label="
                            current
                                ? `Current organization: ${current.name}. Switch organization`
                                : 'Choose an organization'
                        "
                    >
                        <OrganizationAvatar
                            v-if="current"
                            :name="current.name"
                            :logo="current.logo"
                        />
                        <div
                            class="grid min-w-0 flex-1 text-left leading-tight"
                        >
                            <span class="truncate text-sm font-semibold">
                                {{ current?.name ?? 'Your organizations' }}
                            </span>
                            <span
                                v-if="current"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ current.role_label }}
                            </span>
                        </div>
                        <ChevronsUpDown
                            class="ml-auto size-4 text-muted-foreground"
                        />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                    :side="isMobile ? 'bottom' : 'right'"
                    align="start"
                    :side-offset="6"
                >
                    <DropdownMenuLabel
                        class="text-xs font-normal text-muted-foreground"
                    >
                        Switch organization
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="organization in organizations"
                        :key="organization.id"
                        as-child
                    >
                        <Link
                            :href="overview(organization.slug)"
                            class="flex w-full cursor-pointer items-center gap-2.5"
                        >
                            <OrganizationAvatar
                                :name="organization.name"
                                :logo="organization.logo"
                                class="size-7"
                            />
                            <span class="grid min-w-0 flex-1 leading-tight">
                                <span class="truncate text-sm">{{
                                    organization.name
                                }}</span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                    >{{ organization.role_label }}</span
                                >
                            </span>
                            <Check
                                v-if="organization.id === current?.id"
                                class="size-4 text-primary"
                                aria-label="Current"
                            />
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link
                            :href="onboarding()"
                            class="flex w-full cursor-pointer items-center gap-2.5"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-md border border-dashed"
                            >
                                <Plus class="size-4" />
                            </span>
                            Create organization
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
