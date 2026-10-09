import type { EnumOption } from './operations';

/** How much of one plan limit an organization uses. */
export type UsageLine = {
    key: string;
    label: string;
    used: number;
    limit: number | null;
    unit: 'count' | 'mb';
};

/** An organization's subscription and usage, as BillingSummary sends it. */
export type BillingSummary = {
    plan: { value: string; label: string; price: string | null };
    subscribed_plan: { value: string; label: string } | null;
    status: EnumOption | null;
    on_trial: boolean;
    trial_ends_at: string | null;
    trial_days_left: number | null;
    current_period_end: string | null;
    has_custom_limits: boolean;
    usage: UsageLine[];
};
