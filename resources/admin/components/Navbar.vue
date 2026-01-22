<template>
    <nav class="main_nav_soft_carousel bg-white border-b border-gray-200 px-4 py-2.5 left-0 right-0 top-0 z-50">
        <div class="flex flex-wrap justify-between items-center">
            <!-- Logo Section -->
            <div class="flex items-center">
                <span class="self-center text-xl font-semibold whitespace-nowrap text-red-500">
                    WP AI Alt Text
                </span>
            </div>

            <!-- Navigation Menu -->
            <div class="flex items-center gap-1">
                <router-link 
                    v-for="(item, index) in menuItems" 
                    :key="index"
                    :to="item.path"
                    class="px-4 py-2 text-sm outline-none focus:shadow-none font-medium text-gray-700 rounded-lg hover:bg-blue-100 transition-colors"
                    :class="{ 'bg-blue-100': isActive(item.path) }"
                >
                    {{ item.name }}
                </router-link>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const menuItems = ref([
    { name: 'Dashboard', path: '/' },
    // { name: 'Settings', path: '/settings' },
    // { name: 'Help', path: '/help' }
])

const isActive = (path) => {
    return route.path === path
}
</script>

<style scoped>
.router-link-active {
    @apply bg-blue-100;
}
</style>