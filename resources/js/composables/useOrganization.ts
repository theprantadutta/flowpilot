import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CurrentOrganization, Permission, PlanFeature } from '@/types';

/**
 * The organization the current page belongs to and what the viewer may do in it.
 *
 * Permissions here only decide what to show. Every action is authorized again
 * on the server.
 */
export function useOrganization() {
    const page = usePage();

    const organization = computed<CurrentOrganization | null>(
        () => page.props.organization,
    );

    const permissions = computed(
        () => new Set<Permission>(organization.value?.permissions ?? []),
    );

    function can(permission: Permission): boolean {
        return permissions.value.has(permission);
    }

    function canAny(...list: Permission[]): boolean {
        return list.some((permission) => permissions.value.has(permission));
    }

    /** Whether the organization's plan includes a feature. */
    function hasFeature(feature: PlanFeature): boolean {
        return organization.value?.plan.features.includes(feature) ?? false;
    }

    return { organization, can, canAny, hasFeature };
}
