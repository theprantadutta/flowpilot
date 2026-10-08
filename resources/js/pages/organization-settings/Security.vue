<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Copy, Eye, KeyRound, RefreshCw, ShieldAlert } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
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
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { HttpError } from '@/lib/http';
import { show, update } from '@/routes/organization-settings';
import {
    rotate as rotateSecret,
    show as showSecret,
} from '@/routes/organization-settings/webhook-secret';
import { edit as securityEdit } from '@/routes/security';

const props = defineProps<{
    settings: {
        require_two_factor: boolean;
        idle_timeout_minutes: number;
        actor_has_two_factor: boolean;
        webhook_secret_hint: string | null;
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

const secret = ref<string | null>(null);
const secretError = ref<string | null>(null);
const revealing = ref(false);
const copied = ref(false);
const rotating = ref(false);
const rotateProcessing = ref(false);

async function reveal() {
    revealing.value = true;
    secretError.value = null;

    try {
        const response = await fetch(showSecret().url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new HttpError(
                response.status,
                response.status === 429
                    ? 'Too many reveals. Wait a minute and try again.'
                    : 'The secret could not be shown.',
            );
        }

        secret.value = ((await response.json()) as { secret: string }).secret;
    } catch (error) {
        secretError.value =
            error instanceof HttpError
                ? error.message
                : 'The secret could not be shown.';
    } finally {
        revealing.value = false;
    }
}

async function copySecret() {
    if (secret.value) {
        await navigator.clipboard.writeText(secret.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
}

function confirmRotate() {
    router.post(
        rotateSecret().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (rotateProcessing.value = true),
            onFinish: () => (rotateProcessing.value = false),
            onSuccess: () => {
                rotating.value = false;
                secret.value = null;
            },
        },
    );
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

    <div class="grid gap-6">
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
                            Members without it are asked to turn it on before
                            they can open this organization.
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

        <section
            class="overflow-hidden rounded-xl border bg-card shadow-xs"
            aria-labelledby="webhook-signing"
        >
            <div class="space-y-1 border-b px-5 py-4 sm:px-6">
                <h2
                    id="webhook-signing"
                    class="font-display text-base font-semibold"
                >
                    Webhook signing secret
                </h2>
                <p class="text-sm text-pretty text-muted-foreground">
                    Workflows sign every webhook with this secret, so the
                    receiving system can check it came from you. Each delivery
                    has an <code>X-FlowPilot-Signature</code> header: an
                    HMAC-SHA256 of the timestamp and body.
                </p>
            </div>
            <div class="grid gap-4 px-5 py-5 sm:px-6">
                <div class="flex flex-wrap items-center gap-3">
                    <KeyRound
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <code
                        v-if="secret"
                        class="rounded-md bg-secondary px-2 py-1 font-mono text-sm break-all"
                        >{{ secret }}</code
                    >
                    <span
                        v-else-if="settings.webhook_secret_hint"
                        class="font-mono text-sm text-muted-foreground"
                        >whsec_••••••••{{ settings.webhook_secret_hint }}</span
                    >
                    <span v-else class="text-sm text-muted-foreground"
                        >Created the first time you reveal it or a webhook is
                        sent.</span
                    >
                </div>
                <p
                    v-if="secretError"
                    role="alert"
                    class="text-sm text-danger-text"
                >
                    {{ secretError }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!secret"
                        type="button"
                        variant="outline"
                        :disabled="revealing"
                        @click="reveal"
                    >
                        <Eye />
                        Reveal secret
                    </Button>
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        @click="copySecret"
                    >
                        <Copy />
                        {{ copied ? 'Copied' : 'Copy secret' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="rotating = true"
                    >
                        <RefreshCw />
                        Replace secret
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Revealing and replacing the secret are recorded in the audit
                    log.
                </p>
            </div>
        </section>

        <ConfirmDialog
            v-model:open="rotating"
            title="Replace the signing secret?"
            description="Webhooks are signed with the new secret straight away. Receivers checking the old one will reject them until you update them."
            confirm-label="Replace secret"
            destructive
            :processing="rotateProcessing"
            @confirm="confirmRotate"
        />
    </div>
</template>
