<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, Building2, Globe2, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useOrganization } from '@/composables/useOrganization';
import { cn, toUrl } from '@/lib/utils';
import { show } from '@/routes/organization-settings';

const { organization } = useOrganization();
const { isCurrentUrl } = useCurrentUrl();

const sections = computed(() => [
    { title: 'General', href: show({ section: 'general' }), icon: Building2 },
    { title: 'Regional', href: show({ section: 'regional' }), icon: Globe2 },
    {
        title: 'Notifications',
        href: show({ section: 'notifications' }),
        icon: Bell,
    },
    {
        title: 'Security',
        href: show({ section: 'security' }),
        icon: ShieldCheck,
    },
    { title: 'Members', href: show({ section: 'members' }), icon: Users },
]);

function isActive(href: ReturnType<typeof show>, index: number): boolean {
    // /settings and /settings/general are the same page.
    return isCurrentUrl(href) || (index === 0 && isCurrentUrl(show()));
}
</script>

<template>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:py-8">
        <header class="mb-6 space-y-1">
            <h1 class="text-2xl font-semibold">Organization settings</h1>
            <p class="text-sm text-muted-foreground">
                How {{ organization?.name }} works for everyone in it. Only
                owners and admins can change these.
            </p>
        </header>

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            <nav
                aria-label="Organization settings"
                class="-mx-1 flex scrollbar-thin gap-1 overflow-x-auto pb-1 lg:mx-0 lg:w-52 lg:shrink-0 lg:flex-col lg:overflow-visible"
            >
                <Link
                    v-for="(section, index) in sections"
                    :key="toUrl(section.href)"
                    :href="section.href"
                    :aria-current="
                        isActive(section.href, index) ? 'page' : undefined
                    "
                    :class="
                        cn(
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors',
                            isActive(section.href, index)
                                ? 'bg-secondary font-medium text-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )
                    "
                >
                    <component
                        :is="section.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ section.title }}
                </Link>
            </nav>

            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </div>
</template>
