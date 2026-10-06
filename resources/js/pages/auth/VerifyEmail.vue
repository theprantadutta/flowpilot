<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Check your inbox',
        description:
            'We sent a verification link to your email address. Open it to finish setting up your account.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-5 rounded-md bg-success-soft px-3 py-2 text-sm font-medium text-success-text"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form v-bind="send.form()" class="space-y-5" v-slot="{ processing }">
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink :href="logout()" as="button" class="block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
