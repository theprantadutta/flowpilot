<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
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
import { edit as securityEdit } from '@/routes/security';

const props = defineProps<{
    settings: {
        require_two_factor: boolean;
        idle_timeout_minutes: number;
        actor_has_two_factor: boolean;
    };
    options: { idleTimeouts: number[] };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: show() },
        { title: 'Security', href: show({ section: 'security' }) },
    ],
});

const form = useForm({
    require_two_factor: props.settings.require_two_factor,
    idle_timeout_minutes: String(props.settings.idle_timeout_minutes),
});

function timeoutLabel(minutes: number): string {
    if (minutes === 0) {
        return 'Never (use the standard session)';
    }

    return minutes < 60
        ? `After ${minutes} minutes`
        : `After ${minutes / 60} ${minutes === 60 ? 'hour' : 'hours'}`;
}

function save() {
    form.transform((data) => ({
        ...data,
        idle_timeout_minutes: Number(data.idle_timeout_minutes),
    })).submit(update({ section: 'security' }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <Head title="Security settings" />

    <SettingsPanel
        title="Sign-in security"
        description="Rules that apply to everyone in the organization, including you."
        :processing="form.processing"
        :dirty="form.isDirty"
        :saved="form.recentlySuccessful"
        @submit="save"
    >
        <div class="grid gap-2">
            <label
                class="flex cursor-pointer items-start justify-between gap-6"
            >
                <span class="grid gap-1">
                    <span class="text-sm font-medium"
                        >Require two-factor authentication</span
                    >
                    <span class="text-sm text-muted-foreground">
                        Members without it are asked to turn it on before they
                        can open this organization.
                    </span>
                </span>
                <Switch
                    v-model="form.require_two_factor"
                    :disabled="
                        !settings.actor_has_two_factor &&
                        !form.require_two_factor
                    "
                    aria-label="Require two-factor authentication"
                />
            </label>
            <p
                v-if="!settings.actor_has_two_factor"
                class="flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2.5 text-sm text-warning-text"
            >
                <ShieldAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>
                    Turn on two-factor authentication for your own account
                    first, so you are not locked out.
                    <Link
                        :href="securityEdit()"
                        class="font-medium underline underline-offset-2"
                        >Set it up</Link
                    >
                </span>
            </p>
            <InputError :message="form.errors.require_two_factor" />
        </div>

        <FormField
            v-slot="field"
            label="Sign members out when idle"
            help="Members are signed out of this organization after this long without activity."
            :error="form.errors.idle_timeout_minutes"
        >
            <Select v-model="form.idle_timeout_minutes">
                <SelectTrigger v-bind="field" class="w-full sm:w-80">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="minutes in options.idleTimeouts"
                        :key="minutes"
                        :value="String(minutes)"
                    >
                        {{ timeoutLabel(minutes) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </FormField>
    </SettingsPanel>
</template>
