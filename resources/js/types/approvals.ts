import type { EnumOption, PersonSummary } from './operations';

export type ApprovalAmount = {
    minor: number;
    currency: string;
    formatted: string;
    input: string | null;
};

export type ApprovalItem = {
    id: string;
    reference: string;
    title: string;
    description: string | null;
    status: EnumOption;
    is_open: boolean;
    priority: EnumOption;
    requester?: PersonSummary | null;
    approver?: PersonSummary | null;
    approver_role: { value: string; label: string } | null;
    amount: ApprovalAmount | null;
    details: { label: string; value: string }[];
    subject: { type: string; label: string | null; url: string | null } | null;
    workflow_run_url: string | null;
    when_overdue: 'remind' | 'reject';
    due_at: string | null;
    is_overdue: boolean;
    decider?: PersonSummary | null;
    decided_at: string | null;
    decision_note: string | null;
    created_at: string | null;
};

export type ApprovalAbilities = {
    approve: boolean;
    reject: boolean;
    requestChanges: boolean;
    resubmit: boolean;
    withdraw: boolean;
    update: boolean;
    deciding_for_someone_else: boolean;
};
