<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import NotificationEmailToggles from '@/components/NotificationEmailToggles.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/notification-preferences';

const props = defineProps<{
    types: {
        value: string;
        label: string;
        group: string;
        email_by_default: boolean;
    }[];
    email: Record<string, boolean>;
    customised: string[];
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Notification settings', href: edit() }],
});

const form = useForm({ email: { ...props.email } });

function save() {
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <Head title="Notification settings" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Email notifications"
            description="Choose what we email you about. Everything still appears in the notification center of each organization."
        />

        <form class="space-y-6" @submit.prevent="save">
            <NotificationEmailToggles
                v-model="form.email"
                :types="types"
                described-as="Email me"
            />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                >
                    <Spinner v-if="form.processing" />
                    Save notification settings
                </Button>
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm text-success-text"
                    aria-live="polite"
                >
                    Saved
                </p>
            </div>
        </form>
    </div>
</template>
