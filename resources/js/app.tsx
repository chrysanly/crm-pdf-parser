import { createInertiaApp } from '@inertiajs/react';
import { GlobalLoadingIndicator } from '@/components/global-loading-indicator';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { dismissSplash } from '@/lib/splash';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'CRM PDF Parser';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <GlobalLoadingIndicator />
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    // Progress is rendered by GlobalLoadingIndicator instead — two bars for one
    // visit is worse than none.
    progress: false,
}).then(dismissSplash);

// This will set light / dark mode on load...
initializeTheme();
