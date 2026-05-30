import { createRouter, createWebHashHistory } from 'vue-router';
import Api from './services/Api';

const Dashboard = () => import('./views/Dashboard.vue');
const Scanner = () => import('./views/Scanner.vue');
const Batch = () => import('./views/Batch.vue');
const Settings = () => import('./views/Settings.vue');
const WooCommerce = () => import('./views/WooCommerce.vue');
const Help = () => import('./views/Help.vue');
const SetupWizard = () => import('./views/SetupWizard.vue');
const GeneralSettings = () => import('./components/Settings/GeneralSettings.vue');
const ApiSettings = () => import('./components/Settings/ApiSettings.vue');
const AltTextSettings = () => import('./components/Settings/AltTextSettings.vue');
const TitleSettings = () => import('./components/Settings/TitleSettings.vue');
const RenameSettings = () => import('./components/Settings/RenameSettings.vue');
const WooCommerceSettings = () => import('./components/Settings/WooCommerceSettings.vue');
const SeoSettings = () => import('./components/Settings/SeoSettings.vue');
const IntegrationSettings = () => import('./components/Settings/IntegrationSettings.vue');

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
    },
    {
        path: '/scanner',
        name: 'scanner',
        component: Scanner,
    },
    {
        path: '/woocommerce',
        name: 'woocommerce',
        component: WooCommerce,
    },
    {
        path: '/batch/:jobId?',
        name: 'batch',
        component: Batch,
    },
    {
        path: '/settings',
        component: Settings,
        children: [
            { path: '', redirect: '/settings/general' },
            { path: 'general', name: 'settings-general', component: GeneralSettings },
            { path: 'api', name: 'settings-api', component: ApiSettings },
            { path: 'alt-text', name: 'settings-alt-text', component: AltTextSettings },
            { path: 'title', name: 'settings-title', component: TitleSettings },
            { path: 'rename', name: 'settings-rename', component: RenameSettings },
            { path: 'integrations', name: 'settings-integrations', component: IntegrationSettings },
            { path: 'integrations/woocommerce', name: 'settings-integration-woocommerce', component: WooCommerceSettings },
            { path: 'integrations/seo', name: 'settings-integration-seo', component: SeoSettings },
            { path: 'woocommerce', redirect: '/settings/integrations/woocommerce' },
            { path: 'seo', redirect: '/settings/integrations/seo' },
        ],
    },
    {
        path: '/help',
        name: 'help',
        component: Help,
    },
    {
        path: '/wizard',
        name: 'wizard',
        component: SetupWizard,
    },
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

// Cache settings to avoid duplicate API calls
let settingsCache = null;
let settingsCacheTime = 0;
const CACHE_DURATION = 5000; // 5 seconds

// Check wizard completion on route change
router.beforeEach(async (to, from, next) => {
    try {
        // Use cached settings if available and fresh
        let settings;
        const now = Date.now();
        if (settingsCache && (now - settingsCacheTime) < CACHE_DURATION) {
            settings = settingsCache;
        } else {
            settings = await Api.get('/settings');
            settingsCache = settings;
            settingsCacheTime = now;
        }

        const isComplete = settings.wizard_completed && (settings.api_setting?.has_key || settings.site_has_key);

        if (to.path === '/wizard') {
            // Redirect completed users away from wizard to dashboard
            isComplete ? next('/') : next();
        } else {
            // Redirect incomplete users to wizard
            isComplete ? next() : next('/wizard');
        }
    } catch {
        // If API fails, allow navigation (might be first load)
        next();
    }
});

// Export function to clear cache (useful when settings are updated)
export const clearSettingsCache = () => {
    settingsCache = null;
    settingsCacheTime = 0;
};

/**
 * Get settings from cache or fetch fresh. Deduplicates with the router guard.
 */
export const getSettings = async () => {
    const now = Date.now();
    if (settingsCache && (now - settingsCacheTime) < CACHE_DURATION) {
        return settingsCache;
    }
    const result = await Api.get('/settings');
    settingsCache = result;
    settingsCacheTime = now;
    return result;
};

/**
 * Return the current cached settings (or null if not yet fetched).
 */
export const getSettingsCache = () => settingsCache;

export default router;