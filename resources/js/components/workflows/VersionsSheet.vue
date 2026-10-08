<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTime } from '@/lib/format';
import { restore } from '@/routes/workflows/versions';
import type { WorkflowVersionItem } from '@/types/workflows';

/**
 * Every published version, newest first. A version can be copied back into
 * the draft to roll back; nothing changes for runs until it is published.
 */
const props = defineProps<{
    workflowId: string;
    versions: WorkflowVersionItem[];
    canRestore: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ restored: [] }>();

const restoring = ref<WorkflowVersionItem | null>(null);
const processing = ref(false);

function confirmRestore() {
    if (!restoring.value) {
        return;
    }

    router.post(
        restore({ workflow: props.workflowId, version: restoring.value.id })
            .url,
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onSuccess: () => {
                restoring.value = null;
                open.value = false;
                emit('restored');
            },
        },
    );
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Versions</SheetTitle>
                <SheetDescription
                    >Published versions never change, so every run can be
                    explained by the version it ran.</SheetDescription
                >
            </SheetHeader>

            <EmptyState
                v-if="!versions.length"
                compact
                :icon="History"
                title="Not published yet"
                description="Publish the workflow to create version 1."
            />
            <ol v-else class="grid gap-3 px-4 pb-6">
                <li
                    v-for="version in versions"
                    :key="version.id"
                    class="grid gap-2 rounded-xl border p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-display text-base font-semibold">
                            Version {{ version.version }}
                        </p>
                        <span
                            v-if="version.is_current"
                            class="rounded-full bg-success-soft px-2 py-0.5 text-xs font-medium text-success-text"
                            >Live</span
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(version.published_at)
                        }}<template v-if="version.publisher">
                            · {{ version.publisher }}</template
                        >
                        · {{ version.steps }} steps · {{ version.trigger }}
                    </p>
                    <p v-if="version.notes" class="text-sm">
                        {{ version.notes }}
                    </p>
                    <Button
                        v-if="canRestore"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="justify-self-start"
                        @click="restoring = version"
                    >
                        Copy into draft
                    </Button>
                </li>
            </ol>
        </SheetContent>
    </Sheet>

    <ConfirmDialog
        :open="restoring !== null"
        :title="`Copy version ${restoring?.version} into the draft?`"
        description="The current draft is replaced. Runs are not affected until you publish."
        confirm-label="Replace draft"
        :processing="processing"
        @update:open="(value) => !value && (restoring = null)"
        @confirm="confirmRestore"
    />
</template>
