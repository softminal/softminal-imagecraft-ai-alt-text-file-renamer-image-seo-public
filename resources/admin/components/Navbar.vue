<template>
    <nav class="main_nav_soft_carousel bg-white border-b border-gray-200 px-4 py-2.5 left-0 right-0 top-0 z-50">
        <div class="flex flex-wrap justify-between items-center">
            <!-- Logo Section -->
            <router-link to="/" class="flex items-center bg-inherit outline-hidden focus:outline-hidden focus:shadow-none">
                <img :src="asset('imagecraft-logo.png')" alt="ImageCraft" class="h-12" />
            </router-link>

            <!-- Navigation Menu -->
            <div v-if="menuItems && menuItems.length > 0" class="flex items-center gap-1">
                <router-link
                    v-for="(item, index) in menuItems"
                    :key="index"
                    :to="item.path"
                    class="nav-link px-4 py-2 text-sm outline-hidden focus:shadow-none focus:ring-0 focus:border-transparent font-medium text-gray-700 rounded-lg hover:bg-blue-100 transition-colors"
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
import { getSettingsCache, getSettings } from '../router'
import { useAsset } from '../composables/useAsset'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'

const { asset } = useAsset()

const route = useRoute()

const menuItems = ref([
    { name: 'Dashboard', path: '/' },
    { name: 'Scanner', path: '/scanner' },
])

const isActive = (path) => {
    const currentPath = route.path.replace(/^#/, '')
    const checkPath = path.replace(/^#/, '')
    return currentPath === checkPath || currentPath === checkPath + '/'
}

onMounted(async () => {
    try {
        // Use cached settings from router guard (already fetched) or fetch fresh
        const cached = getSettingsCache()
        const settings = cached || await getSettings()
        if (settings.woo_active) {
            menuItems.value.push({ name: 'WooCommerce', path: '/woocommerce', icon: '🛒' })
        }
       
    } catch {
        notification.error($t('Failed to load settings'))
    } finally {
        menuItems.value.push({ name: 'Settings', path: '/settings' })
        menuItems.value.push({ name: 'Help', path: '/help' })
    }
})
</script>

<style scoped>
@reference "tailwindcss";
.nav-link.router-link-active {
    @apply bg-blue-100 text-blue-700;
}

a {
    text-decoration: none;
}

a:hover {
    text-decoration: none;
}

.nav-link {
    color: #6b7280;
}

.nav-link:focus,
.nav-link:active {
    outline: none;
    box-shadow: none;
    border-color: transparent;
}
</style>
