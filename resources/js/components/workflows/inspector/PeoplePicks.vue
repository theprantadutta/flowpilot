<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    BuilderCatalog,
    PersonPick,
    WorkflowField,
} from '@/types/workflows';
import { decodePick, encodePick, pickGroups, pickLabel } from './personPicks';

/**
 * Who a message goes to: any mix of people, roles and people from the run.
 */
const props = withDefaults(
    defineProps<{
        catalog: BuilderCatalog;
        fields: WorkflowField[];
        hasSubject: boolean;
        id?: string;
        disabled?: boolean;
    }>(),
    { id: undefined, disabled: false },
);

const model = defineModel<PersonPick[]>({ required: true });
const adding = ref('');

const groups = computed(() =>
    pickGroups(props.catalog, props.fields, props.hasSubject),
);
const chosen = computed(() => model.value.map((pick) => encodePick(pick)));

function add(value: unknown) {
    const pick = typeof value === 'string' ? decodePick(value) : null;

    if (pick && !chosen.value.includes(value as string)) {
        model.value = [...model.value, pick];
    }

    adding.value = '';
}

function remove(index: number) {
    model.value = model.value.filter((_, position) => position !== index);
}
</script>

<template>
    <div class="grid gap-2">
        <ul
            v-if="model.length"
            class="flex flex-wrap gap-1.5"
            aria-label="Chosen recipients"
        >
            <li
                v-for="(value, index) in chosen"
                :key="value"
                class="inline-flex h-7 items-center gap-1 rounded-full border bg-secondary pr-1 pl-2.5 text-xs font-medium"
            >
                {{ pickLabel(value, groups) }}
                <button
                    v-if="!disabled"
                    type="button"
                    class="rounded-full p-0.5 text-muted-foreground hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Remove ${pickLabel(value, groups)}`"
                    @click="remove(index)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>
        <Select
            v-if="!disabled"
            :model-value="adding"
            @update:model-value="add"
        >
            <SelectTrigger :id="id" class="w-full">
                <SelectValue placeholder="Add people or a role…" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup v-for="group in groups" :key="group.label">
                    <SelectLabel>{{ group.label }}</SelectLabel>
                    <SelectItem
                        v-for="option in group.options"
                        :key="option.value"
                        :value="option.value"
                        :disabled="chosen.includes(option.value)"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>
