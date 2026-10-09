<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Activity,
    ArrowLeftRight,
    Bell,
    CircleAlert,
    CornerDownLeft,
    FolderKanban,
    GitBranch,
    LayoutDashboard,
    ListChecks,
    Loader2,
    MailPlus,
    Moon,
    Plus,
    Search,
    Settings,
    Stamp,
    Sun,
    UserCog,
    Users,
} from '@lucide/vue';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    ListboxContent,
    ListboxFilter,
    ListboxGroup,
    ListboxGroupLabel,
    ListboxItem,
    ListboxRoot,
    VisuallyHidden,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import NamedIcon from '@/components/NamedIcon.vue';
import { useAppearance } from '@/composables/useAppearance';
import {
    shortcutLabel,
    useCommandPalette,
} from '@/composables/useCommandPalette';
import { useOrganization } from '@/composables/useOrganization';
import { overview, search as searchRoute } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as issuesIndex } from '@/routes/issues';
import { index as membersIndex } from '@/routes/members';
import { index as notificationsIndex } from '@/routes/notifications';
import { show as organizationSettings } from '@/routes/organization-settings';
import { edit as profileEdit } from '@/routes/profile';
import { index as projectsIndex } from '@/routes/projects';
import { index as tasksIndex } from '@/routes/tasks';
import { index as runsIndex } from '@/routes/workflow-runs';
import { index as workflowsIndex } from '@/routes/workflows';
import type { Permission } from '@/types';

type Command = {
    id: string;
    group: string;
    label: string;
    hint?: string;
    icon: LucideIcon;
    keywords?: string;
    permission?: Permission;
    run: () => void;
};

type SearchHit = {
    group: string;
    id: string;
    title: string;
    subtitle: string | null;
    url: string;
    icon: string;
};

const page = usePage();
const { isOpen, close, toggle } = useCommandPalette();
const { organization, can } = useOrganization();
const { resolvedAppearance, updateAppearance } = useAppearance();

const query = ref('');
const hits = ref<SearchHit[]>([]);
const searching = ref(false);

function go(href: Parameters<typeof router.visit>[0]) {
    close();
    router.visit(href);
}

