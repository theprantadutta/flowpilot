import type {
    BranchCase,
    BuilderCatalog,
    ConditionRule,
    DefinitionNode,
    ManualInput,
    NodeConfig,
    NodeKind,
    PersonPick,
    RuleSet,
    VariableOption,
    WorkflowField,
} from '@/types/workflows';
import { formatNumber } from '@/lib/format';

/**
 * Helpers shared by the workflow builder and run pages: reading step
 * configuration safely, describing steps in words, and working out which
 * fields and values a step can use.
 */

/**
 * Every kind of step. Typed as a record so adding a kind without listing it
 * here fails to compile; canvases register a node component for each.
 */
const nodeKinds: Record<NodeKind, true> = {
    trigger: true,
    condition: true,
    branch: true,
    approval: true,
    action: true,
    notification: true,
    delay: true,
    create_record: true,
    update_record: true,
    assign: true,
    webhook: true,
    end: true,
};

export const NODE_KINDS = Object.keys(nodeKinds) as NodeKind[];

export function asString(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback;
}

export function asArray<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

export function ruleSet(config: NodeConfig): RuleSet {
    return {
        match: config.match === 'any' ? 'any' : 'all',
        rules: asArray<ConditionRule>(config.rules),
    };
}

export function branchCases(config: NodeConfig): BranchCase[] {
    return asArray<BranchCase>(config.cases);
}

export function manualInputs(config: NodeConfig): ManualInput[] {
    return asArray<ManualInput>(config.inputs);
}

export function picks(value: unknown): PersonPick[] {
    return asArray<PersonPick>(value);
}

/** A short random id for steps, cases and connections. */
export function shortId(prefix: string): string {
    const random =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
            ? crypto.randomUUID().replaceAll('-', '').slice(0, 8)
            : Math.random().toString(36).slice(2, 10);

    return `${prefix}_${random}`;
}

/** The configuration a newly added step starts with. */
export function defaultConfig(type: NodeKind): NodeConfig {
    switch (type) {
        case 'condition':
            return { match: 'all', rules: [] };
        case 'branch':
            return {
                cases: [
                    {
                        id: shortId('case'),
                        label: 'First case',
                        match: 'all',
                        rules: [],
                    },
                ],
            };
        case 'notification':
            return { recipients: [], title: '', message: '' };
        case 'delay':
            return { amount: 1, unit: 'days' };
        case 'create_record':
            return {
                record: 'task',
                title: '',
                description: '',
                priority: 'medium',
                assignee: null,
                project: null,
                due_in_days: null,
                tags: [],
            };
        case 'update_record':
            return { field: 'status', value: null };
        case 'assign':
            return { assignee: null };
        case 'action':
            return {
                action: 'send_email',
                recipients: [],
                subject: '',
                body: '',
            };
        case 'webhook':
            return { url: '', fields: [] };
        case 'end':
            return { summary: '' };
        case 'approval':
            return {
                title: '',
                description: '',
                approver: { type: 'role', role: 'manager' },
                amount_field: null,
                priority: 'medium',
                due_in_hours: 48,
                when_overdue: 'remind',
            };
        default:
            return {};
    }
}

/** The ids of the paths a step can leave through. */
export function handlesFor(
    type: NodeKind,
    config: NodeConfig,
    catalog: BuilderCatalog,
): { id: string; label: string }[] {
    if (type === 'trigger') {
        return [{ id: 'next', label: 'Next' }];
    }

    if (type === 'branch') {
        return [
            ...branchCases(config).map((item) => ({
                id: item.id,
                label: item.label || 'Case',
            })),
            { id: 'otherwise', label: 'Otherwise' },
        ];
    }

    return (
        catalog.nodeTypes.find((option) => option.value === type)?.handles ?? []
    );
}

/** Everything a condition can test, for the workflow's trigger. */
export function fieldsFor(
    trigger: string,
    triggerConfig: NodeConfig,
    catalog: BuilderCatalog,
): WorkflowField[] {
    const option = catalog.triggers.find((item) => item.value === trigger);

    if (trigger === 'manual') {
        return [
            ...manualInputs(triggerConfig)
                .filter((input) => input.key)
                .map((input) => ({
                    path: `input.${input.key}`,
                    label: input.label || input.key,
                    type: input.type,
                    options: input.options ?? [],
                })),
            ...(option?.fields ?? []),
        ];
    }

    return option?.fields ?? [];
}

/**
 * Values a message can include: the trigger's fields, values every run has,
 * the record's details, and outputs of steps that come before this one.
 */
export function variablesFor(
    trigger: string,
    triggerConfig: NodeConfig,
    catalog: BuilderCatalog,
    upstream: DefinitionNode[],
): VariableOption[] {
    const subject = catalog.triggers.find(
        (item) => item.value === trigger,
    )?.subject;
    const stepVariables = upstream.flatMap((node) =>
        (catalog.variables.steps[node.type] ?? []).map((variable) => ({
            path: `steps.${node.id}.${variable.key}`,
            label: `${node.data.label || node.type}: ${variable.label}`,
        })),
    );

    return [
        ...fieldsFor(trigger, triggerConfig, catalog).map((field) => ({
            path: field.path,
            label: field.label,
        })),
        ...catalog.variables.common,
        ...(subject ? catalog.variables.subject : []),
        ...(subject === 'task' || subject === 'issue'
            ? catalog.variables.work
            : []),
        ...stepVariables,
    ];
}

