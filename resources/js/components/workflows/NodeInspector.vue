<script setup lang="ts">
import { Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import FormField from '@/components/FormField.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import TagInput from '@/components/TagInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    asArray,
    asString,
    branchCases,
    manualInputs,
    picks,
    ruleSet,
    shortId,
    toneChip,
} from '@/lib/workflows';
import { cn } from '@/lib/utils';
import type {
    BranchCase,
    BuilderCatalog,
    ManualInput,
    NodeConfig,
    NodeKind,
    PersonPick,
    RuleSet,
    VariableOption,
    WorkflowField,
} from '@/types/workflows';
import ManualInputsEditor from './inspector/ManualInputsEditor.vue';
import PeoplePicks from './inspector/PeoplePicks.vue';
import PersonPickSelect from './inspector/PersonPickSelect.vue';
import RuleSetEditor from './inspector/RuleSetEditor.vue';
import TemplateField from './inspector/TemplateField.vue';

/**
 * The settings of the selected step. Every change is applied straight to the
 * canvas; the builder saves the draft shortly after.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        type: NodeKind;
        label: string;
        config: NodeConfig;
        catalog: BuilderCatalog;
        trigger: string;
        fields: WorkflowField[];
        variables: VariableOption[];
        issues: string[];
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    'update:label': [label: string];
    'update:config': [config: NodeConfig];
    'update:trigger': [trigger: string];
    'rename-input': [from: string, to: string];
    remove: [];
}>();

const typeOption = computed(() =>
    props.catalog.nodeTypes.find((option) => option.value === props.type),
);
const triggerOption = computed(() =>
    props.catalog.triggers.find((option) => option.value === props.trigger),
);
const subject = computed(() => triggerOption.value?.subject ?? null);
const hasSubject = computed(() => subject.value !== null);
const icon = computed(() =>
    props.type === 'trigger' ? 'zap' : (typeOption.value?.icon ?? 'circle'),
);
const tone = computed(() =>
    props.type === 'trigger' ? 'flow' : (typeOption.value?.tone ?? 'neutral'),
);

function set(key: string, value: unknown) {
    emit('update:config', { ...props.config, [key]: value });
}

function text(key: string): string {
    return asString(props.config[key]);
}

const labelModel = computed({
    get: () => props.label,
    set: (value: string) => emit('update:label', value),
});

const rules = computed({
    get: () => ruleSet(props.config),
    set: (value: RuleSet) =>
        emit('update:config', { ...props.config, ...value }),
});

const inputs = computed({
    get: () => manualInputs(props.config),
    set: (value: ManualInput[]) => set('inputs', value),
});

const cases = computed(() => branchCases(props.config));

function updateCase(index: number, changes: Partial<BranchCase>) {
    set(
        'cases',
        cases.value.map((item, position) =>
            position === index ? { ...item, ...changes } : item,
        ),
    );
}

function addCase() {
    set('cases', [
        ...cases.value,
        {
            id: shortId('case'),
            label: `Case ${cases.value.length + 1}`,
            match: 'all',
            rules: [],
        },
    ]);
}

function removeCase(index: number) {
    set(
        'cases',
        cases.value.filter((_, position) => position !== index),
    );
}

const recipients = computed({
    get: () => picks(props.config.recipients),
    set: (value: PersonPick[]) => set('recipients', value),
});

const assignee = computed({
    get: () => (props.config.assignee as PersonPick | null | undefined) ?? null,
    set: (value: PersonPick | null) => set('assignee', value),
});

const updateFields = computed(() =>
    subject.value ? props.catalog.updateFields[subject.value] : [],
);
const updateField = computed(() =>
    updateFields.value.find((field) => field.value === props.config.field),
);

const project = computed({
    get: () =>
        typeof props.config.project === 'string'
            ? props.config.project
            : 'none',
    set: (value: string) => set('project', value === 'none' ? null : value),
});

const webhookFields = computed(() =>
    asArray<{ key: string; value: string }>(props.config.fields),
);

function updateWebhookField(
    index: number,
    changes: Partial<{ key: string; value: string }>,
) {
    set(
        'fields',
        webhookFields.value.map((field, position) =>
            position === index ? { ...field, ...changes } : field,
        ),
    );
}

const webhookPlaceholder = 'https:' + '//hooks.example.com/flowpilot';

function changeTrigger(value: unknown) {
    if (typeof value === 'string' && value !== props.trigger) {
        emit('update:trigger', value);
    }
}
</script>

<template>
    <div class="grid gap-5">
        <div class="flex items-start gap-3">
            <span
                :class="
                    cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        toneChip[tone],
                    )
                "
            >
                <NamedIcon :name="icon" class="size-4.5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ type === 'trigger' ? 'Trigger' : typeOption?.label }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        type === 'trigger'
                            ? 'What starts the workflow.'
                            : typeOption?.description
                    }}
                </p>
            </div>
            <Button
                v-if="type !== 'trigger' && !disabled"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 shrink-0 text-muted-foreground hover:text-danger-text"
                aria-label="Remove this step"
                title="Remove this step"
                @click="emit('remove')"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <div
            v-if="issues.length"
            role="status"
            class="grid gap-1.5 rounded-lg border border-warning/40 bg-warning-soft/60 p-3 text-sm text-warning-text"
        >
            <p class="flex items-center gap-1.5 font-medium">
                <TriangleAlert class="size-4" aria-hidden="true" />
                Fix before publishing
            </p>
            <ul class="list-disc space-y-0.5 pl-5 text-xs">
                <li v-for="issue in issues" :key="issue">{{ issue }}</li>
            </ul>
        </div>

        <FormField v-slot="field" label="Name on the canvas">
            <Input
                v-bind="field"
                v-model="labelModel"
                maxlength="120"
                :disabled="disabled"
            />
        </FormField>

        <!-- Trigger -->
        <template v-if="type === 'trigger'">
            <FormField
                v-slot="field"
                label="Starts when"
                :help="triggerOption?.description"
            >
                <Select
                    :model-value="trigger"
                    :disabled="disabled"
                    @update:model-value="changeTrigger"
                >
                    <SelectTrigger v-bind="field" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in catalog.triggers"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <div v-if="trigger === 'manual'" class="grid gap-2">
                <p class="text-sm font-medium">Details to fill in</p>
                <ManualInputsEditor
                    v-model="inputs"
                    :types="catalog.inputTypes"
                    :max="catalog.maxInputs"
                    :disabled="disabled"
                    @rename="(from, to) => emit('rename-input', from, to)"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Runs start on their own, and can read the {{ subject }}’s
                details:
                {{
                    fields
                        .slice(0, 4)
                        .map((field) => field.label.toLowerCase())
                        .join(', ')
                }}
                and more.
            </p>
        </template>

        <!-- Condition -->
        <template v-else-if="type === 'condition'">
            <div class="grid gap-2">
                <p class="text-sm font-medium">Rules</p>
                <RuleSetEditor
                    v-model="rules"
                    :catalog="catalog"
                    :fields="fields"
                    :disabled="disabled"
                />
            </div>
            <p class="text-xs text-muted-foreground">
                Runs continue down “Yes” when the rules match and “No” when they
                do not.
            </p>
        </template>

        <!-- Branch -->
        <template v-else-if="type === 'branch'">
            <p class="text-xs text-muted-foreground">
                Cases are checked in order. The first that matches is taken; if
                none do, the run goes down “Otherwise”.
            </p>
            <ol class="grid gap-4">
                <li
                    v-for="(item, index) in cases"
                    :key="item.id"
                    class="grid gap-3 rounded-xl border p-3"
                >
                    <div class="flex items-center gap-2">
                        <Input
                            :model-value="item.label"
                            maxlength="60"
                            :disabled="disabled"
                            :aria-label="`Name of case ${index + 1}`"
                            class="h-8 flex-1"
                            @update:model-value="
                                (value) =>
                                    updateCase(index, { label: String(value) })
                            "
                        />
                        <Button
                            v-if="!disabled && cases.length > 1"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground"
                            :aria-label="`Remove ${item.label}`"
                            @click="removeCase(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <RuleSetEditor
                        :model-value="{ match: item.match, rules: item.rules }"
                        :catalog="catalog"
                        :fields="fields"
                        :disabled="disabled"
                        @update:model-value="
                            (value) => updateCase(index, value)
                        "
                    />
                </li>
            </ol>
            <Button
                v-if="!disabled && cases.length < 10"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="addCase"
            >
                <Plus />
                Add a case
            </Button>
        </template>

        <!-- Notification -->
        <template v-else-if="type === 'notification'">
            <FormField v-slot="field" label="Notify">
                <PeoplePicks
                    :id="field.id"
                    v-model="recipients"
                    :catalog="catalog"
                    :fields="fields"
                    :has-subject="hasSubject"
                    :disabled="disabled"
                />
            </FormField>
            <FormField v-slot="field" label="Title">
                <TemplateField
                    v-bind="field"
                    :model-value="text('title')"
                    :variables="variables"
                    :maxlength="150"
                    :disabled="disabled"
                    placeholder="Large purchase request needs a look"
                    @update:model-value="(value) => set('title', value)"
                />
            </FormField>
            <FormField v-slot="field" label="Message" optional>
                <TemplateField
                    v-bind="field"
                    :model-value="text('message')"
                    :variables="variables"
                    multiline
                    :maxlength="2000"
                    :disabled="disabled"
                    @update:model-value="(value) => set('message', value)"
                />
            </FormField>
            <p class="text-xs text-muted-foreground">
                Shows in their notification center, and is emailed if their
                preferences say so.
            </p>
        </template>

        <!-- Delay -->
        <template v-else-if="type === 'delay'">
            <div class="grid grid-cols-[1fr_1.4fr] gap-3">
                <FormField v-slot="field" label="Wait for">
                    <Input
                        v-bind="field"
                        :model-value="String(config.amount ?? '')"
                        type="number"
                        min="1"
                        inputmode="numeric"
                        class="figures"
                        :disabled="disabled"
                        @update:model-value="
                            (value) => set('amount', Number(value) || 0)
                        "
                    />
                </FormField>
                <FormField v-slot="field" label="Unit">
                    <Select
                        :model-value="text('unit') || 'days'"
                        :disabled="disabled"
                        @update:model-value="(value) => set('unit', value)"
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="unit in catalog.delayUnits"
                                :key="unit.value"
                                :value="unit.value"
                                >{{ unit.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>
            </div>
            <p class="text-xs text-muted-foreground">
                The run waits, then carries on within a minute of the time
                passing. Up to 90 days.
            </p>
        </template>

        <!-- Create record -->
        <template v-else-if="type === 'create_record'">
            <FormField v-slot="field" label="Create">
                <Select
                    :model-value="text('record') || 'task'"
                    :disabled="disabled"
                    @update:model-value="(value) => set('record', value)"
                >
                    <SelectTrigger v-bind="field" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="task">A task</SelectItem>
                        <SelectItem value="issue">An issue</SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField v-slot="field" label="Title">
                <TemplateField
                    v-bind="field"
                    :model-value="text('title')"
                    :variables="variables"
                    :maxlength="200"
                    :disabled="disabled"
                    @update:model-value="(value) => set('title', value)"
                />
            </FormField>
            <FormField v-slot="field" label="Description" optional>
                <TemplateField
                    v-bind="field"
                    :model-value="text('description')"
                    :variables="variables"
                    multiline
                    :maxlength="5000"
                    :disabled="disabled"
                    @update:model-value="(value) => set('description', value)"
                />
            </FormField>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField
                    v-if="text('record') !== 'issue'"
                    v-slot="field"
                    label="Priority"
                >
                    <Select
                        :model-value="text('priority') || 'medium'"
                        :disabled="disabled"
                        @update:model-value="(value) => set('priority', value)"
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in catalog.priorities"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField v-else v-slot="field" label="Severity">
                    <Select
                        :model-value="text('severity') || 'medium'"
                        :disabled="disabled"
                        @update:model-value="(value) => set('severity', value)"
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in catalog.severities"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField v-slot="field" label="Due in days" optional>
                    <Input
                        v-bind="field"
                        :model-value="
                            config.due_in_days === null ||
                            config.due_in_days === undefined
                                ? ''
                                : String(config.due_in_days)
                        "
                        type="number"
                        min="0"
                        max="365"
                        class="figures"
                        :disabled="disabled"
                        @update:model-value="
                            (value) =>
                                set(
                                    'due_in_days',
                                    value === '' ? null : Number(value),
                                )
                        "
                    />
                </FormField>
            </div>
            <FormField v-slot="field" label="Assign to" optional>
                <PersonPickSelect
                    :id="field.id"
                    v-model="assignee"
                    :catalog="catalog"
                    :fields="fields"
                    :has-subject="hasSubject"
                    none-label="Nobody yet"
                    :disabled="disabled"
                />
            </FormField>
            <FormField v-slot="field" label="Project" optional>
                <Select v-model="project" :disabled="disabled">
                    <SelectTrigger v-bind="field" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No project</SelectItem>
                        <SelectItem v-if="hasSubject" value="subject"
                            >Same project as the {{ subject }}</SelectItem
                        >
                        <SelectItem
                            v-for="option in catalog.projects"
                            :key="option.id"
                            :value="option.id"
                            >{{ option.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </FormField>
            <FormField v-slot="field" label="Tags" optional>
                <TagInput
                    :id="field.id"
                    :model-value="asArray<string>(config.tags)"
                    @update:model-value="
                        (value: string[]) => set('tags', value)
                    "
                />
            </FormField>
        </template>

        <!-- Update record -->
        <template v-else-if="type === 'update_record'">
            <p v-if="!hasSubject" class="text-sm text-muted-foreground">
                This trigger has no record to change. Choose a trigger about a
                task or an issue.
            </p>
            <template v-else>
                <FormField v-slot="field" label="Change">
                    <Select
                        :model-value="text('field')"
                        :disabled="disabled"
                        @update:model-value="
                            (value) =>
                                emit('update:config', {
                                    ...config,
                                    field: value,
                                    value: null,
                                })
                        "
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue placeholder="Choose a field"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in updateFields"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField
                    v-if="updateField?.options"
                    v-slot="field"
                    label="To"
                >
                    <Select
                        :model-value="
                            typeof config.value === 'string' ? config.value : ''
                        "
                        :disabled="disabled"
                        @update:model-value="(value) => set('value', value)"
                    >
                        <SelectTrigger v-bind="field" class="w-full"
                            ><SelectValue placeholder="Choose"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in updateField.options"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField
                    v-else-if="updateField"
                    v-slot="field"
                    label="Days from when the step runs"
                >
                    <Input
                        v-bind="field"
                        :model-value="
                            config.value === null || config.value === undefined
                                ? ''
                                : String(config.value)
                        "
                        type="number"
                        min="0"
                        max="365"
                        class="figures"
                        :disabled="disabled"
                        @update:model-value="
                            (value) =>
                                set(
                                    'value',
                                    value === '' ? null : Number(value),
                                )
                        "
                    />
                </FormField>
            </template>
        </template>

        <!-- Assign -->
        <template v-else-if="type === 'assign'">
            <p v-if="!hasSubject" class="text-sm text-muted-foreground">
                This trigger has no record to assign. Choose a trigger about a
                task or an issue.
            </p>
            <FormField
                v-else
                v-slot="field"
                label="Assign to"
                help="A role gives the work to whoever in that role has the least open work."
            >
                <PersonPickSelect
                    :id="field.id"
                    v-model="assignee"
                    :catalog="catalog"
                    :fields="fields"
                    :has-subject="hasSubject"
                    :disabled="disabled"
                />
            </FormField>
        </template>

        <!-- Action -->
        <template v-else-if="type === 'action'">
            <FormField v-slot="field" label="Do">
                <Select
                    :model-value="text('action') || 'send_email'"
                    :disabled="disabled"
                    @update:model-value="(value) => set('action', value)"
                >
                    <SelectTrigger v-bind="field" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="action in catalog.actions"
                            :key="action.value"
                            :value="action.value"
                            >{{ action.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </FormField>
            <template v-if="text('action') === 'add_tag'">
                <FormField
                    v-slot="field"
                    label="Tag"
                    help="Lowercase, up to 30 characters. Records have at most 10 tags."
                >
                    <TemplateField
                        v-bind="field"
                        :model-value="text('tag')"
                        :variables="variables"
                        :maxlength="30"
                        :disabled="disabled"
                        placeholder="escalated"
                        @update:model-value="(value) => set('tag', value)"
                    />
                </FormField>
            </template>
            <template v-else>
                <FormField v-slot="field" label="Email">
                    <PeoplePicks
                        :id="field.id"
                        v-model="recipients"
                        :catalog="catalog"
                        :fields="fields"
                        :has-subject="hasSubject"
                        :disabled="disabled"
                    />
                </FormField>
                <FormField v-slot="field" label="Subject">
                    <TemplateField
                        v-bind="field"
                        :model-value="text('subject')"
                        :variables="variables"
                        :maxlength="150"
                        :disabled="disabled"
                        @update:model-value="(value) => set('subject', value)"
                    />
                </FormField>
                <FormField
                    v-slot="field"
                    label="Email body"
                    help="Leave a blank line between paragraphs."
                >
                    <TemplateField
                        v-bind="field"
                        :model-value="text('body')"
                        :variables="variables"
                        multiline
                        :rows="6"
                        :maxlength="5000"
                        :disabled="disabled"
                        @update:model-value="(value) => set('body', value)"
                    />
                </FormField>
            </template>
        </template>

        <!-- Webhook -->
        <template v-else-if="type === 'webhook'">
            <FormField
                v-slot="field"
                label="Send to"
                help="An https address on the public internet."
            >
                <Input
                    v-bind="field"
                    :model-value="text('url')"
                    type="url"
                    inputmode="url"
                    :placeholder="webhookPlaceholder"
                    :disabled="disabled"
                    @update:model-value="
                        (value) => set('url', String(value).trim())
                    "
                />
            </FormField>
            <div class="grid gap-2">
                <p class="text-sm font-medium">Extra values</p>
                <p class="text-xs text-muted-foreground">
                    Every delivery includes the workflow, run, record and
                    details. Add your own under
                    <code class="rounded bg-secondary px-1">data</code>.
                </p>
                <div
                    v-for="(item, index) in webhookFields"
                    :key="index"
                    class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] items-center gap-2"
                >
                    <Input
                        :model-value="item.key"
                        placeholder="name"
                        :aria-label="`Name of value ${index + 1}`"
                        maxlength="40"
                        :disabled="disabled"
                        @update:model-value="
                            (value) =>
                                updateWebhookField(index, {
                                    key: String(value),
                                })
                        "
                    />
                    <TemplateField
                        :model-value="item.value"
                        :variables="variables"
                        :aria-label="`Value ${index + 1}`"
                        :maxlength="1000"
                        :disabled="disabled"
                        @update:model-value="
                            (value) => updateWebhookField(index, { value })
                        "
                    />
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-muted-foreground"
                        :aria-label="`Remove value ${index + 1}`"
                        @click="
                            set(
                                'fields',
                                webhookFields.filter(
                                    (_, position) => position !== index,
                                ),
                            )
                        "
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <Button
                    v-if="!disabled && webhookFields.length < 20"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="justify-self-start"
                    @click="
                        set('fields', [
                            ...webhookFields,
                            { key: '', value: '' },
                        ])
                    "
                >
                    <Plus />
                    Add a value
                </Button>
            </div>
            <p
                class="rounded-lg bg-secondary/60 p-3 text-xs text-muted-foreground"
            >
                Deliveries are signed with your organization’s signing secret
                (Settings → Security) and carry an
                <code>Idempotency-Key</code> that stays the same when a delivery
                is retried.
            </p>
        </template>

        <!-- End -->
        <template v-else-if="type === 'end'">
            <FormField
                v-slot="field"
                label="Summary"
                optional
                help="Shown on the run, e.g. “Escalated to operations”."
            >
                <TemplateField
                    v-bind="field"
                    :model-value="text('summary')"
                    :variables="variables"
                    :maxlength="300"
                    :disabled="disabled"
                    @update:model-value="(value) => set('summary', value)"
                />
            </FormField>
        </template>
    </div>
</template>
