<script setup lang="ts">
import { computed } from 'vue';
import { Switch } from '@/components/ui/switch';

/**
 * One switch per notification type, grouped by area. Used for a member's own
 * email settings and for the organization's defaults.
 */
type TypeOption = {
    value: string;
    label: string;
    group: string;
    email_by_default: boolean;
};

const props = defineProps<{
    types: TypeOption[];
    /** Label prefix for screen readers, e.g. "Email me when". */
    describedAs?: string;
}>();

const model = defineModel<Record<string, boolean>>({ required: true });

const groups = computed(() => {
    const map = new Map<string, TypeOption[]>();

    for (const type of props.types) {
        map.set(type.group, [...(map.get(type.group) ?? []), type]);
    }

    return [...map.entries()];
});

function set(type: string, value: boolean) {
    model.value = { ...model.value, [type]: value };
}
</script>

<template>
    <div class="grid gap-6">
        <fieldset
            v-for="[group, items] in groups"
            :key="group"
            class="grid gap-1"
        >
            <legend class="mb-2 text-xs font-medium text-muted-foreground">
                {{ group }}
            </legend>
            <label
                v-for="type in items"
                :key="type.value"
                class="flex cursor-pointer items-center justify-between gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-accent/50"
            >
                <span class="text-sm">{{ type.label }}</span>
                <Switch
                    :model-value="model[type.value] ?? type.email_by_default"
                    :aria-label="`${describedAs ?? 'Email'}: ${type.label}`"
                    @update:model-value="
                        (value: boolean) => set(type.value, value)
                    "
                />
            </label>
        </fieldset>
    </div>
</template>
