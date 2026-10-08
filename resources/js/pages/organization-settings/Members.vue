<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { show, update } from '@/routes/organization-settings';
import type { Option } from '@/types';

const props = defineProps<{
    settings: { default_role: string; allow_member_invites: boolean };
    options: { roles: Option[] };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: show() },
        { title: 'Members', href: show({ section: 'members' }) },
    ],
});

const form = useForm({ ...props.settings });

const roleDescription = computed(
    () =>
        props.options.roles.find((role) => role.value === form.default_role)
            ?.description,
);

function save() {
    form.submit(update({ section: 'members' }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <Head title="Member settings" />

    <SettingsPanel
        title="Inviting people"
        description="How new people join the organization."
        :processing="form.processing"
        :dirty="form.isDirty"
        :saved="form.recentlySuccessful"
        @submit="save"
    >
        <FormField
            v-slot="field"
            label="Default role for invitations"
            :help="roleDescription"
            :error="form.errors.default_role"
        >
            <Select v-model="form.default_role">
                <SelectTrigger v-bind="field" class="w-full sm:w-80">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="role in options.roles"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </FormField>

        <label class="flex cursor-pointer items-start justify-between gap-6">
            <span class="grid gap-1">
                <span class="text-sm font-medium"
                    >Let every member invite colleagues</span
                >
                <span class="text-sm text-muted-foreground">
                    Members who are not admins can invite people as Employee.
                    Admins can change their role later.
                </span>
            </span>
            <Switch
                v-model="form.allow_member_invites"
                aria-label="Let every member invite colleagues"
            />
        </label>
    </SettingsPanel>
</template>
