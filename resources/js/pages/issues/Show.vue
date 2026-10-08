<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityItem from '@/components/ActivityItem.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DateInput from '@/components/DateInput.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import InlineText from '@/components/InlineText.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import NamedIcon from '@/components/NamedIcon.vue';
import CommentThread from '@/components/operations/CommentThread.vue';
import FileList from '@/components/operations/FileList.vue';
import PersonPicker from '@/components/PersonPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, timeAgo } from '@/lib/format';
import { destroy, index as issuesIndex, show, update } from '@/routes/issues';
import { store as storeAttachment } from '@/routes/issues/attachments';
import { store as storeComment } from '@/routes/issues/comments';
import { show as projectShow } from '@/routes/projects';
import type { ActivityEntry } from '@/types/activity';
import type {
    CommentItem,
    EnumOption,
    FileItem,
    IssueItem,
    MemberOption,
    ProjectOption,
} from '@/types/operations';

const props = defineProps<{
    issue: { data: IssueItem } | IssueItem;
    comments: CommentItem[];
    attachments: FileItem[];
    activity?: ActivityEntry[];
    severities: EnumOption[];
    statuses: EnumOption[];
    members?: MemberOption[];
    projects?: ProjectOption[];
}>();

const issue = computed<IssueItem>(() =>
    'data' in props.issue ? props.issue.data : props.issue,
);
const editable = computed(() => !!issue.value.can?.update);

setLayoutProps({
    breadcrumbs: [
        { title: 'Issues', href: issuesIndex() },
        { title: issue.value.reference, href: show({ issue: issue.value.id }) },
    ],
});

const page = usePage();
const { organization } = useOrganization();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

function save(
    changes: Record<string, string | number | boolean | string[] | null>,
) {
    router.visit(update.patch({ issue: issue.value.id }), {
        data: changes,
        preserveScroll: true,
        only: ['issue', 'activity', 'errors'],
    });
}

const projectModel = computed({
    get: () => issue.value.project?.id ?? 'none',
    set: (value: string) =>
        save({ project_id: value === 'none' ? null : value }),
});

const isOpen = computed(() =>
    ['open', 'investigating'].includes(issue.value.status.value),
);
const deleting = ref(false);
</script>

