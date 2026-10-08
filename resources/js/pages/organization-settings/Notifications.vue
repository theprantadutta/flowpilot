<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import NotificationEmailToggles from '@/components/NotificationEmailToggles.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { show, update } from '@/routes/organization-settings';

const props = defineProps<{
    settings: { email: Record<string, boolean> };
    options: {
        types: {
            value: string;
            label: string;
            group: string;
            email_by_default: boolean;
        }[];
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: show() },
        { title: 'Notifications', href: show({ section: 'notifications' }) },
    ],
});

const form = useForm({ email: { ...props.settings.email } });

function save() {
    form.submit(update({ section: 'notifications' }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <Head title="Notification settings" />

    <SettingsPanel
        title="Email defaults"
        description="Which notifications are also emailed to members. Everything always appears in the notification center, and each member can change these for themselves."
        :processing="form.processing"
        :dirty="form.isDirty"
        :saved="form.recentlySuccessful"
        @submit="save"
    >
        <NotificationEmailToggles
            v-model="form.email"
            :types="options.types"
            described-as="Email members by default"
        />
    </SettingsPanel>
</template>
