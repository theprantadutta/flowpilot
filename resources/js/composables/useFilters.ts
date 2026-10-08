import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';

/**
 * Keeps a list page's filters in the URL. Changing a filter reloads the
 * page's data (not the whole page), so back/forward and shared links work.
 *
 * Text fields are debounced; everything else applies straight away.
 */
export function useFilters<
    T extends Record<string, string | number | boolean | null>,
>(initial: T, options: { only?: string[]; debounced?: (keyof T)[] } = {}) {
    const filters = reactive({ ...initial }) as T;
    const debouncedKeys = new Set(options.debounced ?? ['q']);

    function apply() {
        const query: Record<string, string> = {};

        for (const [key, value] of Object.entries(filters)) {
            if (value !== null && value !== '' && value !== false) {
                query[key] = value === true ? '1' : String(value);
            }
        }

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: options.only,
        });
    }

    const applyDebounced = useDebounceFn(apply, 300);

    for (const key of Object.keys(initial)) {
        watch(
            () => filters[key as keyof T],
            () => (debouncedKeys.has(key) ? applyDebounced() : apply()),
        );
    }

    function reset(values: Partial<T>) {
        Object.assign(filters, values);
    }

    return { filters, apply, reset };
}
