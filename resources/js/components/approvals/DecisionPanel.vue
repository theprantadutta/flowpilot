<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CircleCheck, CircleX, FilePen, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { decide } from '@/routes/approvals';
import type { ApprovalAbilities, ApprovalItem } from '@/types/approvals';

/**
 * Approve, reject or send back for changes. Saying no or "not yet" always
 * comes with a note, so the requester knows what to do next.
 */
const props = defineProps<{
    approval: ApprovalItem;
    can: ApprovalAbilities;
}>();

type Decision = 'approve' | 'reject' | 'request_changes';

const form = useForm({ decision: 'approve' as Decision, note: '' });
const choosing = ref<Decision | null>(null);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function decideAs(decision: Decision) {
    if (decision !== 'approve' && choosing.value !== decision) {
        // Ask for the reason first.
        choosing.value = decision;
        form.clearErrors();

        return;
    }

    form.decision = decision;
    form.submit(decide({ approval: props.approval.id }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            choosing.value = null;
        },
    });
}

const noteLabel = computed(() =>
    choosing.value === 'reject'
        ? 'Why are you rejecting it?'
        : choosing.value === 'request_changes'
          ? 'What needs to change?'
          : 'Note for the requester (optional)',
);
</script>

<template>
    <section
        aria-labelledby="decision-heading"
        class="grid gap-4 rounded-xl border border-warning/40 bg-warning-soft/40 p-4"
    >
        <div>
            <h2
                id="decision-heading"
                class="font-display text-base font-semibold"
            >
                Your decision
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ approval.requester?.name ?? 'A workflow' }} is waiting on
                this.
                <template v-if="approval.amount">
                    The amount is {{ approval.amount.formatted }}.</template
                >
            </p>
        </div>

        <p
            v-if="can.deciding_for_someone_else"
            class="flex items-start gap-2 rounded-lg bg-card px-3 py-2 text-sm text-warning-text"
        >
            <ShieldAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            This request is not addressed to you. Deciding it is recorded as on
            behalf of {{ approval.approver?.name ?? 'the approver' }}.
        </p>

        <div class="grid gap-2">
            <label for="decision-note" class="text-sm font-medium">{{
                noteLabel
            }}</label>
            <Textarea
                id="decision-note"
                v-model="form.note"
                rows="3"
                maxlength="2000"
                :aria-invalid="errors.note ? true : undefined"
                aria-describedby="decision-note-error"
            />
            <InputError
                id="decision-note-error"
                :message="errors.note ?? errors.decision"
            />
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="can.approve"
                type="button"
                :disabled="form.processing"
                @click="decideAs('approve')"
            >
                <Spinner
                    v-if="form.processing && form.decision === 'approve'"
                />
                <CircleCheck v-else />
                Approve request
            </Button>
            <Button
                v-if="can.requestChanges"
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="decideAs('request_changes')"
            >
                <Spinner
                    v-if="
                        form.processing && form.decision === 'request_changes'
                    "
                />
                <FilePen v-else />
                {{
                    choosing === 'request_changes'
                        ? 'Send back for changes'
                        : 'Request changes'
                }}
            </Button>
            <Button
                v-if="can.reject"
                type="button"
                variant="outline"
                class="text-danger-text hover:text-danger-text"
                :disabled="form.processing"
                @click="decideAs('reject')"
            >
                <Spinner v-if="form.processing && form.decision === 'reject'" />
                <CircleX v-else />
                {{
                    choosing === 'reject'
                        ? 'Confirm rejection'
                        : 'Reject request'
                }}
            </Button>
        </div>
    </section>
</template>
