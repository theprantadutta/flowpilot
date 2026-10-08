<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOrganization } from '@/composables/useOrganization';
import type { MemberOption } from '@/types/operations';
import type { WorkflowField } from '@/types/workflows';

/**
 * The value side of a rule, shaped by the field: an amount, a date, one of a
 * choice's options, yes or no, or a member.
 */
const props = defineProps<{
    field: WorkflowField | undefined;
    members: MemberOption[];
    label: string;
    disabled?: boolean;
}>();

const model = defineModel<string | null>({ required: true });
const { organization } = useOrganization();

const text = computed({
    get: () => model.value ?? '',
    set: (value: string) => (model.value = value),
});
</script>

<template>
    <Select
        v-if="
            field?.type === 'select' ||
            field?.type === 'boolean' ||
            field?.type === 'person'
        "
        v-model="text"
        :disabled="disabled"
    >
        <SelectTrigger class="w-full" :aria-label="label"
            ><SelectValue placeholder="Choose"
        /></SelectTrigger>
        <SelectContent>
            <template v-if="field.type === 'select'">
                <SelectItem
                    v-for="option in field.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </template>
            <template v-else-if="field.type === 'boolean'">
                <SelectItem value="true">Yes</SelectItem>
                <SelectItem value="false">No</SelectItem>
            </template>
            <template v-else>
                <SelectItem
                    v-for="member in members"
                    :key="member.id"
                    :value="String(member.id)"
                >
                    {{ member.name }}
                </SelectItem>
            </template>
        </SelectContent>
    </Select>
    <div v-else-if="field?.type === 'money'" class="relative">
        <span
            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs text-muted-foreground"
        >
            {{ organization?.currency ?? '' }}
        </span>
        <Input
            v-model="text"
            inputmode="decimal"
            placeholder="5000.00"
            class="pl-12 figures"
            :aria-label="label"
            :disabled="disabled"
        />
    </div>
    <Input
        v-else-if="field?.type === 'date'"
        v-model="text"
        type="date"
        :aria-label="label"
        :disabled="disabled"
    />
    <Input
        v-else-if="field?.type === 'number'"
        v-model="text"
        inputmode="decimal"
        class="figures"
        :aria-label="label"
        :disabled="disabled"
    />
    <Input
        v-else
        v-model="text"
        :aria-label="label"
        :disabled="disabled"
        placeholder="Value"
    />
</template>
