<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Clock, RotateCw, Stamp } from '@lucide/vue';
import EnumBadge from '@/components/EnumBadge.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDateTime, timeAgo } from '@/lib/format';
import { toneChip } from '@/lib/workflows';
import { cn } from '@/lib/utils';
import type { WorkflowStepItem } from '@/types/workflows';

/**
 * Every step a run took, in order: what it did, which way it went, how many
 * attempts it needed and what went wrong.
 */
defineProps<{ steps: WorkflowStepItem[] }>();

const outputLabels: Record<string, string> = {
    trigger: 'Started by',
    started_by: 'Started by',
    record: 'Record',
    matched: 'Rules matched',
    case: 'Case taken',
    title: 'Title',
    sent_to: 'Sent to',
    emailed: 'Emailed',
    subject: 'Subject',
    note: 'Note',
    until: 'Waiting until',
    waited_until: 'Waited until',
    reference: 'Created',
    assignee: 'Assigned to',
    field: 'Field',
    value: 'Set to',
    tag: 'Tag',
    status: 'Response',
    summary: 'Summary',
    approval: 'Request',
    approver: 'Waiting on',
    decision: 'Decision',
    decided_by: 'Decided by',
};

const hidden = new Set([
    'checks',
    'id',
    'url',
    'delivery_id',
    'delivery_key',
    'record_type',
    'approval_url',
]);

/** The approval request a step raised, to link to it. */
function approvalLink(
    step: WorkflowStepItem,
): { url: string; reference: string } | null {
    const url = step.output?.approval_url;
    const reference = step.output?.approval;

    return typeof url === 'string' && typeof reference === 'string'
        ? { url, reference }
        : null;
}

function outputRows(
    step: WorkflowStepItem,
): { label: string; value: string }[] {
    const output = step.output ?? {};

    return Object.entries(output)
        .filter(
            ([key, value]) =>
                !hidden.has(key) &&
                value !== null &&
                value !== '' &&
                !(Array.isArray(value) && value.length === 0),
        )
        .map(([key, value]) => ({
            label:
                key === 'trigger'
                    ? 'Trigger'
                    : (outputLabels[key] ?? key.replaceAll('_', ' ')),
            value: Array.isArray(value)
                ? value.join(', ')
                : typeof value === 'boolean'
                  ? value
                      ? 'Yes'
                      : 'No'
                  : key === 'until' || key === 'waited_until'
                    ? formatDateTime(String(value))
                    : String(value),
        }));
}

type Check = {
    field: string;
    operator: string;
    expected: string | null;
    actual: unknown;
    passed: boolean;
};

function checks(step: WorkflowStepItem): Check[] {
    const list = step.output?.checks;

    return Array.isArray(list) ? (list as Check[]) : [];
}
</script>