const commands = computed<Command[]>(() => {
    const list: Command[] = [];

    if (organization.value) {
        list.push(
            {
                id: 'go-overview',
                group: 'Go to',
                label: 'Overview',
                icon: LayoutDashboard,
                permission: 'dashboard.view',
                keywords: 'home dashboard',
                run: () => go(overview()),
            },
            {
                id: 'go-projects',
                group: 'Go to',
                label: 'Projects',
                icon: FolderKanban,
                permission: 'projects.view',
                run: () => go(projectsIndex()),
            },
            {
                id: 'go-tasks',
                group: 'Go to',
                label: 'Tasks',
                icon: ListChecks,
                permission: 'tasks.view',
                keywords: 'todo work board kanban',
                run: () => go(tasksIndex()),
            },
            {
                id: 'go-my-tasks',
                group: 'Go to',
                label: 'My tasks',
                icon: ListChecks,
                permission: 'tasks.view',
                keywords: 'assigned to me',
                run: () => go(tasksIndex({}, { query: { assignee: 'me' } })),
            },
            {
                id: 'go-issues',
                group: 'Go to',
                label: 'Issues',
                icon: CircleAlert,
                permission: 'issues.view',
                keywords: 'problems bugs',
                run: () => go(issuesIndex()),
            },
            {
                id: 'go-workflows',
                group: 'Go to',
                label: 'Workflows',
                icon: GitBranch,
                permission: 'workflows.view',
                keywords: 'automation builder',
                run: () => go(workflowsIndex()),
            },
            {
                id: 'go-approvals',
                group: 'Go to',
                label: 'Approvals',
                icon: Stamp,
                permission: 'approvals.view',
                keywords: 'decisions requests waiting',
                run: () => go(approvalsIndex()),
            },
            {
                id: 'go-runs',
                group: 'Go to',
                label: 'Workflow runs',
                icon: GitBranch,
                permission: 'workflows.view',
                keywords: 'automation history failed',
                run: () => go(runsIndex()),
            },
            {
                id: 'go-activity',
                group: 'Go to',
                label: 'Activity',
                icon: Activity,
                permission: 'dashboard.view',
                keywords: 'history audit log',
                run: () => go(activityIndex()),
            },
            {
                id: 'create-project',
                group: 'Actions',
                label: 'Create project',
                icon: Plus,
                permission: 'projects.create',
                keywords: 'new',
                run: () => go(projectsIndex({}, { query: { create: 1 } })),
            },
            {
                id: 'create-task',
                group: 'Actions',
                label: 'Create task',
                icon: Plus,
                permission: 'tasks.create',
                keywords: 'new todo',
                run: () => go(tasksIndex({}, { query: { create: 1 } })),
            },
            {
                id: 'report-issue',
                group: 'Actions',
                label: 'Report an issue',
                icon: Plus,
                permission: 'issues.create',
                keywords: 'new problem bug',
                run: () => go(issuesIndex({}, { query: { create: 1 } })),
            },
            {
                id: 'create-approval',
                group: 'Actions',
                label: 'Request an approval',
                icon: Plus,
                permission: 'approvals.request',
                keywords: 'new purchase expense leave',
                run: () =>
                    go(
                        approvalsIndex(
                            {},
                            { query: { view: 'mine', create: 1 } },
                        ),
                    ),
            },
            {
                id: 'create-workflow',
                group: 'Actions',
                label: 'Create workflow',
                icon: Plus,
                permission: 'workflows.create',
                keywords: 'new automation',
                run: () => go(workflowsIndex({}, { query: { create: 1 } })),
            },
            {
                id: 'go-members',
                group: 'Go to',
                label: 'Members',
                icon: Users,
                permission: 'members.view',
                keywords: 'people team',
                run: () => go(membersIndex()),
            },
            {
                id: 'go-notifications',
                group: 'Go to',
                label: 'Notifications',
                icon: Bell,
                keywords: 'inbox alerts',
                run: () => go(notificationsIndex()),
            },
            {
                id: 'go-settings',
                group: 'Go to',
                label: 'Organization settings',
                icon: Settings,
                permission: 'settings.manage',
                keywords: 'preferences configure',
                run: () => go(organizationSettings()),
            },
            {
                id: 'invite',
                group: 'Actions',
                label: 'Invite member',
                icon: MailPlus,
                permission: 'members.invite',
                keywords: 'add person team',
                run: () => go(membersIndex({}, { query: { invite: 1 } })),
            },
        );
    }

    for (const item of page.props.organizations ?? []) {
        if (item.id !== organization.value?.id) {
            list.push({
                id: `switch-${item.id}`,
                group: 'Switch organization',
                label: item.name,
                hint: item.role_label,
                icon: ArrowLeftRight,
                keywords: 'switch organization workspace',
                run: () => go(overview(item.slug)),
            });
        }
    }

    list.push(
        {
            id: 'theme',
            group: 'Preferences',
            label:
                resolvedAppearance.value === 'dark'
                    ? 'Switch to light mode'
                    : 'Switch to dark mode',
            icon: resolvedAppearance.value === 'dark' ? Sun : Moon,
            keywords: 'theme appearance dark light',
            run: () => {
                updateAppearance(
                    resolvedAppearance.value === 'dark' ? 'light' : 'dark',
                );
                close();
            },
        },
        {
            id: 'account',
            group: 'Preferences',
            label: 'Account settings',
            icon: UserCog,
            keywords: 'profile password two-factor',
            run: () => go(profileEdit()),
        },
    );

    return list.filter(
        (command) => !command.permission || can(command.permission),
    );
});

const filteredCommands = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (!needle) {
        return commands.value;
    }

    return commands.value.filter((command) =>
        `${command.label} ${command.keywords ?? ''} ${command.group}`
            .toLowerCase()
            .includes(needle),
    );
});

const groupedCommands = computed(() => {
    const groups = new Map<string, Command[]>();

    for (const command of filteredCommands.value) {
        groups.set(command.group, [
            ...(groups.get(command.group) ?? []),
            command,
        ]);
    }

    return [...groups.entries()];
});

const groupedHits = computed(() => {
    const groups = new Map<string, SearchHit[]>();

    for (const hit of hits.value) {
        groups.set(hit.group, [...(groups.get(hit.group) ?? []), hit]);
    }

    return [...groups.entries()];
});

const runSearch = useDebounceFn(async (term: string) => {
    if (!organization.value || term.trim().length < 2) {
        hits.value = [];
        searching.value = false;

        return;
    }

    try {
        const response = await fetch(
            searchRoute({}, { query: { q: term } }).url,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const data = (await response.json()) as { results: SearchHit[] };

        // Ignore answers to a query the person has already moved past.
        if (term === query.value) {
            hits.value = data.results;
        }
    } catch {
        hits.value = [];
    } finally {
        if (term === query.value) {
            searching.value = false;
        }
    }
}, 180);

watch(query, (term) => {
    searching.value = term.trim().length >= 2 && !!organization.value;
    void runSearch(term);
});

watch(isOpen, (value) => {
    if (!value) {
        query.value = '';
        hits.value = [];
    }
});

function onSelect(value: unknown) {
    if (typeof value !== 'string') {
        return;
    }

    const hit = hits.value.find(
        (item) => `hit:${item.group}:${item.id}` === value,
    );

    if (hit) {
        go(hit.url);

        return;
    }

    commands.value.find((command) => command.id === value)?.run();
}

useEventListener(document, 'keydown', (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        toggle();
    }
});

