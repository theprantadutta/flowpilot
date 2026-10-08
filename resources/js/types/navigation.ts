import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import type { Permission } from './organization';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Hide the item unless the member has this permission. */
    permission?: Permission;
    /** Highlight for any URL under href, not only an exact match. */
    matchPrefix?: boolean;
    /** Other sections that belong to this item, e.g. runs under workflows. */
    alsoMatches?: NonNullable<InertiaLinkProps['href']>[];
    /** A count shown beside the item, e.g. pending approvals. */
    badge?: number;
};

export type NavGroup = {
    title: string;
    items: NavItem[];
};
