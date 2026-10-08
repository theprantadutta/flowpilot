<script setup lang="ts">
import { Braces } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { VariableOption } from '@/types/workflows';

/**
 * Text that can include values from the run, written as {{ path }}. The
 * menu inserts a value where the cursor is, so nobody has to type paths.
 */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        variables: VariableOption[];
        multiline?: boolean;
        rows?: number;
        placeholder?: string;
        maxlength?: number;
        disabled?: boolean;
    }>(),
    {
        multiline: false,
        rows: 3,
        placeholder: undefined,
        maxlength: undefined,
        disabled: false,
    },
);

const model = defineModel<string>({ required: true });
const control = ref<{ $el: Element } | null>(null);

function element(): HTMLInputElement | HTMLTextAreaElement | null {
    const root = control.value?.$el ?? null;

    if (
        root instanceof HTMLInputElement ||
        root instanceof HTMLTextAreaElement
    ) {
        return root;
    }

    return (
        root?.querySelector<HTMLInputElement | HTMLTextAreaElement>(
            'input, textarea',
        ) ?? null
    );
}

function insert(path: string) {
    const token = `{{ ${path} }}`;
    const field = element();
    const value = model.value ?? '';
    const start = field?.selectionStart ?? value.length;
    const end = field?.selectionEnd ?? value.length;

    model.value = `${value.slice(0, start)}${token}${value.slice(end)}`;

    requestAnimationFrame(() => {
        field?.focus();
        field?.setSelectionRange(start + token.length, start + token.length);
    });
}
</script>

<template>
    <div class="relative">
        <Textarea
            v-if="multiline"
            ref="control"
            v-bind="$attrs"
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            :maxlength="maxlength"
            :disabled="disabled"
            class="pr-10"
        />
        <Input
            v-else
            ref="control"
            v-bind="$attrs"
            v-model="model"
            :placeholder="placeholder"
            :maxlength="maxlength"
            :disabled="disabled"
            class="pr-10"
        />
        <DropdownMenu v-if="!disabled && props.variables.length">
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="absolute top-1 right-1 size-7 text-muted-foreground"
                    aria-label="Insert a value from the run"
                    title="Insert a value from the run"
                >
                    <Braces class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                class="max-h-72 w-64 overflow-y-auto"
            >
                <DropdownMenuLabel>Insert a value</DropdownMenuLabel>
                <DropdownMenuItem
                    v-for="variable in props.variables"
                    :key="variable.path"
                    @select="insert(variable.path)"
                >
                    <span class="truncate">{{ variable.label }}</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
