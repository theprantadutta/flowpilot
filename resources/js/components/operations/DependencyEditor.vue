<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Link2, X } from '@lucide/vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show } from '@/routes/tasks';
import { destroy, store } from '@/routes/tasks/dependencies';
import type { TaskLink } from '@/types/operations';

const props = defineProps<{
    taskId: string;
    dependencies: TaskLink[];
    dependents: TaskLink[];
    options: { id: string; label: string }[];
    editable: boolean;
}>();

const form = useForm({ depends_on_id: '' });

function add() {
    form.submit(store({ task: props.taskId }), {
        preserveScroll: true,
        only: ['task'],
        onSuccess: () => form.reset(),
    });
}

function remove(blocker: TaskLink) {
    router.visit(destroy({ task: props.taskId, blocker: blocker.id }), {
        preserveScroll: true,
        only: ['task'],
    });
}
</script>

<template>
    <div class="grid gap-4 text-sm">
        <div>
            <h3 class="mb-2 text-xs font-medium text-muted-foreground">
                Waiting on
            </h3>
            <ul v-if="dependencies.length" class="grid gap-1.5">
                <li
                    v-for="blocker in dependencies"
                    :key="blocker.id"
                    class="flex items-center gap-2"
                >
                    <Link
                        :href="show({ task: blocker.id })"
                        class="min-w-0 flex-1 truncate hover:underline"
                    >
                        <span
                            class="mr-1 figures text-xs text-muted-foreground"
                            >{{ blocker.reference }}</span
                        >{{ blocker.title }}
                    </Link>
                    <EnumBadge :option="blocker.status" variant="plain" />
                    <button
                        v-if="editable"
                        type="button"
                        class="flex size-6 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground"
                        :aria-label="`Stop waiting on ${blocker.reference}`"
                        @click="remove(blocker)"
                    >
                        <X class="size-3.5" />
                    </button>
                </li>
            </ul>
            <p v-else class="text-muted-foreground">
                Nothing. This task can start any time.
            </p>
        </div>

        <form
            v-if="editable && options.length"
            class="flex items-center gap-2"
            @submit.prevent="add"
        >
            <Select v-model="form.depends_on_id">
                <SelectTrigger
                    class="h-8 flex-1"
                    aria-label="Task this one waits on"
                >
                    <SelectValue placeholder="Add a task this waits on" />
                </SelectTrigger>
                <SelectContent class="max-h-72">
                    <SelectItem
                        v-for="option in options"
                        :key="option.id"
                        :value="option.id"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                :disabled="!form.depends_on_id || form.processing"
            >
                <Link2 />
                Link
            </Button>
        </form>
        <InputError :message="form.errors.depends_on_id" />

        <div v-if="dependents.length">
            <h3 class="mb-2 text-xs font-medium text-muted-foreground">
                Holding up
            </h3>
            <ul class="grid gap-1.5">
                <li
                    v-for="waiting in dependents"
                    :key="waiting.id"
                    class="flex items-center gap-2"
                >
                    <Link
                        :href="show({ task: waiting.id })"
                        class="min-w-0 flex-1 truncate hover:underline"
                    >
                        <span
                            class="mr-1 figures text-xs text-muted-foreground"
                            >{{ waiting.reference }}</span
                        >{{ waiting.title }}
                    </Link>
                    <EnumBadge :option="waiting.status" variant="plain" />
                </li>
            </ul>
        </div>
    </div>
</template>
