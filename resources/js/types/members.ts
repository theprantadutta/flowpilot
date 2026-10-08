import type { RoleValue } from './organization';

export type MemberRow = {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    role: RoleValue;
    role_label: string;
    status: 'active' | 'suspended';
    department: string | null;
    job_title: string | null;
    joined_at: string | null;
    last_active_at: string | null;
    is_owner: boolean;
    is_you: boolean;
    can: { update: boolean; delete: boolean };
};

export type PendingInvitation = {
    id: string;
    email: string;
    role: RoleValue;
    role_label: string;
    department: string | null;
    invited_by: string | null;
    sent_at: string | null;
    expires_at: string;
    is_expired: boolean;
};

export type RoleOption = {
    value: RoleValue;
    label: string;
    description: string;
    assignable: boolean;
};
