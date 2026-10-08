<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { FieldType, ManualInput } from '@/types/workflows';

/**
 * The details a person fills in when starting the workflow. Keys are made
 * from the label so conditions and messages can refer to them.
 */
const props = withDefaults(
    defineProps<{
        types: { value: FieldType; label: string }[];
        max: number;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const model = defineModel<ManualInput[]>({ required: true });

const emit = defineEmits<{
    /** A detail's key changed, so rules and messages using it can follow. */
    rename: [from: string, to: string];
}>();

function keyFrom(label: string, index: number): string {
    const base =
        label
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/^(\d)/, 'n_$1')
            .slice(0, 40) || `detail_${index + 1}`;

    const taken = model.value
        .filter((_, position) => position !== index)
        .map((input) => input.key);
    let key = base;
    let suffix = 2;

    while (taken.includes(key)) {
        key = `${base.slice(0, 37)}_${suffix++}`;
    }

    return key;
}

function update(index: number, changes: Partial<ManualInput>) {
    let renamed: [string, string] | null = null;

    model.value = model.value.map((input, position) => {
        if (position !== index) {
            return input;
        }

        const next = { ...input, ...changes };

        // Keys follow the label; steps that use the old key are updated to match.
        if (changes.label !== undefined) {
            next.key = keyFrom(changes.label, index);

            if (next.key !== input.key) {
                renamed = [input.key, next.key];
            }
        }

        if (changes.type !== undefined && changes.type !== 'select') {
            next.options = [];
        }

        return next;
    });

    if (renamed) {
        emit('rename', ...(renamed as [string, string]));
    }
}

function optionsText(input: ManualInput): string {
    return input.options.map((option) => option.label).join('\n');
}

function updateOptions(index: number, text: string) {
    const labels = text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .slice(0, 30);

    update(index, {
        options: labels.map((label) => ({
            value:
                label
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '') || label,
            label,
        })),
    });
}

function add() {
    const label = `Detail ${model.value.length + 1}`;

    model.value = [
        ...model.value,
        {
            key: keyFrom(label, model.value.length),
            label,
            type: 'text',
            required: true,
            options: [],
        },
    ];
}

function move(index: number, direction: -1 | 1) {
    const list = [...model.value];
    const [item] = list.splice(index, 1);
    list.splice(index + direction, 0, item);
    model.value = list;
}

function remove(index: number) {
    model.value = model.value.filter((_, position) => position !== index);
}
</script>

<template>
    <div class="grid gap-3">
        <p v-if="!model.length" class="text-sm text-muted-foreground">
            No details yet. Add what the person starting a run should fill in,
            such as an amount or a date.
        </p>

        <ol class="grid gap-3">
            <li
                v-for="(input, index) in model"
                :key="index"
                class="grid gap-2 rounded-lg border bg-secondary/40 p-3"
            >
                <div class="flex items-center gap-1">
                    <Input
                        :model-value="input.label"
                        :aria-label="`Name of detail ${index + 1}`"
                        maxlength="80"
                        :disabled="disabled"
                        class="h-8 flex-1"
                        @update:model-value="
                            (value) => update(index, { label: String(value) })
                        "
                    />
                    <template v-if="!disabled">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="index === 0"
                            :aria-label="`Move ${input.label} up`"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="index === model.length - 1"
                            :aria-label="`Move ${input.label} down`"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7 text-muted-foreground"
                            :aria-label="`Remove ${input.label}`"
                            @click="remove(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </template>
                </div>
                <div class="flex items-center gap-3">
                    <Select
                        :model-value="input.type"
                        :disabled="disabled"
                        @update:model-value="
                            (value) =>
                                update(index, { type: value as FieldType })
                        "
                    >
                        <SelectTrigger
                            class="h-8 flex-1"
                            :aria-label="`Type of ${input.label}`"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in props.types"
                                :key="type.value"
                                :value="type.value"
                                >{{ type.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <label class="flex items-center gap-2 text-xs">
                        <Switch
                            :model-value="input.required"
                            :disabled="disabled"
                            :aria-label="`${input.label} is required`"
                            @update:model-value="
                                (value) =>
                                    update(index, { required: Boolean(value) })
                            "
                        />
                        Required
                    </label>
                </div>
                <Textarea
                    v-if="input.type === 'select'"
                    :model-value="optionsText(input)"
                    rows="3"
                    :disabled="disabled"
                    :aria-label="`Options for ${input.label}, one per line`"
                    placeholder="One option per line"
                    @update:model-value="
                        (value) => updateOptions(index, String(value))
                    "
                />
                <p class="text-[0.6875rem] text-muted-foreground">
                    Referred to as
                    <code class="rounded bg-background px-1"
                        >input.{{ input.key }}</code
                    >
                </p>
            </li>
        </ol>

        <Button
            v-if="!disabled && model.length < max"
            type="button"
            variant="outline"
            size="sm"
            class="justify-self-start"
            @click="add"
        >
            <Plus />
            Add a detail
        </Button>
    </div>
</template>
