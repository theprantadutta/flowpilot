export type Permission =
    | 'dashboard.view'
    | 'projects.view'
    | 'projects.create'
    | 'projects.update'
    | 'projects.delete'
    | 'tasks.view'
    | 'tasks.create'
    | 'tasks.update'
    | 'tasks.delete'
    | 'issues.view'
    | 'issues.create'
    | 'issues.update'
    | 'issues.delete'
    | 'workflows.view'
    | 'workflows.create'
    | 'workflows.publish'
    | 'workflows.execute'
    | 'workflows.delete'
    | 'approvals.view'
    | 'approvals.approve'
    | 'approvals.reject'
    | 'approvals.override'
    | 'approvals.request'
    | 'inventory.view'
    | 'inventory.manage'
    | 'inventory.request'
    | 'reports.view'
    | 'reports.export'
    | 'members.view'
    | 'members.invite'
    | 'members.manage'
    | 'settings.manage'
    | 'billing.manage'
    | 'audit.view'
    | 'ai.use';

export type RoleValue =
    | 'owner'
    | 'admin'
    | 'manager'
    | 'finance'
    | 'procurement'
    | 'operations'
    | 'employee'
    | 'auditor';

/** The organization the current page belongs to, with the viewer's access to it. */
export type CurrentOrganization = {
    id: string;
    name: string;
    slug: string;
    logo: string | null;
    timezone: string;
    currency: string;
    date_format: string;
    role: RoleValue;
    role_label: string;
    permissions: Permission[];
    plan: OrganizationPlan;
};

/** Parts of FlowPilot that depend on the plan. */
export type PlanFeature =
    | 'approvals'
    | 'all_reports'
    | 'report_exports'
    | 'ai_insights'
    | 'webhooks';

/** The plan in force now. Only decides what to show; the server checks every action. */
export type OrganizationPlan = {
    value: 'free' | 'starter' | 'business' | 'enterprise';
    label: string;
    status: 'trialing' | 'active' | 'past_due' | 'canceled';
    trial_days_left: number | null;
    features: PlanFeature[];
};

/** An organization the signed-in user can switch to. */
export type OrganizationSummary = {
    id: string;
    name: string;
    slug: string;
    logo: string | null;
    role_label: string;
};

export type Option<T extends string = string> = {
    value: T;
    label: string;
    description?: string;
};
