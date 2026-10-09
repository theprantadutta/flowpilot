<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { Clock, GitBranch, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import DecisionPanel from '@/components/approvals/DecisionPanel.vue';
import ResubmitPanel from '@/components/approvals/ResubmitPanel.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import CommentThread from '@/components/operations/CommentThread.vue';
import FileList from '@/components/operations/FileList.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as approvalsIndex, show, withdraw } from '@/routes/approvals';
import { store as storeAttachment } from '@/routes/approvals/attachments';
import { store as storeComment } from '@/routes/approvals/comments';
import type { ActivityEntry } from '@/types/activity';
import type { ApprovalAbilities, ApprovalItem } from '@/types/approvals';
import type { CommentItem, FileItem } from '@/types/operations';

const props = defineProps<{
    approval: ApprovalItem;
    comments: CommentItem[];
    attachments: FileItem[];
    history?: ActivityEntry[];
    can: ApprovalAbilities;
}>();

const { organization } = useOrganization();

setLayoutProps({
    breadcrumbs: [
        { title: 'Approvals', href: approvalsIndex() },
        {
            title: props.approval.reference,
            href: show({ approval: props.approval.id }),
        },
    ],
});

const canDecide = computed(
    () => props.can.approve || props.can.reject || props.can.requestChanges,
);
const withdrawing = ref(false);
const processing = ref(false);

function confirmWithdraw() {
    router.post(
        withdraw({ approval: props.approval.id }).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => (withdrawing.value = false),
        },
    );
}

const outcome = computed(() => {
    const status = props.approval.status.value;

    if (status === 'approved' || status === 'rejected') {
        return `${props.approval.decider?.name ?? 'Someone'} ${status} this ${timeAgo(props.approval.decided_at)}.`;
    }

    if (status === 'expired') {
        return 'Nobody decided before it was due, so it expired.';
    }

    if (status === 'cancelled') {
        return 'This request was withdrawn.';
    }

    return null;
});
</script>