<template>
    <Head :title="`${issue.reference} ${issue.title}`" />

    <div
        class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:py-8"
    >
        <main class="grid min-w-0 content-start gap-8">
            <header class="grid gap-3">
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="figures">{{ issue.reference }}</span>
                    <template v-if="issue.project">
                        <span aria-hidden="true">/</span>
                        <Link
                            :href="projectShow({ project: issue.project.id })"
                            class="hover:text-foreground hover:underline"
                            >{{ issue.project.name }}</Link
                        >
                    </template>
                </div>
                <InlineText
                    :value="issue.title"
                    label="title"
                    :editable="editable"
                    :error="errors.title"
                    class="text-2xl leading-tight font-semibold"
                    @save="(value) => save({ title: value })"
                />
                <div class="flex flex-wrap items-center gap-3">
                    <EnumBadge :option="issue.severity" />
                    <EnumBadge :option="issue.status" />
                    <Button
                        v-if="editable"
                        size="sm"
                        :variant="isOpen ? 'default' : 'outline'"
                        class="ml-auto"
                        @click="save({ status: isOpen ? 'resolved' : 'open' })"
                    >
                        <NamedIcon
                            :name="isOpen ? 'circle-check' : 'circle'"
                            class="size-4"
                        />
                        {{ isOpen ? 'Mark resolved' : 'Reopen issue' }}
                    </Button>
                </div>
            </header>

            <section aria-labelledby="details-heading" class="grid gap-2">
                <h2 id="details-heading" class="text-sm font-semibold">
                    Details
                </h2>
                <InlineText
                    :value="issue.description"
                    label="details"
                    :editable="editable"
                    multiline
                    :maxlength="20000"
                    placeholder="Add what happens, when, and what has been tried."
                    class="text-sm leading-relaxed"
                    @save="(value) => save({ description: value || null })"
                />
            </section>

            <section aria-labelledby="files-heading" class="grid gap-3">
                <h2 id="files-heading" class="text-sm font-semibold">Files</h2>
                <FileList
                    :files="attachments"
                    :upload-to="
                        editable ? storeAttachment({ issue: issue.id }) : null
                    "
                    compact
                />
            </section>

            <section aria-labelledby="comments-heading" class="grid gap-3">
                <h2 id="comments-heading" class="text-sm font-semibold">
                    Comments
                </h2>
                <CommentThread
                    :comments="comments"
                    :post-to="storeComment({ issue: issue.id })"
                    :timezone="organization?.timezone"
                />
            </section>

            <section aria-labelledby="history-heading" class="grid gap-3">
                <h2 id="history-heading" class="text-sm font-semibold">
                    History
                </h2>
                <div v-if="activity === undefined" class="grid gap-3">
                    <Skeleton v-for="n in 3" :key="n" class="h-9 w-full" />
                </div>
                <ol v-else-if="activity.length">
                    <ActivityItem
                        v-for="(entry, index) in activity"
                        :key="entry.id"
                        :entry="entry"
                        :timezone="organization?.timezone"
                        :connected="index < activity.length - 1"
                    />
                </ol>
            </section>
        </main>

        <aside
            class="grid h-fit gap-5 rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-6"
        >
            <div class="grid gap-1.5">
                <label
                    for="issue-severity"
                    class="text-xs font-medium text-muted-foreground"
                    >Severity</label
                >
                <Select
                    :model-value="issue.severity.value"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ severity: String(value) })
                    "
                >
                    <SelectTrigger id="issue-severity" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in severities"
                            :key="option.value"
                            :value="option.value"
                        >
                            <span class="flex items-center gap-2"
                                ><NamedIcon
                                    :name="option.icon"
                                    class="size-4"
                                />{{ option.label }}</span
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="issue-status"
                    class="text-xs font-medium text-muted-foreground"
                    >Status</label
                >
                <Select
                    :model-value="issue.status.value"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ status: String(value) })
                    "
                >
                    <SelectTrigger id="issue-status" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            <span class="flex items-center gap-2"
                                ><NamedIcon
                                    :name="option.icon"
                                    class="size-4"
                                />{{ option.label }}</span
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="issue-assignee"
                    class="text-xs font-medium text-muted-foreground"
                    >Assignee</label
                >
                <PersonPicker
                    id="issue-assignee"
                    :model-value="issue.assignee?.id ?? null"
                    :members="members ?? []"
                    :disabled="!editable"
                    @update:model-value="
                        (value) => save({ assignee_id: value })
                    "
                />
                <p v-if="errors.assignee_id" class="text-sm text-danger-text">
                    {{ errors.assignee_id }}
                </p>
            </div>

            <div class="grid gap-1.5">
                <label
                    for="issue-due"
                    class="text-xs font-medium text-muted-foreground"
                    >Fix by</label
                >
                <DateInput
                    id="issue-due"
                    :model-value="issue.due_date"
                    :disabled="!editable"
                    @update:model-value="(value) => save({ due_date: value })"
                />
            </div>

            <div class="grid gap-1.5">
                <label
                    for="issue-project"
                    class="text-xs font-medium text-muted-foreground"
                    >Project</label
                >
                <Select v-model="projectModel" :disabled="!editable">
                    <SelectTrigger id="issue-project" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No project</SelectItem>
                        <SelectItem
                            v-if="
                                issue.project &&
                                !(projects ?? []).some(
                                    (p) => p.id === issue.project?.id,
                                )
                            "
                            :value="issue.project.id"
                        >
                            {{ issue.project.name }}
                        </SelectItem>
                        <SelectItem
                            v-for="project in projects ?? []"
                            :key="project.id"
                            :value="project.id"
                            >{{ project.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <dl class="grid gap-2 border-t pt-4 text-xs text-muted-foreground">
                <div
                    v-if="issue.reporter"
                    class="flex items-center justify-between gap-2"
                >
                    <dt>Reported by</dt>
                    <dd class="flex items-center gap-1.5 text-foreground">
                        <MemberAvatar
                            :name="issue.reporter.name"
                            :avatar="issue.reporter.avatar"
                            class="size-5 text-[0.625rem]"
                        />
                        {{ issue.reporter.name }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt>Reported</dt>
                    <dd
                        :title="
                            formatDateTime(
                                issue.created_at,
                                organization?.timezone,
                            )
                        "
                    >
                        {{ timeAgo(issue.created_at) }}
                    </dd>
                </div>
                <div
                    v-if="issue.resolved_at"
                    class="flex justify-between gap-2"
                >
                    <dt>Resolved</dt>
                    <dd>
                        {{
                            formatDateTime(
                                issue.resolved_at,
                                organization?.timezone,
                            )
                        }}
                    </dd>
                </div>
            </dl>

            <Button
                v-if="issue.can?.delete"
                variant="ghost"
                size="sm"
                class="justify-start text-danger-text"
                @click="deleting = true"
            >
                <Trash2 />
                Delete issue
            </Button>
        </aside>

        <ConfirmDialog
            v-model:open="deleting"
            :title="`Delete ${issue.reference}?`"
            description="The issue, its comments and files are removed. The activity log keeps a record that it existed."
            confirm-label="Delete issue"
            destructive
            @confirm="router.visit(destroy({ issue: issue.id }))"
        />
    </div>
</template>
