<script setup lang="ts">
import { computed } from 'vue';
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
import { decodePick, encodePick, pickGroups } from './personPicks';

/**
 * One person to give work to. Choosing a role assigns the member of that role
 * with the least open work.
 */
const props = withDefaults(
    defineProps<{
        catalog: BuilderCatalog;
        fields: WorkflowField[];
        hasSubject: boolean;
        id?: string;
        /** Offer "Nobody" when the choice is optional. */
        noneLabel?: string;
        disabled?: boolean;
    }>(),
    { id: undefined, noneLabel: undefined, disabled: false },
);

const model = defineModel<PersonPick | null>({ required: true });

const groups = computed(() =>
    pickGroups(
        props.catalog,
        props.fields,
        props.hasSubject,
        (role) => `Least busy in ${role}`,
    ),
);

const value = computed({
    get: () => encodePick(model.value) || 'none',
    set: (next: string) => {
        model.value = next === 'none' ? null : decodePick(next);
    },
});
</script>

<template>
    <Select v-model="value" :disabled="disabled">
        <SelectTrigger :id="id" class="w-full">
            <SelectValue placeholder="Choose who" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="noneLabel" value="none">{{
                noneLabel
            }}</SelectItem>
            <SelectGroup v-for="group in groups" :key="group.label">
                <SelectLabel>{{ group.label }}</SelectLabel>
                <SelectItem
                    v-for="option in group.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
