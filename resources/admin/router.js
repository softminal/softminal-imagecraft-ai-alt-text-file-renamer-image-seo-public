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

// Check wizard completion on route change
router.beforeEach(async (to, from, next) => {
    // Skip check for wizard route
    if (to.path === '/wizard') {
        next();
        return;
    }
    
    try {
        const settings = await Api.get('/settings');
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

export default router;