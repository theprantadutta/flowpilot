<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import OrganizationAvatar from '@/components/OrganizationAvatar.vue';
import SettingsPanel from '@/components/SettingsPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/organization-settings';
import {
    destroy as destroyLogo,
    update as updateLogo,
} from '@/routes/organization-settings/logo';
import type { Option } from '@/types';

const props = defineProps<{
    settings: {
        name: string;
        website: string | null;
        industry: string | null;
        company_size: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        address: string | null;
        logo: string | null;
    };
    options: { industries: Option[]; companySizes: Option[] };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Settings', href: show() },
        { title: 'General', href: show({ section: 'general' }) },
    ],
});

const form = useForm({
    name: props.settings.name,
    website: props.settings.website ?? '',
    industry: props.settings.industry ?? '',
    company_size: props.settings.company_size ?? '',
    contact_email: props.settings.contact_email ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    address: props.settings.address ?? '',
});

function save() {
    form.transform((data) => ({
        ...data,
        website: data.website || null,
        industry: data.industry || null,
        company_size: data.company_size || null,
        contact_email: data.contact_email || null,
        contact_phone: data.contact_phone || null,
        address: data.address || null,
    })).submit(update({ section: 'general' }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

const websitePlaceholder = 'https://' + 'northstar.example';

const logoInput = ref<HTMLInputElement | null>(null);
const logoForm = useForm<{ logo: File | null }>({ logo: null });
const removingLogo = ref(false);

function uploadLogo(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    logoForm.logo = file;
    logoForm.submit(updateLogo(), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            logoForm.reset();

            if (logoInput.value) {
                logoInput.value.value = '';
            }
        },
    });
}

function removeLogo() {
    router.visit(destroyLogo(), {
        preserveScroll: true,
        onStart: () => (removingLogo.value = true),
        onFinish: () => (removingLogo.value = false),
    });
}
</script>

<template>
    <Head title="General settings" />

    <div class="grid gap-6">
        <section
            class="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-center sm:p-6"
        >
            <OrganizationAvatar
                :name="settings.name"
                :logo="settings.logo"
                class="size-16 rounded-xl text-lg"
            />
            <div class="min-w-0 flex-1">
                <h2 class="font-display text-base font-semibold">Logo</h2>
                <p class="text-sm text-muted-foreground">
                    Shown in the sidebar and on invitations. PNG, JPG or WebP,
                    at least 64 × 64 pixels, up to 2 MB.
                </p>
                <InputError :message="logoForm.errors.logo" class="mt-1" />
            </div>
            <div class="flex gap-2">
                <input
                    ref="logoInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="sr-only"
                    aria-label="Upload a logo"
                    @change="uploadLogo"
                />
                <Button
                    type="button"
                    variant="outline"
                    :disabled="logoForm.processing"
                    @click="logoInput?.click()"
                >
                    <Spinner v-if="logoForm.processing" />
                    <ImagePlus v-else />
                    {{ settings.logo ? 'Replace' : 'Upload logo' }}
                </Button>
                <Button
                    v-if="settings.logo"
                    type="button"
                    variant="ghost"
                    :disabled="removingLogo"
                    aria-label="Remove logo"
                    @click="removeLogo"
                >
                    <Trash2 />
                </Button>
            </div>
        </section>

        <SettingsPanel
            title="Organization details"
            description="The name and contact details people see across FlowPilot."
            :processing="form.processing"
            :dirty="form.isDirty"
            :saved="form.recentlySuccessful"
            @submit="save"
        >
            <FormField
                v-slot="field"
                label="Organization name"
                :error="form.errors.name"
            >
                <Input
                    v-bind="field"
                    v-model="form.name"
                    required
                    maxlength="120"
                    autocomplete="organization"
                />
            </FormField>

            <div class="grid gap-6 sm:grid-cols-2">
                <FormField
                    v-slot="field"
                    label="Industry"
                    :error="form.errors.industry"
                >
                    <Select v-model="form.industry">
                        <SelectTrigger v-bind="field" class="w-full">
                            <SelectValue placeholder="Choose an industry" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.industries"
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
                    label="Team size"
                    :error="form.errors.company_size"
                >
                    <Select v-model="form.company_size">
                        <SelectTrigger v-bind="field" class="w-full">
                            <SelectValue placeholder="Choose a size" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.companySizes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>
            </div>

            <FormField
                v-slot="websiteField"
                label="Website"
                optional
                :error="form.errors.website"
            >
                <Input
                    v-bind="websiteField"
                    v-model="form.website"
                    type="url"
                    :placeholder="websitePlaceholder"
                    autocomplete="url"
                />
            </FormField>

            <div class="grid gap-6 sm:grid-cols-2">
                <FormField
                    v-slot="field"
                    label="Contact email"
                    optional
                    help="Where FlowPilot sends account and billing notices."
                    :error="form.errors.contact_email"
                >
                    <Input
                        v-bind="field"
                        v-model="form.contact_email"
                        type="email"
                        autocomplete="email"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Phone"
                    :optional="true"
                    :error="form.errors.contact_phone"
                >
                    <Input
                        v-bind="field"
                        v-model="form.contact_phone"
                        type="tel"
                        autocomplete="tel"
                    />
                </FormField>
            </div>

            <FormField
                v-slot="field"
                label="Address"
                :optional="true"
                :error="form.errors.address"
            >
                <Input
                    v-bind="field"
                    v-model="form.address"
                    autocomplete="street-address"
                    maxlength="255"
                />
            </FormField>
        </SettingsPanel>
    </div>
</template>