<template>
    <Head :title="`${approval.reference} ${approval.title}`" />

    <div
        class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:py-8"
    >
        <main class="grid content-start gap-6">
            <header class="grid gap-3">
                <p class="figures text-sm text-muted-foreground">
                    {{ approval.reference }}
                </p>
                <h1 class="text-2xl leading-tight font-semibold text-balance">
                    {{ approval.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-3">
                    <EnumBadge :option="approval.status" />
                    <EnumBadge :option="approval.priority" variant="plain" />
                    <span
                        v-if="approval.amount"
                        class="font-display figures text-2xl font-semibold"
                        >{{ approval.amount.formatted }}</span
                    >
                </div>
            </header>

            <p
                v-if="outcome"
                role="status"
                :class="
                    cn(
                        'rounded-lg px-4 py-3 text-sm',
                        approval.status.value === 'approved'
                            ? 'bg-success-soft text-success-text'
                            : approval.status.value === 'cancelled'
                              ? 'bg-neutral-soft text-neutral-text'
                              : 'bg-danger-soft text-danger-text',
                    )
                "
            >
                {{ outcome }}
                <span
                    v-if="approval.decision_note"
                    class="mt-1 block font-medium"
                    >“{{ approval.decision_note }}”</span
                >
            </p>

            <DecisionPanel v-if="canDecide" :approval="approval" :can="can" />
            <ResubmitPanel
                v-else-if="can.resubmit"
                :key="approval.status.value"
                :approval="approval"
            />
            <p
                v-else-if="approval.status.value === 'changes_requested'"
                class="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning-text"
            >
                {{ approval.decider?.name ?? 'The approver' }} asked for
                changes: “{{ approval.decision_note }}”. Waiting for
                {{ approval.requester?.name ?? 'the requester' }} to resubmit.
            </p>

            <section
                v-if="approval.description"
                aria-labelledby="description-heading"
                class="grid gap-2"
            >
                <h2 id="description-heading" class="text-sm font-semibold">
                    Details
                </h2>
                <p class="text-sm leading-relaxed whitespace-pre-line">
                    {{ approval.description }}
                </p>
            </section>

            <section
                v-if="approval.details.length"
                aria-labelledby="facts-heading"
                class="grid gap-2"
            >
                <h2 id="facts-heading" class="text-sm font-semibold">
                    From the request
                </h2>
                <dl
                    class="grid gap-x-6 gap-y-2 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2"
                >
                    <div
                        v-for="detail in approval.details"
                        :key="detail.label"
                        class="min-w-0"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ detail.label }}
                        </dt>
                        <dd class="break-words">{{ detail.value }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="files-heading" class="grid gap-3">
                <h2 id="files-heading" class="text-sm font-semibold">Files</h2>
                <FileList
                    :files="attachments"
                    :upload-to="
                        can.update
                            ? storeAttachment({ approval: approval.id })
                            : null
                    "
                    compact
                />
            </section>

            <section aria-labelledby="comments-heading" class="grid gap-3">
                <h2 id="comments-heading" class="text-sm font-semibold">
                    Comments
                </h2>
                <CommentThread
                    :comments="comments"
                    :post-to="storeComment({ approval: approval.id })"
                    :timezone="organization?.timezone"
                />
            </section>

            <section aria-labelledby="history-heading" class="grid gap-3">
                <h2 id="history-heading" class="text-sm font-semibold">
                    Audit trail
                </h2>
                <div v-if="history === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-9 w-full" />
                </div>
                <ol v-else-if="history.length">
                    <ActivityItem
                        v-for="(entry, index) in history"
                        :key="entry.id"
                        :entry="entry"
                        :timezone="organization?.timezone"
                        :connected="index < history.length - 1"
                    />
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    Nothing recorded yet.
                </p>
            </section>
        </main>

        <aside
            class="grid h-fit gap-5 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6"
        >
            <div class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground">
                    Requested by
                </p>
                <p
                    v-if="approval.requester"
                    class="flex items-center gap-2 text-sm"
                >
                    <MemberAvatar
                        :name="approval.requester.name"
                        :avatar="approval.requester.avatar"
                        class="size-6"
                    />
                    {{ approval.requester.name }}
                </p>
                <p v-else class="text-sm">A workflow</p>
                <p class="text-xs text-muted-foreground">
                    {{
                        formatDateTime(
                            approval.created_at,
                            organization?.timezone,
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ approval.decider ? 'Decided by' : approval.is_open ? 'Waiting on' : 'Was waiting on' }}
                </p>
                <p
                    v-if="approval.decider"
                    class="flex items-center gap-2 text-sm"
                >
                    <MemberAvatar
                        :name="approval.decider.name"
                        :avatar="approval.decider.avatar"
                        class="size-6"
                    />
                    {{ approval.decider.name }}
                </p>
                <p
                    v-else-if="approval.approver"
                    class="flex items-center gap-2 text-sm"
                >
                    <MemberAvatar
                        :name="approval.approver.name"
                        :avatar="approval.approver.avatar"
                        class="size-6"
                    />
                    {{ approval.approver.name }}
                </p>
                <p v-else-if="approval.approver_role" class="text-sm">
                    Anyone in {{ approval.approver_role.label }}
                </p>
            </div>

            <div v-if="approval.due_at && approval.is_open" class="grid gap-1.5">
                <p class="text-xs font-medium text-muted-foreground">
                    Decision due
                </p>
                <p
                    :class="
                        cn(
                            'flex items-center gap-1.5 text-sm',
                            approval.is_overdue &&
                                'font-medium text-danger-text',
                        )
                    "
                >
                    <Clock class="size-4" aria-hidden="true" />
                    {{
                        formatDateTime(approval.due_at, organization?.timezone)
                    }}
                </p>
                <p
                    v-if="approval.is_open"
                    class="text-xs text-muted-foreground"
                >
                    {{ approval.is_overdue ? 'Overdue. ' : ''
                    }}{{
                        approval.when_overdue === 'reject'
                            ? 'Rejected automatically if nobody decides.'
                            : 'Approvers get a reminder if it runs late.'
                    }}
                </p>
            </div>

            <div
                v-if="approval.subject || approval.workflow_run_url"
                class="grid gap-2"
            >
                <p class="text-xs font-medium text-muted-foreground">Related</p>
                <Link
                    v-if="approval.subject?.url"
                    :href="approval.subject.url"
                    class="text-sm font-medium hover:underline"
                    >{{ approval.subject.label }}</Link
                >
                <Link
                    v-if="approval.workflow_run_url"
                    :href="approval.workflow_run_url"
                    class="flex items-center gap-1.5 text-sm font-medium hover:underline"
                >
                    <GitBranch class="size-4" aria-hidden="true" />
                    Workflow run
                </Link>
            </div>

            <Button
                v-if="can.withdraw"
                variant="outline"
                class="justify-self-start"
                @click="withdrawing = true"
            >
                <Undo2 />
                Withdraw request
            </Button>
        </aside>
    </div>

    <ConfirmDialog
        v-model:open="withdrawing"
        :title="`Withdraw ${approval.reference}?`"
        description="The approvers no longer need to decide. The request stays in the history."
        confirm-label="Withdraw request"
        destructive
        :processing="processing"
        @confirm="confirmWithdraw"
    />
</template>
