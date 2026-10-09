<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    BadgeCheck,
    KeyRound,
    MailWarning,
    Search,
    ShieldCheck,
    ShieldOff,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFilters } from '@/composables/useFilters';
import { formatNumber, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes/platform';
import { show as organizationShow } from '@/routes/platform/organizations';
import { index, passwordReset } from '@/routes/platform/users';
import type { Paginated } from '@/types/pagination';
import type { PlatformUserRow } from '@/types/platform';

const props = defineProps<{
    users: Paginated<PlatformUserRow>;
    filters: { q: string; admins: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Platform', href: dashboard() },
        { title: 'People', href: index() },
    ],
});

const { filters, reset } = useFilters(
    { q: props.filters.q, admins: props.filters.admins },
    { only: ['users', 'filters'] },
);

const hasFilters = computed(() => !!(filters.q || filters.admins));

const resetting = ref<PlatformUserRow | null>(null);
const resetOpen = ref(false);
const sending = ref(false);

function askReset(user: PlatformUserRow) {
    resetting.value = user;
    resetOpen.value = true;
}

function sendReset() {
    if (!resetting.value) {
        return;
    }

    router.post(
        passwordReset(resetting.value.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
            onSuccess: () => (resetOpen.value = false),
        },
    );
}
</script>

<template>
    <Head title="People" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8"
    >
        <PageHeader
            title="People"
            :description="`${formatNumber(users.total)} ${users.total === 1 ? 'account' : 'accounts'}. Help someone who is locked out by sending them a password reset link.`"
        />

        <div class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="flex flex-wrap items-center gap-4 border-b p-3">
                <div class="relative min-w-56 flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.q"
                        type="search"
                        placeholder="Search by name or email"
                        aria-label="Search people"
                        class="pl-8"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="admins-only" v-model="filters.admins" />
                    <Label for="admins-only" class="font-normal"
                        >Platform administrators only</Label
                    >
                </div>
            </div>

            <div v-if="users.data.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th
                                scope="col"
                                class="py-2.5 pr-3 pl-5 font-medium"
                            >
                                Person
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Organizations
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Security
                            </th>
                            <th scope="col" class="px-3 py-2.5 font-medium">
                                Last active
                            </th>
                            <th
                                scope="col"
                                class="py-2.5 pr-5 pl-3 text-right font-medium"
                            >
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b align-top last:border-0"
                        >
                            <td class="py-3 pr-3 pl-5">
                                <p
                                    class="flex items-center gap-2 font-medium whitespace-nowrap"
                                >
                                    {{ user.name }}
                                    <StatusBadge
                                        v-if="user.is_platform_admin"
                                        tone="info"
                                        :icon="ShieldCheck"
                                        >Platform admin</StatusBadge
                                    >
                                </p>
                                <p
                                    class="text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <ul
                                    v-if="user.organizations.length"
                                    class="grid gap-0.5"
                                >
                                    <li
                                        v-for="organization in user.organizations"
                                        :key="organization.slug"
                                        class="whitespace-nowrap"
                                    >
                                        <Link
                                            :href="
                                                organizationShow(
                                                    organization.slug,
                                                )
                                            "
                                            class="hover:text-primary hover:underline"
                                            >{{ organization.name }}</Link
                                        >
                                        <span class="text-muted-foreground">
                                            · {{ organization.role
                                            }}{{
                                                organization.status ===
                                                'suspended'
                                                    ? ', suspended'
                                                    : ''
                                            }}</span
                                        >
                                    </li>
                                </ul>
                                <span v-else class="text-muted-foreground"
                                    >None yet</span
                                >
                            </td>
                            <td class="px-3 py-3">
                                <ul class="grid gap-0.5 whitespace-nowrap">
                                    <li
                                        :class="
                                            user.verified
                                                ? 'text-success-text'
                                                : 'text-warning-text'
                                        "
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <component
                                            :is="
                                                user.verified
                                                    ? BadgeCheck
                                                    : MailWarning
                                            "
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        {{
                                            user.verified
                                                ? 'Email verified'
                                                : 'Email not verified'
                                        }}
                                    </li>
                                    <li
                                        :class="
                                            user.two_factor
                                                ? 'text-success-text'
                                                : 'text-muted-foreground'
                                        "
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <component
                                            :is="
                                                user.two_factor
                                                    ? ShieldCheck
                                                    : ShieldOff
                                            "
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        {{
                                            user.two_factor
                                                ? 'Two-factor on'
                                                : 'Two-factor off'
                                        }}
                                    </li>
                                </ul>
                            </td>
                            <td
                                class="px-3 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    user.last_active_at
                                        ? timeAgo(user.last_active_at)
                                        : 'Never'
                                }}
                            </td>
                            <td class="py-2 pr-5 pl-3 text-right">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="askReset(user)"
                                >
                                    <KeyRound />
                                    Send reset link
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else-if="hasFilters"
                :icon="Search"
                title="Nobody matches"
                description="Try part of the name or email address."
            >
                <Button
                    variant="outline"
                    @click="reset({ q: '', admins: false })"
                    >Clear filters</Button
                >
            </EmptyState>
            <EmptyState
                v-else
                :icon="Users"
                title="No accounts yet"
                description="Everyone who signs up or accepts an invitation is listed here."
            />

            <Pagination :paginator="users" noun="people" />
        </div>

        <ConfirmDialog
            v-model:open="resetOpen"
            :title="`Send ${resetting?.name ?? 'them'} a reset link?`"
            :description="`An email goes to ${resetting?.email ?? 'their address'} with a link to choose a new password. Their current password keeps working until they do.`"
            confirm-label="Send reset link"
            :processing="sending"
            @confirm="sendReset"
        />
    </div>
</template>
