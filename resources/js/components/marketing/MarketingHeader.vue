<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Menu, Moon, Sun } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import { dashboard, home, login, pricing, register } from '@/routes';

/**
 * The public site's navigation: sections of the landing page, pricing, and
 * the way in. Signed-in visitors go straight to their organization.
 */
const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);

const links = computed(() => [
    { label: 'Product', href: `${home.url()}#product` },
    { label: 'How it works', href: `${home.url()}#how-it-works` },
    { label: 'Automation', href: `${home.url()}#automation` },
    { label: 'Pricing', href: pricing.url(), page: true },
    { label: 'FAQ', href: `${home.url()}#faq` },
]);

const { y } = useWindowScroll();
const scrolled = computed(() => y.value > 8);

const menuOpen = ref(false);

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header
        :class="
            cn(
                'sticky top-0 z-40 border-b transition-colors',
                scrolled
                    ? 'border-border bg-background/90 backdrop-blur-md'
                    : 'border-transparent bg-background/0',
            )
        "
    >
        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8"
        >
            <Link
                :href="home()"
                class="rounded-md text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="FlowPilot home"
            >
                <AppLogo />
            </Link>

            <nav aria-label="Main" class="hidden flex-1 lg:block">
                <ul class="flex items-center gap-1">
                    <li v-for="link in links" :key="link.label">
                        <component
                            :is="link.page ? Link : 'a'"
                            :href="link.href"
                            class="rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >{{ link.label }}</component
                        >
                    </li>
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2 lg:ml-0">
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
                <template v-if="signedIn">
                    <Button as-child class="hidden sm:inline-flex">
                        <Link :href="dashboard()"
                            >Open FlowPilot<ArrowRight
                        /></Link>
                    </Button>
                </template>
                <template v-else>
                    <Button
                        as-child
                        variant="ghost"
                        class="hidden sm:inline-flex"
                    >
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child class="hidden sm:inline-flex">
                        <Link :href="register()">Start free</Link>
                    </Button>
                </template>
                <Button
                    variant="ghost"
                    size="icon"
                    class="lg:hidden"
                    aria-label="Open menu"
                    @click="menuOpen = true"
                >
                    <Menu />
                </Button>
            </div>
        </div>

        <Sheet v-model:open="menuOpen">
            <SheetContent side="right" class="w-80 max-w-[85vw] gap-0 p-0">
                <SheetHeader class="border-b px-5 py-4">
                    <SheetTitle><AppLogo /></SheetTitle>
                    <SheetDescription class="sr-only"
                        >Site navigation</SheetDescription
                    >
                </SheetHeader>
                <nav aria-label="Main" class="flex-1 overflow-y-auto p-3">
                    <ul class="grid gap-1">
                        <li v-for="link in links" :key="link.label">
                            <component
                                :is="link.page ? Link : 'a'"
                                :href="link.href"
                                class="block rounded-md px-3 py-2.5 text-[0.9375rem] hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                @click="menuOpen = false"
                                >{{ link.label }}</component
                            >
                        </li>
                    </ul>
                </nav>
                <div class="grid gap-2 border-t p-4">
                    <Button v-if="signedIn" as-child>
                        <Link :href="dashboard()"
                            >Open FlowPilot<ArrowRight
                        /></Link>
                    </Button>
                    <template v-else>
                        <Button as-child>
                            <Link :href="register()">Start free</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link :href="login()">Log in</Link>
                        </Button>
                    </template>
                </div>
            </SheetContent>
        </Sheet>
    </header>
</template>
