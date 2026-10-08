<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    BuilderCatalog,
    ConditionRule,
    RuleSet,
    WorkflowField,
} from '@/types/workflows';
import RuleValueInput from './RuleValueInput.vue';

/**
 * A list of rules ("Amount is greater than 5,000") and whether all or any
 * of them must match. Comparisons are limited to what suits each field.
 */
const props = withDefaults(
    defineProps<{
        catalog: BuilderCatalog;
        fields: WorkflowField[];
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const model = defineModel<RuleSet>({ required: true });

const match = computed({
    get: () => model.value.match,
    set: (value: 'all' | 'any') =>
        (model.value = { ...model.value, match: value }),
});

function fieldFor(rule: ConditionRule): WorkflowField | undefined {
    return props.fields.find((field) => field.path === rule.field);
}

function operatorsFor(rule: ConditionRule) {
    const field = fieldFor(rule);
    const allowed = field ? props.catalog.operatorsByType[field.type] : [];

    return props.catalog.operators.filter((operator) =>
        allowed.includes(operator.value),
    );
}

function needsValue(rule: ConditionRule): boolean {
    return (
        props.catalog.operators.find(
            (operator) => operator.value === rule.operator,
        )?.needs_value ?? true
    );
}

function update(index: number, changes: Partial<ConditionRule>) {
    const rules = model.value.rules.map((rule, position) => {
        if (position !== index) {
            return rule;
        }

        const next = { ...rule, ...changes };

        // A new field may not support the old comparison or value.
        if (changes.field !== undefined && changes.field !== rule.field) {
            const allowed = operatorsFor(next).map(
                (operator) => operator.value,
            );
            next.operator = allowed.includes(next.operator)
                ? next.operator
                : (allowed[0] ?? 'equals');
            next.value = null;
        }

        if (!needsValue(next)) {
            next.value = null;
        }

        return next;
    });

    model.value = { ...model.value, rules };
}

function add() {
    const field = props.fields[0];
    const operator = field
        ? (props.catalog.operatorsByType[field.type][0] ?? 'equals')
        : 'equals';

    model.value = {
        ...model.value,
        rules: [
            ...model.value.rules,
            { field: field?.path ?? '', operator, value: null },
        ],
    };
}

function remove(index: number) {
    model.value = {
        ...model.value,
        rules: model.value.rules.filter((_, position) => position !== index),
    };
}
</script>

<template>
    <div class="grid gap-3">
        <div
            v-if="model.rules.length > 1"
            class="flex items-center gap-2 text-sm"
        >
            <span>Continue when</span>
            <Select v-model="match" :disabled="disabled">
                <SelectTrigger class="h-8 w-24" aria-label="How rules combine"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">all</SelectItem>
                    <SelectItem value="any">any</SelectItem>
                </SelectContent>
            </Select>
            <span>of these match</span>
        </div>

        <ol class="grid gap-3">
            <li
                v-for="(rule, index) in model.rules"
                :key="index"
                class="grid gap-2 rounded-lg border bg-secondary/40 p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-muted-foreground"
                        >Rule {{ index + 1 }}</span
                    >
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 text-muted-foreground"
                        :aria-label="`Remove rule ${index + 1}`"
                        @click="remove(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <Select
                    :model-value="rule.field"
                    :disabled="disabled"
                    @update:model-value="
                        (value) => update(index, { field: String(value) })
                    "
                >
                    <SelectTrigger
                        class="w-full"
                        :aria-label="`Field for rule ${index + 1}`"
                    >
                        <SelectValue placeholder="Choose a field" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="field in fields"
                            :key="field.path"
                            :value="field.path"
                        >
                            {{ field.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="rule.operator"
                    :disabled="disabled || !fieldFor(rule)"
                    @update:model-value="
                        (value) => update(index, { operator: String(value) })
                    "
                >
                    <SelectTrigger
                        class="w-full"
                        :aria-label="`Comparison for rule ${index + 1}`"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="operator in operatorsFor(rule)"
                            :key="operator.value"
                            :value="operator.value"
                        >
                            {{ operator.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <RuleValueInput
                    v-if="needsValue(rule)"
                    :model-value="rule.value"
                    :field="fieldFor(rule)"
                    :members="catalog.members"
                    :label="`Value for rule ${index + 1}`"
                    :disabled="disabled"
                    @update:model-value="(value) => update(index, { value })"
                />
            </li>
        </ol>

        <p v-if="!fields.length" class="text-sm text-muted-foreground">
            This trigger has nothing to compare yet. Add details to the trigger
            first.
        </p>
        <Button
            v-else-if="!disabled"
            type="button"
            variant="outline"
            size="sm"
            class="justify-self-start"
            @click="add"
        >
            <Plus />
            Add a rule
        </Button>
    </div>
</template>
