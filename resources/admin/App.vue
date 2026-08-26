<template>
    <Navbar />
    <SectionBar :woo-active="wooActive" />
    <router-view></router-view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Navbar from './components/Navbar.vue'
import SectionBar from './components/SectionBar.vue'
import { getSettingsCache, getSettings } from './router'

const wooActive = ref(false)
const isAdmin = window.icais?.isAdmin ?? false

onMounted(async () => {
    if (!isAdmin) return
    try {
        const settings = getSettingsCache() || await getSettings()
        wooActive.value = !!settings.woo_active
    } catch {
        // Optional enhancement only — the sub-bar just omits WooCommerce.
    }
})
</script>
