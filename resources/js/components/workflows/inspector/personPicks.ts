import type {
    BuilderCatalog,
    PersonPick,
    WorkflowField,
} from '@/types/workflows';

/**
 * People choices for selects, encoded as strings ("member:12", "role:finance",
 * "starter", "assignee", "field:input.manager").
 */
export type PickGroup = {
    label: string;
    options: { value: string; label: string }[];
};

export function encodePick(pick: PersonPick | null | undefined): string {
    if (!pick) {
        return '';
    }

    switch (pick.type) {
        case 'member':
            return `member:${pick.id}`;
        case 'role':
            return `role:${pick.role}`;
        case 'field':
            return `field:${pick.field}`;
        default:
            return pick.type;
    }
}

export function decodePick(value: string): PersonPick | null {
    if (value.startsWith('member:')) {
        return { type: 'member', id: Number(value.slice(7)) };
    }

    if (value.startsWith('role:')) {
        return { type: 'role', role: value.slice(5) };
    }

    if (value.startsWith('field:')) {
        return { type: 'field', field: value.slice(6) };
    }

    if (value === 'starter' || value === 'assignee') {
        return { type: value };
    }

    return null;
}

export function pickGroups(
    catalog: BuilderCatalog,
    fields: WorkflowField[],
    hasSubject: boolean,
    roleLabel: (role: string) => string = (role) => `Everyone in ${role}`,
): PickGroup[] {
    const people = fields.filter((field) => field.type === 'person');

    return [
        {
            label: 'From the run',
            options: [
                { value: 'starter', label: 'Whoever started it' },
                ...(hasSubject
                    ? [{ value: 'assignee', label: 'The record’s assignee' }]
                    : []),
                ...people.map((field) => ({
                    value: `field:${field.path}`,
                    label: field.label,
                })),
            ],
        },
        {
            label: 'Roles',
            options: catalog.roles.map((role) => ({
                value: `role:${role.value}`,
                label: roleLabel(role.label),
            })),
        },
        {
            label: 'People',
            options: catalog.members.map((member) => ({
                value: `member:${member.id}`,
                label: member.name,
            })),
        },
    ].filter((group) => group.options.length > 0);
}

export function pickLabel(value: string, groups: PickGroup[]): string {
    for (const group of groups) {
        const option = group.options.find((item) => item.value === value);

        if (option) {
            return option.label;
        }
    }

    return 'Someone no longer available';
}