function personLabel(
    pick: PersonPick,
    catalog: BuilderCatalog,
    fields: WorkflowField[],
): string {
    switch (pick.type) {
        case 'member':
            return (
                catalog.members.find((member) => member.id === pick.id)?.name ??
                'a former member'
            );
        case 'role':
            return `everyone in ${catalog.roles.find((role) => role.value === pick.role)?.label ?? pick.role}`;
        case 'starter':
            return 'whoever started it';
        case 'assignee':
            return 'the assignee';
        case 'field':
            return (
                fields.find((field) => field.path === pick.field)?.label ??
                'a chosen person'
            );
    }
}

export function describePicks(
    value: unknown,
    catalog: BuilderCatalog,
    fields: WorkflowField[],
): string {
    const list = picks(value);

    if (list.length === 0) {
        return 'Nobody chosen yet';
    }

    const labels = list.map((pick) => personLabel(pick, catalog, fields));

    return labels.length > 2
        ? `${labels.slice(0, 2).join(', ')} and ${labels.length - 2} more`
        : labels.join(' and ');
}

export function describeRule(
    rule: ConditionRule,
    catalog: BuilderCatalog,
    fields: WorkflowField[],
): string {
    const field = fields.find((item) => item.path === rule.field);
    const operator = catalog.operators.find(
        (item) => item.value === rule.operator,
    );

    if (!field || !operator) {
        return 'Incomplete rule';
    }

    if (!operator.needs_value) {
        return `${field.label} ${operator.label}`;
    }

    let value = rule.value ?? '';

    if (field.type === 'select') {
        value =
            field.options.find((option) => option.value === value)?.label ??
            value;
    } else if (field.type === 'person') {
        value =
            catalog.members.find((member) => String(member.id) === value)
                ?.name ?? value;
    } else if (field.type === 'boolean') {
        value = value === 'true' ? 'yes' : 'no';
    } else if (field.type === 'money' || field.type === 'number') {
        const amount = Number(value.replaceAll(',', ''));

        if (value !== '' && Number.isFinite(amount)) {
            value = formatNumber(amount, {
                maximumFractionDigits: 2,
            });
        }
    }

    return `${field.label} ${operator.label} ${value}`;
}

/** One line under a step's name on the canvas, saying what it does. */
export function describeNode(
    node: DefinitionNode,
    catalog: BuilderCatalog,
    fields: WorkflowField[],
    trigger: string,
): string {
    const config = node.data.config;

    switch (node.type) {
        case 'trigger': {
            const label =
                catalog.triggers.find((item) => item.value === trigger)
                    ?.label ?? 'Trigger';
            const inputs = manualInputs(config).length;

            return trigger === 'manual' && inputs > 0
                ? `${label}, asks for ${inputs} ${inputs === 1 ? 'detail' : 'details'}`
                : label;
        }
        case 'condition': {
            const { rules, match } = ruleSet(config);

            if (rules.length === 0) {
                return 'No rules yet';
            }

            const first = describeRule(rules[0], catalog, fields);

            return rules.length > 1
                ? `${first}, ${match === 'all' ? 'and' : 'or'} ${rules.length - 1} more`
                : first;
        }
        case 'branch': {
            const cases = branchCases(config).length;

            return `${cases} ${cases === 1 ? 'case' : 'cases'}, then otherwise`;
        }
        case 'notification':
            return `Notify ${describePicks(config.recipients, catalog, fields)}`;
        case 'delay': {
            const amount = Number(config.amount ?? 0);
            const unit = asString(config.unit, 'days');

            return `Wait ${amount} ${amount === 1 ? unit.replace(/s$/, '') : unit}`;
        }
        case 'create_record':
            return config.record === 'purchase_request'
                ? `Reorder ${config.quantity ? `${Number(config.quantity)}` : 'the reorder quantity'}`
                : `New ${asString(config.record, 'task')}: ${asString(config.title) || 'untitled'}`;
        case 'update_record': {
            const field = asString(config.field);

            return field
                ? `Change ${field.replaceAll('_', ' ')}`
                : 'Choose a field';
        }
        case 'assign': {
            const pick = config.assignee as PersonPick | null;

            if (!pick) {
                return 'Choose who';
            }

            return pick.type === 'role'
                ? `Least busy in ${catalog.roles.find((role) => role.value === pick.role)?.label ?? pick.role}`
                : `To ${personLabel(pick, catalog, fields)}`;
        }
        case 'action':
            return config.action === 'add_tag'
                ? `Tag with “${asString(config.tag) || '…'}”`
                : `Email ${describePicks(config.recipients, catalog, fields)}`;
        case 'webhook': {
            const url = asString(config.url);

            try {
                return url ? `POST to ${new URL(url).host}` : 'No address yet';
            } catch {
                return 'Address needs fixing';
            }
        }
        case 'end':
            return asString(config.summary) || 'Run finishes';
        case 'approval': {
            const pick = config.approver as PersonPick | null;

            if (!pick) {
                return 'Choose who approves';
            }

            return pick.type === 'role'
                ? `Anyone in ${catalog.roles.find((role) => role.value === pick.role)?.label ?? pick.role} decides`
                : `${personLabel(pick, catalog, fields)} decides`;
        }
    }
}

/** Seconds as "1h 4m", "2m 5s" or "12s". */
export function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
    }

    return `${Math.floor(seconds / 86400)}d ${Math.floor((seconds % 86400) / 3600)}h`;
}

/** Tone classes for the step icon chips on the canvas and in run timelines. */
export const toneChip: Record<string, string> = {
    neutral: 'bg-neutral-soft text-neutral-text',
    info: 'bg-info-soft text-info-text',
    success: 'bg-success-soft text-success-text',
    warning: 'bg-warning-soft text-warning-text',
    danger: 'bg-danger-soft text-danger-text',
    flow: 'bg-flow-soft text-flow-text',
    ai: 'bg-ai-soft text-ai-text',
};
