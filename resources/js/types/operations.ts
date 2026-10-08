import type { Tone } from './ui';

/** An enum value as the server describes it (status, priority, severity). */
export type EnumOption = {
    value: string;
    label: string;
    tone: Tone;
    icon: string;
};

export type PersonSummary = {
    id: number;
    name: string;
    avatar: string | null;
};

export type MemberOption = PersonSummary & { role: string };

export type ProjectOption = { id: string; name: string };

export type Can = { update: boolean; delete: boolean } | null;

export type ProjectItem = {
    id: string;
    name: string;
    description: string | null;
    status: EnumOption;
    priority: EnumOption;
    owner?: PersonSummary | null;
    members?: PersonSummary[];
    start_date: string | null;
    due_date: string | null;
    is_overdue: boolean;
    budget: { amount: number; currency: string; input: string } | null;
    tags: string[];
    tasks_count: number | null;
    done_tasks_count: number | null;
    open_issues_count: number | null;
    progress: number | null;
    completed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can: Can;
};

export type ChecklistItem = { id: string; body: string; is_done: boolean };

export type TaskLink = {
    id: string;
    reference: string;
    title: string;
    status: EnumOption;
};

export type TaskItem = {
    id: string;
    reference: string;
    title: string;
    description: string | null;
    status: EnumOption;
    priority: EnumOption;
    project?: ProjectOption | null;
    assignee?: PersonSummary | null;
    reporter?: PersonSummary | null;
    due_date: string | null;
    is_overdue: boolean;
    position: number;
    tags: string[];
    checklist?: { done: number; total: number; items: ChecklistItem[] };
    checklist_summary?: { done: number; total: number };
    comments_count?: number;
    attachments_count?: number;
    dependencies?: TaskLink[];
    dependents?: TaskLink[];
    completed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can: Can;
};

export type IssueItem = {
    id: string;
    reference: string;
    title: string;
    description: string | null;
    severity: EnumOption;
    status: EnumOption;
    project?: ProjectOption | null;
    assignee?: PersonSummary | null;
    reporter?: PersonSummary | null;
    due_date: string | null;
    tags: string[];
    comments_count?: number;
    resolved_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can: Can;
};

export type CommentItem = {
    id: string;
    body: string;
    author: { id: number | null; name: string; avatar: string | null };
    created_at: string | null;
    edited: boolean;
    can_delete: boolean;
};

export type FileItem = {
    id: string;
    name: string;
    extension: string;
    mime_type: string;
    size: number;
    uploaded_by: string | null;
    created_at: string | null;
    download_url: string;
    can_delete?: boolean;
    source?: 'project' | 'task';
};

/** Laravel API resource collections wrap their items in `data`. */
export type ResourceCollection<T> = { data: T[] };

export type PaginatedResource<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
    };
};
