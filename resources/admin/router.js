import { createRouter, createWebHashHistory } from 'vue-router';
import { Dashboard, Scanner, Batch, Settings } from './views';
import SetupWizard from './views/SetupWizard.vue';
import Api from './services/Api';

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
        path: '/batch/:jobId?',
        name: 'batch',
        component: Batch,
    },
    {
        path: '/settings',
        name: 'settings',
        component: Settings,
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
    // Skip check for wizard route
    if (to.path === '/wizard') {
        next();
        return;
    }
    
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
        
        if (!settings.wizard_completed || !settings.api_setting?.has_key) {
            next('/wizard');
        } else {
            next();
        }
    } catch (error) {
        // If API fails, allow navigation (might be first load)
        next();
    }
});

// Export function to clear cache (useful when settings are updated)
export const clearSettingsCache = () => {
    settingsCache = null;
    settingsCacheTime = 0;
};

export default router;