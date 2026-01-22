import { createRouter, createWebHashHistory } from 'vue-router';
import { Dashboard } from './views';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
    },
];

const router = createRouter({
    history: createWebHashHistory(),
    routes,
});

export default router;