<template>
    <ol class="relative grid gap-0">
        <li
            v-for="(step, index) in steps"
            :key="step.id"
            class="relative flex gap-4 pb-6 last:pb-0"
        >
            <span
                v-if="index < steps.length - 1"
                class="absolute top-10 bottom-0 left-[1.1875rem] w-px bg-border"
                aria-hidden="true"
            />
            <span
                :class="
                    cn(
                        'relative z-10 flex size-10 shrink-0 items-center justify-center rounded-xl border-4 border-background',
                        toneChip[step.type.tone],
                    )
                "
            >
                <NamedIcon
                    :name="
                        step.type.value === 'trigger' ? 'zap' : step.type.icon
                    "
                    class="size-4"
                />
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="font-medium">{{ step.label }}</p>
                    <EnumBadge :option="step.status" />
                    <span
                        v-if="step.outcome_label"
                        class="rounded-full border px-2 py-0.5 text-xs font-medium"
                        >→ {{ step.outcome_label }}</span
                    >
                    <span
                        v-if="step.attempts > 1"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <RotateCw class="size-3" aria-hidden="true" />{{
                            step.attempts
                        }}
                        attempts
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ step.type.label }}
                    <template v-if="step.completed_at">
                        · {{ formatDateTime(step.completed_at) }}</template
                    >
                    <template v-else-if="step.started_at">
                        · started {{ timeAgo(step.started_at) }}</template
                    >
                </p>

                <p
                    v-if="step.status.value === 'waiting' && step.resume_at"
                    class="mt-2 flex items-center gap-1.5 text-sm text-warning-text"
                >
                    <Clock class="size-4" aria-hidden="true" />
                    Continues {{ timeAgo(step.resume_at) }} ({{
                        formatDateTime(step.resume_at)
                    }})
                </p>
                <p
                    v-else-if="
                        step.status.value === 'pending' &&
                        step.resume_at &&
                        step.error
                    "
                    class="mt-2 text-sm text-warning-text"
                >
                    Trying again {{ timeAgo(step.resume_at) }}.
                </p>

                <p
                    v-if="step.error"
                    role="alert"
                    class="mt-2 rounded-lg border border-danger/30 bg-danger-soft/60 px-3 py-2 text-sm text-danger-text"
                >
                    {{ step.error }}
                </p>

                <ul v-if="checks(step).length" class="mt-2 grid gap-1 text-sm">
                    <li
                        v-for="(check, position) in checks(step)"
                        :key="position"
                        class="flex items-start gap-2"
                    >
                        <span
                            :class="
                                cn(
                                    'mt-0.5 rounded px-1.5 text-[0.6875rem] font-semibold',
                                    check.passed
                                        ? 'bg-success-soft text-success-text'
                                        : 'bg-neutral-soft text-neutral-text',
                                )
                            "
                        >
                            {{ check.passed ? 'Yes' : 'No' }}
                        </span>
                        <span>
                            {{ check.field }} {{ check.operator }}
                            <strong v-if="check.expected !== null">{{
                                check.expected
                            }}</strong>
                            <span class="text-muted-foreground">
                                (was
                                {{
                                    check.actual === null || check.actual === ''
                                        ? 'empty'
                                        : check.actual
                                }})</span
                            >
                        </span>
                    </li>
                </ul>

                <Link
                    v-if="approvalLink(step)"
                    :href="approvalLink(step)!.url"
                    class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    <Stamp class="size-4" aria-hidden="true" />
                    Open request {{ approvalLink(step)!.reference }}
                </Link>

                <dl
                    v-if="outputRows(step).length"
                    class="mt-2 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[auto_1fr]"
                >
                    <template v-for="row in outputRows(step)" :key="row.label">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="break-words">{{ row.value }}</dd>
                    </template>
                </dl>

                <Collapsible v-if="step.delivery" class="mt-2">
                    <CollapsibleTrigger
                        class="group flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                    >
                        <ChevronDown
                            class="size-3.5 transition-transform group-data-[state=open]:rotate-180"
                            aria-hidden="true"
                        />
                        Delivery details
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <dl
                            class="mt-2 grid gap-x-4 gap-y-1 rounded-lg bg-secondary/50 p-3 text-xs sm:grid-cols-[auto_1fr]"
                        >
                            <dt class="text-muted-foreground">Address</dt>
                            <dd class="break-all">{{ step.delivery.url }}</dd>
                            <dt class="text-muted-foreground">Answer</dt>
                            <dd>
                                {{ step.delivery.response_status ?? 'No answer'
                                }}<template
                                    v-if="step.delivery.duration_ms !== null"
                                >
                                    in
                                    {{ step.delivery.duration_ms }} ms</template
                                >
                            </dd>
                            <dt class="text-muted-foreground">Attempts</dt>
                            <dd>{{ step.delivery.attempts }}</dd>
                            <dt class="text-muted-foreground">
                                Idempotency key
                            </dt>
                            <dd class="font-mono break-all">
                                {{ step.delivery.delivery_key }}
                            </dd>
                            <template v-if="step.delivery.response_body">
                                <dt class="text-muted-foreground">Response</dt>
                                <dd>
                                    <pre
                                        class="max-h-40 overflow-auto font-mono text-[0.6875rem] whitespace-pre-wrap"
                                        >{{ step.delivery.response_body }}</pre>
                                </dd>
                            </template>
                        </dl>
                    </CollapsibleContent>
                </Collapsible>
            </div>
        </li>
    </ol>
</template>
