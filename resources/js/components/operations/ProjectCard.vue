<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleAlert, ListChecks } from '@lucide/vue';
import DueDate from '@/components/DueDate.vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { show } from '@/routes/projects';
import type { ProjectItem } from '@/types/operations';

defineProps<{ project: ProjectItem }>();
</script>

<template>
    <Link
        :href="show({ project: project.id })"
        class="group flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs transition-[border-color,box-shadow] hover:border-border-strong hover:shadow-sm focus-visible:outline-2"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3
                    class="truncate font-display text-base font-semibold group-hover:text-primary"
                >
                    {{ project.name }}
                </h3>
                <p class="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
                    {{ project.description || 'No description yet.' }}
                </p>
            </div>
            <EnumBadge :option="project.status" />
        </div>

        <ProgressBar
            :value="project.progress ?? 0"
            :label="`${project.name} progress`"
            :tone="
                project.status.value === 'completed'
                    ? 'success'
                    : project.is_overdue
                      ? 'warning'
                      : 'primary'
            "
            show-value
        />

        <div
            class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground"
        >
            <span class="inline-flex items-center gap-1">
                <ListChecks class="size-3.5" aria-hidden="true" />
                <span class="figures"
                    >{{ project.done_tasks_count ?? 0 }}/{{
                        project.tasks_count ?? 0
                    }}</span
                >
                tasks
            </span>
            <span
                v-if="(project.open_issues_count ?? 0) > 0"
                class="inline-flex items-center gap-1 text-warning-text"
            >
                <CircleAlert class="size-3.5" aria-hidden="true" />
                <span class="figures">{{ project.open_issues_count }}</span>
                open {{ project.open_issues_count === 1 ? 'issue' : 'issues' }}
            </span>
            <DueDate
                v-if="project.due_date"
                :date="project.due_date"
                :overdue="project.is_overdue"
                :done="project.status.value === 'completed'"
            />
            <span
                v-if="project.owner"
                class="ml-auto inline-flex items-center gap-1.5"
            >
                <MemberAvatar
                    :name="project.owner.name"
                    :avatar="project.owner.avatar"
                    class="size-5 text-[0.625rem]"
                />
                <span class="sr-only">Owner:</span>
                {{ project.owner.name }}
            </span>
        </div>
    </Link>
</template>
