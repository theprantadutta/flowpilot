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
import { formatDateTime } from '@/lib/format';
import { show, update } from '@/routes/organization-settings';
import type { Option } from '@/types';

const props = defineProps<{
    settings: { timezone: string; currency: string; date_format: string };
    options: {
        currencies: Option[];
        dateFormats: Option[];
        timezones: string[];
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: show() },
        { title: 'Regional', href: show({ section: 'regional' }) },
    ],
});

const form = useForm({ ...props.settings });

const nowInTimezone = computed(() => {
    try {
        return formatDateTime(new Date().toISOString(), form.timezone);
    } catch {
        return '';
    }
});

function save() {
    form.submit(update({ section: 'regional' }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <Head title="Regional settings" />

    <SettingsPanel
        title="Time, currency and dates"
        description="Due dates, reminders and reports follow the organization's timezone. Money is shown in its currency."
        :processing="form.processing"
        :dirty="form.isDirty"
        :saved="form.recentlySuccessful"
        @submit="save"
    >
        <FormField
            v-slot="field"
            label="Timezone"
            :help="
                nowInTimezone ? `It is ${nowInTimezone} there now.` : undefined
            "
            :error="form.errors.timezone"
        >
            <select
                v-bind="field"
                v-model="form.timezone"
                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            >
                <option
                    v-for="timezone in options.timezones"
                    :key="timezone"
                    :value="timezone"
                >
                    {{ timezone.replaceAll('_', ' ') }}
                </option>
            </select>
        </FormField>

        <div class="grid gap-6 sm:grid-cols-2">
            <FormField
                v-slot="field"
                label="Currency"
                help="Changing it does not convert amounts already recorded."
                :error="form.errors.currency"
            >
                <Select v-model="form.currency">
                    <SelectTrigger v-bind="field" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in options.currencies"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </FormField>

            <FormField
                v-slot="field"
                label="Date format"
                :error="form.errors.date_format"
            >
                <Select v-model="form.date_format">
                    <SelectTrigger v-bind="field" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in options.dateFormats"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
        </div>
    </SettingsPanel>
</template>
