<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { timeAgo } from '@/lib/format';
import { show } from '@/routes/issues';
import type { IssueItem } from '@/types/operations';

withDefaults(
    defineProps<{
        issues: IssueItem[];
        showProject?: boolean;
    }>(),
    { showProject: true },
);
</script>

<template>
    <table class="hidden w-full text-sm md:table">
        <thead>
            <tr class="border-b text-left text-xs text-muted-foreground">
                <th scope="col" class="w-[42%] py-2.5 pr-3 pl-5 font-medium">
                    Issue
                </th>
                <th scope="col" class="px-3 py-2.5 font-medium">Severity</th>
                <th scope="col" class="px-3 py-2.5 font-medium">Status</th>
                <th scope="col" class="px-3 py-2.5 font-medium">Assignee</th>
                <th scope="col" class="py-2.5 pr-5 pl-3 font-medium">
                    Reported
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="issue in issues"
                :key="issue.id"
                class="group border-b last:border-0 hover:bg-accent/40"
            >
                <td class="max-w-0 py-3 pr-3 pl-5">
                    <Link
                        :href="show({ issue: issue.id })"
                        class="flex min-w-0 items-baseline gap-2"
                    >
                        <span
                            class="shrink-0 figures text-xs text-muted-foreground"
                            >{{ issue.reference }}</span
                        >
                        <span
                            class="truncate font-medium group-hover:text-primary"
                            >{{ issue.title }}</span
                        >
                    </Link>
                    <div
                        class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="showProject && issue.project"
                            class="truncate"
                            >{{ issue.project.name }}</span
                        >
                        <DueDate
                            v-if="
                                issue.due_date &&
                                issue.status.value !== 'resolved' &&
                                issue.status.value !== 'closed'
                            "
                            :date="issue.due_date"
                            bare
                        />
                        <span
                            v-if="issue.comments_count"
                            class="inline-flex items-center gap-1"
                        >
                            <MessageSquare
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <span class="figures">{{
                                issue.comments_count
                            }}</span>
                            <span class="sr-only">comments</span>
                        </span>
                    </div>
                </td>
                <td class="px-3 py-3">
                    <EnumBadge :option="issue.severity" variant="plain" />
                </td>
                <td class="px-3 py-3"><EnumBadge :option="issue.status" /></td>
                <td class="px-3 py-3">
                    <span
                        v-if="issue.assignee"
                        class="inline-flex items-center gap-2"
                    >
                        <MemberAvatar
                            :name="issue.assignee.name"
                            :avatar="issue.assignee.avatar"
                            class="size-6 text-[0.625rem]"
                        />
                        <span class="truncate">{{ issue.assignee.name }}</span>
                    </span>
                    <span v-else class="text-muted-foreground">Unassigned</span>
                </td>
                <td class="py-3 pr-5 pl-3 text-xs text-muted-foreground">
                    {{ timeAgo(issue.created_at) }}
                </td>
            </tr>
        </tbody>
    </table>

    <ul class="divide-y md:hidden">
        <li v-for="issue in issues" :key="issue.id">
            <Link
                :href="show({ issue: issue.id })"
                class="flex flex-col gap-2 px-4 py-3.5"
            >
                <span class="flex items-baseline gap-2">
                    <span class="figures text-xs text-muted-foreground">{{
                        issue.reference
                    }}</span>
                    <span class="font-medium">{{ issue.title }}</span>
                </span>
                <span class="flex flex-wrap items-center gap-2">
                    <EnumBadge :option="issue.severity" variant="plain" />
                    <EnumBadge :option="issue.status" />
                    <span class="ml-auto text-xs text-muted-foreground">{{
                        timeAgo(issue.created_at)
                    }}</span>
                </span>
            </Link>
        </li>
    </ul>
</template>
