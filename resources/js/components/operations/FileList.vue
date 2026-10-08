<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import type { RouteDefinition } from '@/wayfinder';
import { Download, FileText, FileUp, Image, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber, timeAgo } from '@/lib/format';
import { destroy } from '@/routes/attachments';
import type { FileItem } from '@/types/operations';

/**
 * Files on a record, with upload (when allowed) and download links.
 */
const props = withDefaults(
    defineProps<{
        files: FileItem[];
        uploadTo?: RouteDefinition<'post'> | null;
        compact?: boolean;
    }>(),
    { uploadTo: null, compact: false },
);

const input = ref<HTMLInputElement | null>(null);
const form = useForm<{ file: File | null }>({ file: null });
const removing = ref<string | null>(null);

function size(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${formatNumber(bytes / 1024, { maximumFractionDigits: 0 })} KB`;
    }

    return `${formatNumber(bytes / 1024 / 1024, { maximumFractionDigits: 1 })} MB`;
}

function upload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file || !props.uploadTo) {
        return;
    }

    form.file = file;
    form.submit(props.uploadTo, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset();

            if (input.value) {
                input.value.value = '';
            }
        },
    });
}

function remove(file: FileItem) {
    router.visit(destroy({ attachment: file.id }), {
        preserveScroll: true,
        onStart: () => (removing.value = file.id),
        onFinish: () => (removing.value = null),
    });
}

const isImage = (file: FileItem) => file.mime_type.startsWith('image/');
</script>

<template>
    <div class="grid gap-3">
        <div v-if="uploadTo" class="flex flex-wrap items-center gap-3">
            <input
                ref="input"
                type="file"
                class="sr-only"
                accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                aria-label="Upload a file"
                @change="upload"
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="form.processing"
                @click="input?.click()"
            >
                <Spinner v-if="form.processing" />
                <FileUp v-else />
                Upload file
            </Button>
            <span class="text-xs text-muted-foreground"
                >PDF, images, Office documents, CSV or ZIP, up to 20 MB.</span
            >
            <InputError :message="form.errors.file" class="w-full" />
        </div>

        <EmptyState
            v-if="files.length === 0"
            :icon="FileText"
            title="No files yet"
            :description="
                uploadTo
                    ? 'Drawings, quotes, photos and reports for this work belong here.'
                    : 'Nothing has been attached.'
            "
            compact
        />

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="file in files"
                :key="file.id"
                class="flex items-center gap-3 px-3 py-2.5"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-md bg-secondary text-muted-foreground"
                >
                    <Image
                        v-if="isImage(file)"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <FileText v-else class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <a
                        :href="file.download_url"
                        class="block truncate text-sm font-medium hover:underline"
                        >{{ file.name }}</a
                    >
                    <p class="text-xs text-muted-foreground">
                        {{ size(file.size) }}
                        <template v-if="!compact">
                            · {{ file.uploaded_by ?? 'A former member' }} ·
                            {{ timeAgo(file.created_at) }}
                            <template v-if="file.source === 'task'">
                                · on a task</template
                            >
                        </template>
                    </p>
                </div>
                <Button
                    as="a"
                    :href="file.download_url"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Download ${file.name}`"
                >
                    <Download />
                </Button>
                <Button
                    v-if="file.can_delete"
                    variant="ghost"
                    size="icon-sm"
                    :disabled="removing === file.id"
                    :aria-label="`Remove ${file.name}`"
                    @click="remove(file)"
                >
                    <Trash2 />
                </Button>
            </li>
        </ul>
    </div>
</template>
