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

                <!-- Get Pro Button -->
                <a
                    v-if="!isPro"
                    :href="proUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="get-pro-btn ml-2 inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-amber-500 rounded-lg shadow-sm hover:bg-amber-600 transition-colors outline-hidden focus:shadow-none"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.286-3.957z" />
                    </svg>
                    {{ $t('Get Pro') }}
                </a>
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
    { name: 'Image SEO', path: '/scanner' },
    { name: 'Compress', path: '/compression' },
])

const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'
const isPro = window.icais?.isPro ?? false

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
    border-radius: 0.5rem;
}

.nav-link:focus,
.nav-link:active {
    outline: none;
    box-shadow: none;
    border-color: transparent;
    border-radius: 0.5rem;
}

.get-pro-btn,
.get-pro-btn:hover,
.get-pro-btn:focus,
.get-pro-btn:active {
    color: #ffffff;
}
</style>
