<script setup lang="ts">
import { computed } from 'vue';
import NamedIcon from '@/components/NamedIcon.vue';
import {
    asArray,
    asString,
    branchCases,
    describeNode,
    describePicks,
    describeRule,
    manualInputs,
    ruleSet,
    toneChip,
} from '@/lib/workflows';
import { cn } from '@/lib/utils';
import type {
    BuilderCatalog,
    DefinitionNode,
    WorkflowField,
} from '@/types/workflows';

/**
 * A read-only description of a step, for people who can look but not edit,
 * and for the builder's inspection mode on small screens.
 */
const props = defineProps<{
    node: DefinitionNode;
    catalog: BuilderCatalog;
    fields: WorkflowField[];
    trigger: string;
}>();

const typeOption = computed(() =>
    props.catalog.nodeTypes.find((option) => option.value === props.node.type),
);
const isTrigger = computed(() => props.node.type === 'trigger');
const config = computed(() => props.node.data.config);

const lines = computed<{ label: string; value: string }[]>(() => {
    const c = config.value;
    const list: { label: string; value: string }[] = [];

    switch (props.node.type) {
        case 'trigger':
            manualInputs(c).forEach((input) =>
                list.push({
                    label: input.label,
                    value: `${props.catalog.inputTypes.find((type) => type.value === input.type)?.label ?? input.type}${input.required ? ', required' : ''}`,
                }),
            );
            break;
        case 'condition': {
            const set = ruleSet(c);
            set.rules.forEach((rule, index) =>
                list.push({
                    label:
                        index === 0
                            ? 'When'
                            : set.match === 'all'
                              ? 'And'
                              : 'Or',
                    value: describeRule(rule, props.catalog, props.fields),
                }),
            );
            break;
        }
        case 'branch':
            branchCases(c).forEach((item) =>
                list.push({
                    label: item.label,
                    value:
                        item.rules
                            .map((rule) =>
                                describeRule(rule, props.catalog, props.fields),
                            )
                            .join(item.match === 'all' ? ' and ' : ' or ') ||
                        'No rules',
                }),
            );
            break;
        case 'notification':
            list.push({
                label: 'To',
                value: describePicks(c.recipients, props.catalog, props.fields),
            });
            list.push({ label: 'Title', value: asString(c.title) });
            if (asString(c.message)) {
                list.push({ label: 'Message', value: asString(c.message) });
            }
            break;
        case 'action':
            if (c.action === 'add_tag') {
                list.push({ label: 'Tag', value: asString(c.tag) });
            } else {
                list.push({
                    label: 'To',
                    value: describePicks(
                        c.recipients,
                        props.catalog,
                        props.fields,
                    ),
                });
                list.push({ label: 'Subject', value: asString(c.subject) });
            }
            break;
        case 'create_record':
            list.push({ label: 'Title', value: asString(c.title) });
            if (c.due_in_days !== null && c.due_in_days !== undefined) {
                list.push({
                    label: 'Due',
                    value: `${c.due_in_days} days after the step runs`,
                });
            }
            break;
        case 'approval':
            list.push({ label: 'Asks', value: asString(c.title) });
            list.push({
                label: 'Approver',
                value: describePicks(
                    c.approver ? [c.approver] : [],
                    props.catalog,
                    props.fields,
                ),
            });
            if (c.due_in_hours !== null && c.due_in_hours !== undefined) {
                list.push({
                    label: 'Due',
                    value: `${c.due_in_hours} hours after asking, then ${c.when_overdue === 'reject' ? 'rejected' : 'a reminder'}`,
                });
            }
            break;
        case 'webhook':
            list.push({
                label: 'Address',
                value: asString(c.url) || 'Not set',
            });
            asArray<{ key: string; value: string }>(c.fields).forEach((field) =>
                list.push({ label: field.key, value: field.value }),
            );
            break;
    }

    return list.filter((line) => line.value !== '');
});
</script>

<template>
    <div class="grid gap-4">
        <div class="flex items-start gap-3">
            <span
                :class="
                    cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        toneChip[
                            isTrigger ? 'flow' : (typeOption?.tone ?? 'neutral')
                        ],
                    )
                "
            >
                <NamedIcon
                    :name="isTrigger ? 'zap' : (typeOption?.icon ?? 'circle')"
                    class="size-4.5"
                />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium text-muted-foreground">
                    {{ isTrigger ? 'Trigger' : typeOption?.label }}
                </p>
                <p class="font-semibold">
                    {{ node.data.label || typeOption?.label }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ describeNode(node, catalog, fields, trigger) }}
                </p>
            </div>
        </div>
        <dl v-if="lines.length" class="grid gap-2 text-sm">
            <div
                v-for="(line, index) in lines"
                :key="index"
                class="grid gap-0.5 rounded-lg bg-secondary/50 px-3 py-2"
            >
                <dt class="text-xs font-medium text-muted-foreground">
                    {{ line.label }}
                </dt>
                <dd class="break-words whitespace-pre-line">
                    {{ line.value }}
                </dd>
            </div>
        </dl>
    </div>
</template>
