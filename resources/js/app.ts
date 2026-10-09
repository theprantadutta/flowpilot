import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import OrganizationSettingsLayout from '@/layouts/settings/OrganizationSettingsLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { setUrlDefaults } from '@/wayfinder';

const appName = import.meta.env.VITE_APP_NAME || 'FlowPilot';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('onboarding/'):
            case name.startsWith('invitations/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('organization-settings/'):
                return [AppLayout, OrganizationSettingsLayout];
            case name.startsWith('platform/'):
                return PlatformLayout;
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#2563EB',
    },
});

// Routes inside an organization take its slug as their first parameter. Fill it
// in from the current page so callers write members.index(), not members.index(slug).
const page = usePage();
setUrlDefaults(() => ({ organization: page.props?.organization?.slug }));

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
