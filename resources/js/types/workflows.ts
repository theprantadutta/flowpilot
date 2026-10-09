import type {
    EnumOption,
    MemberOption,
    PersonSummary,
    ProjectOption,
} from './operations';
import type { Tone } from './ui';

/** A value-label pair for selects. */
export type Option = { value: string; label: string };

export type FieldType =
    | 'text'
    | 'number'
    | 'money'
    | 'date'
    | 'select'
    | 'boolean'
    | 'person';

/** Something a condition can test or a message can include. */
export type WorkflowField = {
    path: string;
    label: string;
    type: FieldType;
    options: Option[];
};

export type TriggerOption = {
    value: string;
    label: string;
    description: string;
    subject: 'task' | 'issue' | null;
    fields: WorkflowField[];
};

export type NodeTypeOption = {
    value: NodeKind;
    label: string;
    description: string;
    icon: string;
    tone: Tone;
    handles: { id: string; label: string }[];
    addable: boolean;
};

export type NodeKind =
    | 'trigger'
    | 'condition'
    | 'branch'
    | 'approval'
    | 'action'
    | 'notification'
    | 'delay'
    | 'create_record'
    | 'update_record'
    | 'assign'
    | 'webhook'
    | 'end';

/** Who a step is for: a member, a role, the starter, the assignee or a person field. */
export type PersonPick =
    | { type: 'member'; id: number }
    | { type: 'role'; role: string }
    | { type: 'starter' }
    | { type: 'assignee' }
    | { type: 'field'; field: string };

export type ConditionRule = {
    field: string;
    operator: string;
    value: string | null;
};

export type RuleSet = { match: 'all' | 'any'; rules: ConditionRule[] };

export type BranchCase = RuleSet & { id: string; label: string };

export type ManualInput = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    options: Option[];
};

/** Step configuration is free-form data validated on the server. */
export type NodeConfig = Record<string, unknown>;

export type WorkflowNodeData = { label: string; config: NodeConfig };

export type DefinitionNode = {
    id: string;
    type: NodeKind;
    position: { x: number; y: number };
    data: WorkflowNodeData;
};

export type DefinitionEdge = {
    id: string;
    source: string;
    target: string;
    sourceHandle: string;
};

export type WorkflowDraft = {
    nodes: DefinitionNode[];
    edges: DefinitionEdge[];
    trigger: string;
};

export type ValidationIssue = { node: string | null; message: string };

export type WorkflowSummary = {
    id: string;
    name: string;
    description: string | null;
    status: EnumOption;
    trigger: { value: string; label: string };
    template: string | null;
    version?: number | null;
    has_unpublished_changes?: boolean;
    published_at: string | null;
    updated_at: string | null;
    editor?: PersonSummary | null;
    runs_count: number | null;
    failed_runs_count: number | null;
    active_runs_count: number | null;
    last_run_at: string | null;
};

export type WorkflowTemplateOption = {
    key: string;
    name: string;
    description: string;
    category: string;
    trigger: string;
    steps: number;
};

export type WorkflowVersionItem = {
    id: string;
    version: number;
    notes: string | null;
    trigger: string;
    steps: number;
    published_at: string;
    publisher: string | null;
    is_current: boolean;
};

export type VariableOption = { path: string; label: string };

export type BuilderCatalog = {
    nodeTypes: NodeTypeOption[];
    triggers: TriggerOption[];
    operators: { value: string; label: string; needs_value: boolean }[];
    operatorsByType: Record<FieldType, string[]>;
    inputTypes: { value: FieldType; label: string }[];
    maxInputs: number;
    roles: Option[];
    members: MemberOption[];
    projects: ProjectOption[];
    priorities: Option[];
    severities: Option[];
    updateFields: Record<
        'task' | 'issue',
        { value: string; label: string; options: Option[] | null }[]
    >;
    actions: Option[];
    delayUnits: Option[];
    variables: {
        common: VariableOption[];
        subject: VariableOption[];
        steps: Record<string, { key: string; label: string }[]>;
    };
};

export type WorkflowRunItem = {
    id: string;
    reference: string;
    status: EnumOption;
    is_finished: boolean;
    workflow?: { id: string; name: string };
    version?: number;
    trigger: { value: string; label: string };
    subject: { type: string; label: string | null; url: string | null } | null;
    starter?: PersonSummary | null;
    error: string | null;
    created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    cancelled_at: string | null;
    duration_seconds: number | null;
};

export type WorkflowStepItem = {
    id: string;
    sequence: number;
    node_id: string;
    type: { value: NodeKind; label: string; icon: string; tone: Tone };
    label: string;
    status: EnumOption;
    outcome: string | null;
    outcome_label: string | null;
    input: NodeConfig | null;
    output: Record<string, unknown> | null;
    error: string | null;
    attempts: number;
    resume_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    delivery: {
        url: string;
        status: string;
        response_status: number | null;
        response_body: string | null;
        attempts: number;
        duration_ms: number | null;
        delivery_key: string;
    } | null;
};
