<template>
  <!-- Standalone host for a Performance-pillar feature page. Reuses the SAME
       settings components the Settings area renders (no duplication) by
       providing a compatible `settingsContext` fetched directly from the API.
       AUTO-SAVE: every change persists immediately (debounced) — no Save
       button; a switch should just work. -->
  <div class="p-6 bg-ic-page min-h-screen">
    <UiPageHead :title="$t(head.title)" :description="$t(head.description)" />

    <div class="max-w-3xl">
      <div v-if="loading" class="bg-ic-card rounded-card shadow-card border border-ic-border p-6">
        <UiSkeleton :lines="5" height="1.1rem" />
      </div>

      <component :is="featureComponent" v-else />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, provide, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { clearSettingsCache } from '../router'
import { UiPageHead, UiSkeleton } from '../@ui'
import { $t } from '../utils/i18n'
import NextGenSettings from '../components/Settings/NextGenSettings.vue'
import FallbackSettings from '../components/Settings/FallbackSettings.vue'

const route = useRoute()

const FEATURES = {
  nextgen: {
    component: NextGenSettings,
    title: 'Next-gen Formats',
    description: 'Create WebP/AVIF copies of your images and serve them to supporting browsers — originals stay untouched.',
  },
  fallbacks: {
    component: FallbackSettings,
    title: 'Image Fallbacks',
    description: 'Never show a broken image — pick a fallback that covers broken and missing images automatically.',
  },
}

const feature = computed(() => FEATURES[route.meta.feature] || FEATURES.nextgen)
const featureComponent = computed(() => feature.value.component)
const head = computed(() => feature.value)

const settings = ref({})
const availablePostTypes = ref({})
const loading = ref(true)

// Same context shape Settings.vue provides — the shared child components
// only consume `settings` and `availablePostTypes`.
provide('settingsContext', { settings, availablePostTypes })

// ---- Auto-save: debounced deep watch, armed only after the initial load ----
let ready = false
let debounceTimer = null
let saveQueued = false
let saving = false

const persist = async () => {
  if (saving) {
    saveQueued = true
    return
  }
  saving = true
  try {
    const result = await Api.post('/settings', { settings: settings.value })
    if (result.success) {
      clearSettingsCache()
      notification.success($t('Settings saved'))
    } else {
      notification.error(result.message || $t('Failed to save settings'))
    }
  } catch {
    notification.error($t('Failed to save settings'))
  } finally {
    saving = false
    if (saveQueued) {
      saveQueued = false
      persist()
    }
  }
}

watch(settings, () => {
  if (!ready) return
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(persist, 500)
}, { deep: true })

onBeforeUnmount(() => {
  // Don't lose a change made right before navigating away.
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    if (ready) persist()
  }
})

const load = async () => {
  try {
    const result = await Api.get('/settings')
    settings.value = result.settings || {}
    availablePostTypes.value = result.available_post_types || {}
  } catch {
    notification.error($t('Failed to load settings'))
  } finally {
    loading.value = false
    // Arm the auto-save only after the initial assignment has flushed,
    // so loading the page never triggers a save.
    await nextTick()
    ready = true
  }
}

onMounted(load)
</script>
