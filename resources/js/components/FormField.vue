<script setup lang="ts">
import { computed, useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A labelled form control with optional help text and an error message, all
 * wired to the control through ids. The default slot receives the attributes
 * to spread onto the control.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        help?: string;
        error?: string;
        optional?: boolean;
        id?: string;
        class?: string;
    }>(),
    { help: undefined, error: undefined, optional: false, id: undefined },
);

const generatedId = useId();
const controlId = computed(() => props.id ?? `field-${generatedId}`);
const helpId = computed(() => `${controlId.value}-help`);
const errorId = computed(() => `${controlId.value}-error`);

const controlAttributes = computed(() => ({
    id: controlId.value,
    'aria-invalid': props.error ? true : undefined,
    'aria-describedby':
        [props.help ? helpId.value : null, props.error ? errorId.value : null]
            .filter(Boolean)
            .join(' ') || undefined,
}));
</script>

<template>
    <div :class="cn('grid content-start gap-2', props.class)">
        <Label :for="controlId">
            {{ label }}
            <span v-if="optional" class="font-normal text-muted-foreground"
                >(optional)</span
            >
        </Label>
        <slot v-bind="controlAttributes" />
        <p v-if="help" :id="helpId" class="text-xs text-muted-foreground">
            {{ help }}
        </p>
        <InputError :id="errorId" :message="error" />
    </div>
</template>
