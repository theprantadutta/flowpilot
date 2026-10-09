<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import DateInput from '@/components/DateInput.vue';
import FormField from '@/components/FormField.vue';
import PersonPicker from '@/components/PersonPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useOrganization } from '@/composables/useOrganization';
import { store } from '@/routes/approvals';
import type { EnumOption, MemberOption } from '@/types/operations';

/**
 * Ask someone (or anyone in a role) to approve something. It opens on its
 * own page once sent, where the decision, comments and files live.
 */
const props = defineProps<{
    members: MemberOption[];
    roles: { value: string; label: string }[];
    priorities: EnumOption[];
}>();

const open = defineModel<boolean>('open', { required: true });
const { organization } = useOrganization();

const form = useForm({
    title: '',
    description: '',
    approver_type: 'role' as 'role' | 'member',
    approver_role:
        props.roles.find((role) => role.value === 'manager')?.value ??
        props.roles[0]?.value ??
        '',
    approver_id: null as number | null,
    amount: '',
    priority: 'medium',
    due_date: null as string | null,
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        amount: data.amount.trim() === '' ? null : data.amount.trim(),
        approver_role:
            data.approver_type === 'role' ? data.approver_role : null,
        approver_id: data.approver_type === 'member' ? data.approver_id : null,
    })).submit(store(), {
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>New approval request</DialogTitle>
                <DialogDescription>
                    Say what needs approving and who decides. They are notified
                    straight away.
                </DialogDescription>
            </DialogHeader>

            <form id="new-approval" class="grid gap-5" @submit.prevent="submit">
                <FormField
                    v-slot="field"
                    label="What needs approving"
                    :error="form.errors.title"
                >
                    <Input
                        v-bind="field"
                        v-model="form.title"
                        v-focus
                        required
                        maxlength="200"
                        placeholder="Replacement conveyor belt for line 2"
                    />
                </FormField>

                <FormField
                    v-slot="field"
                    label="Details"
                    optional
                    :error="form.errors.description"
                >
                    <Textarea
                        v-bind="field"
                        v-model="form.description"
                        rows="3"
                        maxlength="5000"
                        placeholder="Why it is needed, supplier, alternatives considered."
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField
                        v-slot="field"
                        label="Amount"
                        optional
                        :error="form.errors.amount"
                    >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs text-muted-foreground"
                                >{{ organization?.currency }}</span
                            >
                            <Input
                                v-bind="field"
                                v-model="form.amount"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="pl-12 figures"
                            />
                        </div>
                    </FormField>
                    <FormField
                        v-slot="field"
                        label="Priority"
                        :error="form.errors.priority"
                    >
                        <Select v-model="form.priority">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in priorities"
                                    :key="option.value"
                                    :value="option.value"
                                    >{{ option.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>

                <fieldset class="grid gap-3">
                    <legend class="mb-1 text-sm font-medium">
                        Who decides
                    </legend>
                    <div
                        class="flex gap-2"
                        role="radiogroup"
                        aria-label="Who decides"
                    >
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                form.approver_type === 'role'
                                    ? 'default'
                                    : 'outline'
                            "
                            role="radio"
                            :aria-checked="form.approver_type === 'role'"
                            @click="form.approver_type = 'role'"
                        >
                            Anyone in a role
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                form.approver_type === 'member'
                                    ? 'default'
                                    : 'outline'
                            "
                            role="radio"
                            :aria-checked="form.approver_type === 'member'"
                            @click="form.approver_type = 'member'"
                        >
                            A specific person
                        </Button>
                    </div>
                    <FormField
                        v-if="form.approver_type === 'role'"
                        v-slot="field"
                        label="Role"
                        :error="form.errors.approver_role"
                    >
                        <Select v-model="form.approver_role">
                            <SelectTrigger v-bind="field" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role.value"
                                    :value="role.value"
                                    >{{ role.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        v-else
                        v-slot="field"
                        label="Person"
                        :error="form.errors.approver_id"
                    >
                        <PersonPicker
                            :id="field.id"
                            v-model="form.approver_id"
                            :members="members"
                            :allow-none="false"
                            placeholder="Choose who approves"
                        />
                    </FormField>
                </fieldset>

                <FormField
                    v-slot="field"
                    label="Decision needed by"
                    optional
                    help="If left empty, the organization’s usual time for approvals applies."
                    :error="form.errors.due_date"
                >
                    <DateInput :id="field.id" v-model="form.due_date" />
                </FormField>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="open = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="new-approval"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Send for approval
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
