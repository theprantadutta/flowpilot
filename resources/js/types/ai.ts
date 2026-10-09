export type BriefLink = { label: string; url: string | null };

export type BriefItem = {
    title: string;
    detail: string;
    severity: 'high' | 'medium' | 'low';
    links: BriefLink[];
};

export type OperationsBrief = {
    id: string;
    status: 'pending' | 'completed' | 'failed';
    headline: string | null;
    items: BriefItem[];
    actions: { label: string; url: string }[];
    used_fallback: boolean;
    error: string | null;
    created_at: string | null;
    completed_at: string | null;
};