const nothingFound = computed(
    () =>
        query.value.trim().length > 0 &&
        filteredCommands.value.length === 0 &&
        hits.value.length === 0 &&
        !searching.value,
);
</script>

<template>
    <DialogRoot v-model:open="isOpen">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-[#080d17]/50 backdrop-blur-[2px] data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed top-[12vh] left-1/2 z-50 w-[min(40rem,calc(100vw-2rem))] -translate-x-1/2 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-lg outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-[0.98] data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-[0.98]"
            >
                <VisuallyHidden>
                    <DialogTitle>Command palette</DialogTitle>
                </VisuallyHidden>

                <ListboxRoot
                    class="flex flex-col"
                    highlight-on-hover
                    @update:model-value="onSelect"
                >
                    <div class="flex items-center gap-2.5 border-b px-4">
                        <Search
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ListboxFilter
                            v-model="query"
                            auto-focus
                            placeholder="Search or jump to…"
                            aria-label="Search FlowPilot"
                            class="h-12 w-full bg-transparent text-[0.9375rem] outline-none placeholder:text-muted-foreground"
                        />
                        <Loader2
                            v-if="searching"
                            class="size-4 shrink-0 animate-spin text-muted-foreground"
                            aria-label="Searching"
                        />
                    </div>

                    <ListboxContent
                        class="max-h-[min(26rem,60vh)] scrollbar-thin overflow-y-auto p-2"
                    >
                        <ListboxGroup
                            v-for="[group, items] in groupedHits"
                            :key="`hits-${group}`"
                            class="mb-1"
                        >
                            <ListboxGroupLabel
                                class="px-2.5 pt-2 pb-1 text-xs font-medium text-muted-foreground"
                            >
                                {{ group }}
                            </ListboxGroupLabel>
                            <ListboxItem
                                v-for="hit in items"
                                :key="`hit:${hit.group}:${hit.id}`"
                                :value="`hit:${hit.group}:${hit.id}`"
                                class="group flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-sm outline-none data-[highlighted]:bg-accent"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-md border bg-card text-muted-foreground"
                                >
                                    <NamedIcon
                                        :name="hit.icon"
                                        class="size-3.5"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{
                                        hit.title
                                    }}</span>
                                    <span
                                        v-if="hit.subtitle"
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ hit.subtitle }}</span
                                    >
                                </span>
                                <CornerDownLeft
                                    class="size-3.5 text-muted-foreground opacity-0 group-data-[highlighted]:opacity-100"
                                    aria-hidden="true"
                                />
                            </ListboxItem>
                        </ListboxGroup>

                        <ListboxGroup
                            v-for="[group, items] in groupedCommands"
                            :key="group"
                            class="mb-1"
                        >
                            <ListboxGroupLabel
                                class="px-2.5 pt-2 pb-1 text-xs font-medium text-muted-foreground"
                            >
                                {{ group }}
                            </ListboxGroupLabel>
                            <ListboxItem
                                v-for="command in items"
                                :key="command.id"
                                :value="command.id"
                                class="group flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-sm outline-none data-[highlighted]:bg-accent"
                            >
                                <component
                                    :is="command.icon"
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="flex-1 truncate">{{
                                    command.label
                                }}</span>
                                <span
                                    v-if="command.hint"
                                    class="text-xs text-muted-foreground"
                                    >{{ command.hint }}</span
                                >
                                <CornerDownLeft
                                    class="size-3.5 text-muted-foreground opacity-0 group-data-[highlighted]:opacity-100"
                                    aria-hidden="true"
                                />
                            </ListboxItem>
                        </ListboxGroup>

                        <p
                            v-if="searching && hits.length === 0"
                            class="flex items-center gap-2 px-3 py-3 text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            <Loader2
                                class="size-3.5 animate-spin"
                                aria-hidden="true"
                            />
                            Searching {{ organization?.name }}…
                        </p>

                        <p
                            v-if="nothingFound"
                            class="px-3 py-10 text-center text-sm text-muted-foreground"
                        >
                            Nothing matches “{{ query }}”. Try a project name, a
                            task number like T-12, or a person.
                        </p>
                    </ListboxContent>
                </ListboxRoot>

                <footer
                    class="flex items-center justify-between gap-4 border-t bg-muted/40 px-4 py-2 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-3">
                        <span
                            ><kbd class="font-sans">↑</kbd>
                            <kbd class="font-sans">↓</kbd> to move</span
                        >
                        <span><kbd class="font-sans">Enter</kbd> to open</span>
                    </span>
                    <span
                        ><kbd class="font-sans">{{ shortcutLabel() }}</kbd> to
                        toggle</span
                    >
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
