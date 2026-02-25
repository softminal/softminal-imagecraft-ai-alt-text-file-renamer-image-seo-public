<template>
    <nav class="main_nav_soft_carousel bg-white border-b border-gray-200 px-4 py-2.5 left-0 right-0 top-0 z-50">
        <div class="flex flex-wrap justify-between items-center">
            <!-- Logo Section -->
            <router-link to="/" class="flex items-center bg-inherit outline-none focus:outline-none focus:shadow-none">
                <img :src="asset('imagecraft-logo.png')" alt="ImageCraft" class="h-16" />
            </router-link>

            <!-- Navigation Menu -->
            <div v-if="menuItems && menuItems.length > 0" class="flex items-center gap-1">
                <router-link
                    v-for="(item, index) in menuItems"
                    :key="index"
                    :to="item.path"
                    class="nav-link px-4 py-2 text-sm outline-none focus:shadow-none font-medium text-gray-700 rounded-lg hover:bg-blue-100 transition-colors"
                    :class="{ 'bg-blue-100 text-blue-700': isActive(item.path) }"
                >
                    <span v-if="item.icon" class="mr-1">{{ item.icon }}</span>
                    {{ $t(item.name) }}
                </router-link>
            </div>
            <div v-else class="flex items-center gap-1 text-gray-500 text-sm">
                {{ $t('Loading...') }}
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Api from '../services/Api'
import { useAsset } from '../composables/useAsset'

const { asset } = useAsset()

const route = useRoute()

const menuItems = ref([
    { name: 'Dashboard', path: '/' },
    { name: 'Scanner', path: '/scanner' },
    { name: 'WooCommerce', path: '/woocommerce', icon: '🛒' },
    { name: 'Settings', path: '/settings' }
])

const isActive = (path) => {
    const currentPath = route.path.replace(/^#/, '')
    const checkPath = path.replace(/^#/, '')
    return currentPath === checkPath || currentPath === checkPath + '/'
}

onMounted(async () => {
    try {
        const wooStats = await Api.get('/woocommerce/stats')
        if (!wooStats.is_active) {
            menuItems.value = menuItems.value.filter(item => item.path !== '/woocommerce')
        }
    } catch (e) {
        menuItems.value = menuItems.value.filter(item => item.path !== '/woocommerce')
    }
})
</script>

<style scoped>
.nav-link.router-link-active {
    @apply bg-blue-100 text-blue-700;
}

a {
    text-decoration: none;
}

a:hover {
    text-decoration: none;
}
</style>
