<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { MailPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/members/invitations';
import type { RoleOption } from '@/types/members';

const props = defineProps<{
    roles: RoleOption[];
    organizationName: string;
}>();

const open = ref(false);

const assignableRoles = computed(() =>
    props.roles.filter((role) => role.assignable),
);
const defaultRole = computed(
    () =>
        assignableRoles.value.find((role) => role.value === 'employee')
            ?.value ?? assignableRoles.value[0]?.value,
);
const selectedRole = ref<string | undefined>(defaultRole.value);
const selectedDescription = computed(
    () =>
        props.roles.find((role) => role.value === selectedRole.value)
            ?.description,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button>
                <MailPlus />
                Invite member
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    >Invite someone to {{ organizationName }}</DialogTitle
                >
                <DialogDescription>
                    They get an email with a link to join. The link works for 7
                    days.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="store.form()"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="grid gap-5"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="invite-email">Email address</Label>
                    <Input
                        id="invite-email"
                        name="email"
                        type="email"
                        autocomplete="off"
                        required
                        placeholder="colleague@company.com"
                        :aria-invalid="!!errors.email"
                        aria-describedby="invite-email-error"
                    />
                    <InputError
                        id="invite-email-error"
                        :message="errors.email"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="invite-role">Role</Label>
                    <Select v-model="selectedRole" name="role">
                        <SelectTrigger id="invite-role" class="w-full">
                            <SelectValue placeholder="Choose a role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in assignableRoles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="selectedDescription"
                        class="text-xs text-muted-foreground"
                    >
                        {{ selectedDescription }}
                    </p>
                    <InputError :message="errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="invite-department">
                        Department
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        >
                    </Label>
                    <Input
                        id="invite-department"
                        name="department"
                        placeholder="Operations"
                        maxlength="80"
                    />
                    <InputError :message="errors.department" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Send invitation
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
