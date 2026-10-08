<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import type { RouteDefinition } from '@/wayfinder';
import { MessageSquare, Trash2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, timeAgo } from '@/lib/format';
import { destroy } from '@/routes/comments';
import type { CommentItem } from '@/types/operations';

const props = defineProps<{
    comments: CommentItem[];
    postTo: RouteDefinition<'post'>;
    timezone?: string;
}>();

const form = useForm({ body: '' });

function post() {
    form.submit(props.postTo, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        post();
    }
}

function remove(comment: CommentItem) {
    router.visit(destroy({ comment: comment.id }), { preserveScroll: true });
}
</script>

<template>
    <div class="grid gap-5">
        <EmptyState
            v-if="comments.length === 0"
            :icon="MessageSquare"
            title="No comments yet"
            description="Questions, decisions and updates about this work go here."
            compact
        />

        <ol v-else class="grid gap-4">
            <li
                v-for="comment in comments"
                :key="comment.id"
                class="group flex gap-3"
            >
                <MemberAvatar
                    :name="comment.author.name"
                    :avatar="comment.author.avatar"
                />
                <div class="min-w-0 flex-1">
                    <p class="flex items-baseline gap-2 text-sm">
                        <span class="font-medium">{{
                            comment.author.name
                        }}</span>
                        <time
                            :datetime="comment.created_at ?? undefined"
                            :title="
                                formatDateTime(comment.created_at, timezone)
                            "
                            class="text-xs text-muted-foreground"
                        >
                            {{ timeAgo(comment.created_at) }}
                        </time>
                    </p>
                    <p
                        class="mt-1 text-sm leading-relaxed break-words whitespace-pre-line text-foreground/90"
                    >
                        {{ comment.body }}
                    </p>
                </div>
                <Button
                    v-if="comment.can_delete"
                    variant="ghost"
                    size="icon-sm"
                    class="opacity-0 group-focus-within:opacity-100 group-hover:opacity-100"
                    aria-label="Delete your comment"
                    @click="remove(comment)"
                >
                    <Trash2 />
                </Button>
            </li>
        </ol>

        <form class="grid gap-2" @submit.prevent="post">
            <label for="new-comment" class="sr-only">Write a comment</label>
            <Textarea
                id="new-comment"
                v-model="form.body"
                rows="3"
                maxlength="5000"
                placeholder="Write a comment…"
                :aria-invalid="!!form.errors.body"
                @keydown="onKeydown"
            />
            <InputError :message="form.errors.body" />
            <div class="flex items-center justify-end gap-3">
                <span class="hidden text-xs text-muted-foreground sm:inline"
                    >Ctrl + Enter to post</span
                >
                <Button
                    type="submit"
                    size="sm"
                    :disabled="form.processing || form.body.trim() === ''"
                >
                    <Spinner v-if="form.processing" />
                    Post comment
                </Button>
            </div>
        </form>
    </div>
</template>
