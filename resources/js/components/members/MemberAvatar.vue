<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        name: string;
        avatar?: string | null;
        class?: string;
    }>(),
    { avatar: null },
);
</script>

<template>
    <Avatar :class="cn('size-8 shrink-0 rounded-full', props.class)">
        <AvatarImage v-if="avatar" :src="avatar" :alt="name" />
        <AvatarFallback
            class="rounded-full bg-secondary text-xs font-medium text-secondary-foreground"
        >
            {{ getInitials(name) }}
        </AvatarFallback>
    </Avatar>
</template>
