<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home, login, pricing, register } from '@/routes';
import { privacy, terms } from '@/routes/legal';

defineProps<{ salesEmail?: string }>();

const year = new Date().getFullYear();

const product = [
    { label: 'Overview', href: `${home.url()}#product` },
    { label: 'How it works', href: `${home.url()}#how-it-works` },
    { label: 'Workflow automation', href: `${home.url()}#automation` },
    { label: 'AI operations brief', href: `${home.url()}#ai` },
    { label: 'Security', href: `${home.url()}#security` },
];
</script>

<template>
    <footer class="border-t bg-card">
        <div
            class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))] lg:px-8"
        >
            <div class="grid content-start gap-3">
                <Link :href="home()" class="self-start text-foreground">
                    <AppLogo />
                </Link>
                <p class="max-w-xs text-sm text-pretty text-muted-foreground">
                    Workflow automation for operations teams. Move work forward,
                    automatically.
                </p>
            </div>

            <nav aria-labelledby="footer-product">
                <h2
                    id="footer-product"
                    class="mb-3 text-sm font-semibold text-foreground"
                >
                    Product
                </h2>
                <ul class="grid gap-2 text-sm text-muted-foreground">
                    <li v-for="link in product" :key="link.label">
                        <a :href="link.href" class="hover:text-foreground">{{
                            link.label
                        }}</a>
                    </li>
                    <li>
                        <Link :href="pricing()" class="hover:text-foreground"
                            >Pricing</Link
                        >
                    </li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-account">
                <h2
                    id="footer-account"
                    class="mb-3 text-sm font-semibold text-foreground"
                >
                    Get started
                </h2>
                <ul class="grid gap-2 text-sm text-muted-foreground">
                    <li>
                        <Link :href="register()" class="hover:text-foreground"
                            >Create an account</Link
                        >
                    </li>
                    <li>
                        <Link :href="login()" class="hover:text-foreground"
                            >Log in</Link
                        >
                    </li>
                    <li v-if="salesEmail">
                        <a
                            :href="`mailto:${salesEmail}`"
                            class="hover:text-foreground"
                            >Talk to sales</a
                        >
                    </li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-legal">
                <h2
                    id="footer-legal"
                    class="mb-3 text-sm font-semibold text-foreground"
                >
                    Legal
                </h2>
                <ul class="grid gap-2 text-sm text-muted-foreground">
                    <li>
                        <Link :href="terms()" class="hover:text-foreground"
                            >Terms of service</Link
                        >
                    </li>
                    <li>
                        <Link :href="privacy()" class="hover:text-foreground"
                            >Privacy policy</Link
                        >
                    </li>
                </ul>
            </nav>
        </div>
        <div class="border-t">
            <p
                class="mx-auto w-full max-w-7xl px-4 py-5 text-xs text-muted-foreground sm:px-6 lg:px-8"
            >
                &copy; {{ year }} FlowPilot. All rights reserved.
            </p>
        </div>
    </footer>
</template>
