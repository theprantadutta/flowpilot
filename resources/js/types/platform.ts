import type { ActivityEntry } from './activity';
import type { EnumOption } from './operations';

/** Platform administration: the FlowPilot team's view across organizations. */

export type PlanChoice = { value: string; label: string };

export type PlatformOrganizationRow = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    owner: { name: string; email: string };
    members: number;
    plan: {
        label: string;
        status: EnumOption;
        trial_days_left: number | null;
    } | null;
    created_at: string | null;
};

export type PlatformPlanRequest = {
    id: string;
    organization: { name: string; slug: string };
    from: string;
    to: string;
    requester: string | null;
    message: string | null;
    created_at: string | null;
};

export type PlatformMember = {
    id: string;
    name: string;
    email: string;
    role: string;
    status: 'active' | 'suspended';
    two_factor: boolean;
    last_active_at: string | null;
};

export type PlatformOrganizationRequest = {
    id: string;
    from: string;
    to: PlanChoice;
    status: 'pending' | 'approved' | 'declined' | 'withdrawn';
    message: string | null;
    requester: string | null;
    decision_note: string | null;
    created_at: string | null;
};

export type PlatformUserRow = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    two_factor: boolean;
    is_platform_admin: boolean;
    organizations: {
        name: string;
        slug: string;
        role: string;
        status: 'active' | 'suspended';
    }[];
    last_active_at: string | null;
    created_at: string | null;
};

export type HealthStatus = 'ok' | 'warning' | 'failing';

export type HealthCheck = {
    key: string;
    label: string;
    status: HealthStatus;
    summary: string;
    detail: string | null;
};

export type FailedJob = {
    id: string;
    job: string;
    connection: string;
    queue: string;
    error: string;
    failed_at: string | null;
};

export type PlatformAuditEntry = ActivityEntry & {
    organization: { name: string; slug: string } | null;
    staff: string | null;
    ip_address: string | null;
};
