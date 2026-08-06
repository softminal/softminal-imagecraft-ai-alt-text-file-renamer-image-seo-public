<template>
    <nav class="main_nav_soft_carousel bg-white border-b border-ic-border px-4 py-2.5 left-0 right-0 top-0 z-50">
        <div class="flex flex-wrap justify-between items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center bg-inherit outline-hidden focus:outline-hidden focus:shadow-none">
                <img :src="asset('imagecraft-logo.png')" alt="ImageCraft" class="icais-logo h-12" />
            </router-link>

            <!-- Navigation (driven by config/navigation.js — single source of truth) -->
            <div class="flex items-center gap-1">
                <router-link
                    v-for="item in menuItems"
                    :key="item.path"
                    :to="item.path"
                    :title="$t(item.description)"
                    class="nav-link inline-flex items-center gap-1.5 px-3.5 py-2 text-sm outline-hidden focus:shadow-none focus:ring-0 focus:border-transparent font-medium rounded-ic transition-colors hover:bg-ic-muted hover:text-ic-text"
                    :class="isActive(item) ? 'bg-brand-soft text-brand-soft-text' : 'text-ic-soft'"
                >
                    <component :is="item.icon" :size="16" :stroke-width="2" aria-hidden="true" />
                    {{ $t(item.name) }}
                </router-link>

                <!-- Get Pro — single, quiet upgrade entry point -->
                <a
                    v-if="!isPro"
                    :href="proUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="get-pro-btn ml-2 inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-ic-pro bg-ic-pro-soft border border-ic-pro/20 rounded-ic hover:bg-ic-pro hover:text-white transition-colors outline-hidden focus:shadow-none"
                >
                    <Star :size="14" :stroke-width="2" aria-hidden="true" />
                    {{ $t('Get Pro') }}
                </a>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { Star } from 'lucide-vue-next'
import { topNav, visibleItems, pillarForPath } from '../config/navigation'
import { useAsset } from '../composables/useAsset'
import { $t } from '../utils/i18n'

const { asset } = useAsset()
const route = useRoute()

const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'
const isPro = window.icais?.isPro ?? false
const isAdmin = window.icais?.isAdmin ?? false


const menuItems = ref(visibleItems(topNav, { isAdmin, wooActive: false }))

const isActive = (item) => {
    if (item.path === '/') return route.path === '/'
    if (route.path.startsWith(item.path)) return true
    // The Performance tab also owns the legacy /compression path.
    return pillarForPath(route.path) === item.pillar && item.pillar === 'perf'
        && route.path.startsWith('/compression')
}

</script>

<style scoped>
a {
    text-decoration: none;
}

a:hover {
    text-decoration: none;
}

.nav-link:focus,
.nav-link:active {
    outline: none;
    box-shadow: none;
    border-color: transparent;
}
</style>
