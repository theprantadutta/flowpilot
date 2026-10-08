<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleAlert, MailOpen } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import FocusLayout from '@/layouts/FocusLayout.vue';
import { formatDate } from '@/lib/format';
import { home, login, logout, register } from '@/routes';
import { accept } from '@/routes/invitations';

const props = defineProps<{
    token: string;
    invitation: {
        organization: string;
        email: string;
        role_label: string;
        role_description: string;
        invited_by: string | null;
        expires_at: string;
        state: 'open' | 'accepted' | 'revoked' | 'expired';
    };
    viewer: { email: string; matches: boolean } | null;
}>();

const unavailable = computed(() => {
    switch (props.invitation.state) {
        case 'accepted':
            return {
                title: 'This invitation has already been used',
                body: 'If you accepted it, sign in to open the organization.',
            };
        case 'revoked':
            return {
                title: 'This invitation was withdrawn',
                body: `Ask ${props.invitation.invited_by ?? 'an admin'} at ${props.invitation.organization} to invite you again.`,
            };
        case 'expired':
            return {
                title: 'This invitation has expired',
                body: `Invitations last 7 days. Ask ${props.invitation.invited_by ?? 'an admin'} to send a new one.`,
            };
        default:
            return null;
    }
});
</script>

<template>
    <Head :title="`Join ${invitation.organization}`" />

    <FocusLayout :home-href="home().url">
        <div
            class="flex flex-1 items-start justify-center px-5 pt-10 pb-16 sm:pt-20"
        >
            <div
                class="w-full max-w-md rounded-2xl border bg-card p-7 shadow-md sm:p-8"
            >
                <!-- Not usable any more -->
                <template v-if="unavailable">
                    <div
                        class="mb-5 flex size-11 items-center justify-center rounded-xl bg-warning-soft text-warning-text"
                    >
                        <CircleAlert class="size-5" aria-hidden="true" />
                    </div>
                    <h1 class="text-xl font-semibold">
                        {{ unavailable.title }}
                    </h1>
                    <p class="mt-2 text-sm text-pretty text-muted-foreground">
                        {{ unavailable.body }}
                    </p>
                    <Button as-child variant="outline" class="mt-6">
                        <Link :href="login()">Sign in</Link>
                    </Button>
                </template>

                <template v-else>
                    <div
                        class="mb-5 flex size-11 items-center justify-center rounded-xl bg-info-soft text-info-text"
                    >
                        <MailOpen class="size-5" aria-hidden="true" />
                    </div>
                    <h1 class="text-xl leading-snug font-semibold text-balance">
                        {{ invitation.invited_by ?? 'Someone' }} invited you to
                        join
                        {{ invitation.organization }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        You would join as
                        <span class="font-medium text-foreground">{{
                            invitation.role_label
                        }}</span
                        >.
                        {{ invitation.role_description }}
                    </p>
                    <p class="mt-4 text-xs text-muted-foreground">
                        Sent to {{ invitation.email }} · valid until
                        {{ formatDate(invitation.expires_at) }}
                    </p>

                    <!-- Signed in with the invited address -->
                    <Form
                        v-if="viewer?.matches"
                        v-bind="accept.form(token)"
                        class="mt-7"
                        v-slot="{ errors, processing }"
                    >
                        <Button
                            type="submit"
                            class="w-full"
                            size="lg"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            Join {{ invitation.organization }}
                        </Button>
                        <InputError :message="errors.invitation" class="mt-3" />
                    </Form>

                    <!-- Signed in as someone else -->
                    <div v-else-if="viewer" class="mt-7 space-y-4">
                        <p
                            class="rounded-lg bg-warning-soft px-3.5 py-3 text-sm text-warning-text"
                        >
                            You are signed in as {{ viewer.email }}. This
                            invitation is for {{ invitation.email }}.
                        </p>
                        <Button as-child variant="outline" class="w-full">
                            <Link :href="logout()" as="button">
                                Sign out and switch account
                            </Link>
                        </Button>
                    </div>

                    <!-- Signed out -->
                    <div v-else class="mt-7 grid gap-3">
                        <Button as-child size="lg">
                            <Link :href="register()"
                                >Create an account to join</Link
                            >
                        </Button>
                        <Button as-child variant="outline" size="lg">
                            <Link :href="login()"
                                >I already have an account</Link
                            >
                        </Button>
                        <p class="text-center text-xs text-muted-foreground">
                            Use {{ invitation.email }} so we can match your
                            invitation.
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </FocusLayout>
</template>
