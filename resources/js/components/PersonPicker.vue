<script setup lang="ts">
import { computed } from 'vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { MemberOption } from '@/types/operations';

/**
 * Choose a member of the organization (or nobody). Values are user ids.
 */
const props = withDefaults(
    defineProps<{
        members: MemberOption[];
        placeholder?: string;
        noneLabel?: string;
        allowNone?: boolean;
        id?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Choose someone',
        noneLabel: 'Unassigned',
        allowNone: true,
        id: undefined,
    },
);

const model = defineModel<number | null>({ default: null });

// Select works with strings; "none" stands for nobody.
const selected = computed({
    get: () => (model.value === null ? 'none' : String(model.value)),
    set: (value: string) => {
        model.value = value === 'none' ? null : Number(value);
    },
});

const current = computed(() =>
    props.members.find((member) => member.id === model.value),
);
</script>

<template>
    <Select v-model="selected" :disabled="disabled">
        <SelectTrigger :id="id" class="w-full">
            <SelectValue :placeholder="placeholder">
                <span v-if="current" class="flex items-center gap-2">
                    <MemberAvatar
                        :name="current.name"
                        :avatar="current.avatar"
                        class="size-5 text-[0.625rem]"
                    />
                    {{ current.name }}
                </span>
                <span v-else class="text-muted-foreground">{{
                    allowNone ? noneLabel : placeholder
                }}</span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent class="max-h-72">
            <SelectItem v-if="allowNone" value="none">
                <span class="text-muted-foreground">{{ noneLabel }}</span>
            </SelectItem>
            <SelectItem
                v-for="member in members"
                :key="member.id"
                :value="String(member.id)"
            >
                <span class="flex items-center gap-2">
                    <MemberAvatar
                        :name="member.name"
                        :avatar="member.avatar"
                        class="size-5 text-[0.625rem]"
                    />
                    <span>{{ member.name }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        member.role
                    }}</span>
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
