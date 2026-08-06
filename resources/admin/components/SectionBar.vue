<template>
    <div
        v-if="screens.length > 1"
        class="bg-white border-b border-ic-border-soft px-4 py-2"
    >
        <div class="flex items-center gap-1.5">
            <router-link
                v-for="screen in screens"
                :key="screen.path"
                :to="screen.path"
                :title="$t(screen.description)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-pill text-[0.8125rem] font-medium transition-colors outline-hidden focus:shadow-none"
                :class="isCurrent(screen)
                    ? (pillar === 'perf' ? 'bg-pillar-perf-soft text-pillar-perf' : 'bg-brand-soft text-brand-soft-text')
                    : 'text-ic-soft hover:bg-ic-muted hover:text-ic-text'"
            >
                <component :is="screen.icon" :size="14" :stroke-width="2" aria-hidden="true" />
                {{ $t(screen.name) }}
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { pillarForPath, pillarScreens, visibleItems } from '../config/navigation'
import { $t } from '../utils/i18n'

const route = useRoute()

const isAdmin = window.icais?.isAdmin ?? false

const props = defineProps({
    wooActive: { type: Boolean, default: false },
})

const pillar = computed(() => pillarForPath(route.path))

const screens = computed(() => {
    if (!pillar.value) return []
    return visibleItems(pillarScreens[pillar.value] ?? [], {
        isAdmin,
        wooActive: props.wooActive,
    })
})

// Exact match only (sibling screens share the /performance prefix); the
// Compress pill also owns the legacy /compression path.
const isCurrent = (screen) => route.path === screen.path
    || (screen.path === '/performance' && route.path.startsWith('/compression'))
</script>

<style scoped>
/* Quiet pills: suppress the WP-admin default link focus ring/box-shadow
   (same treatment as the Navbar links). */
a {
    text-decoration: none;
}
a:focus,
a:active {
    outline: none;
    box-shadow: none;
}
</style>
