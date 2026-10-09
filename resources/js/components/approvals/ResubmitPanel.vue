<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { resubmit } from '@/routes/approvals';
import type { ApprovalItem } from '@/types/approvals';

/**
 * The requester answers a request for changes and sends it back.
 */
const props = defineProps<{ approval: ApprovalItem }>();

const form = useForm({
    title: props.approval.title,
    description: props.approval.description ?? '',
    amount: props.approval.amount?.input ?? '',
    note: '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        amount:
            props.approval.amount || data.amount.trim() !== ''
                ? data.amount.trim() || null
                : undefined,
    })).submit(resubmit({ approval: props.approval.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
}
</script>

<template>
    <section
        aria-labelledby="resubmit-heading"
        class="grid gap-4 rounded-xl border border-warning/40 bg-warning-soft/40 p-4"
    >
        <div>
            <h2
                id="resubmit-heading"
                class="font-display text-base font-semibold"
            >
                Changes requested
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ approval.decider?.name ?? 'The approver' }} asked:
                <span class="font-medium text-foreground"
                    >“{{ approval.decision_note }}”</span
                >
            </p>
        </div>
        <form class="grid gap-4" @submit.prevent="submit">
            <FormField
                v-slot="field"
                label="What needs approving"
                :error="form.errors.title"
            >
                <Input v-bind="field" v-model="form.title" maxlength="200" />
            </FormField>
            <FormField
                v-slot="field"
                label="Amount"
                optional
                :error="form.errors.amount"
            >
                <Input
                    v-bind="field"
                    v-model="form.amount"
                    inputmode="decimal"
                    class="figures sm:w-60"
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
                />
            </FormField>
            <FormField
                v-slot="field"
                label="What you changed"
                :error="form.errors.note"
            >
                <Textarea
                    v-bind="field"
                    v-model="form.note"
                    rows="2"
                    maxlength="2000"
                    placeholder="Attached the supplier quote and lowered the quantity."
                />
            </FormField>
            <Button
                type="submit"
                class="justify-self-start"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                Send back for a decision
            </Button>
        </form>
    </section>
</template>
