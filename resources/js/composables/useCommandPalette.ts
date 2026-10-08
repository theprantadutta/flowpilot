import { ref } from 'vue';

const isOpen = ref(false);

/**
 * Shared open state for the command palette, so any button can open it and
 * the keyboard shortcut works everywhere.
 */
export function useCommandPalette() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
        toggle: () => (isOpen.value = !isOpen.value),
    };
}

/** "⌘K" on Apple platforms, "Ctrl K" elsewhere. */
export function shortcutLabel(): string {
    if (typeof navigator === 'undefined') {
        return 'Ctrl K';
    }

    return /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K';
}
