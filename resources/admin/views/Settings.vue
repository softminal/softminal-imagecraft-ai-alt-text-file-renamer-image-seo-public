<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $t('Settings') }}</h1>

    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-48 shrink-0">
        <nav class="bg-white rounded-lg shadow border overflow-hidden sticky top-6">
          <router-link
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            :class="[
              'flex items-center gap-2.5 w-full text-left px-4 py-3 text-sm border-b last:border-b-0 transition !shadow-none focus:outline-none focus:shadow-none',
              isActive(item.to)
                ? 'bg-blue-50 text-blue-700 border-l-2 border-l-blue-600'
                : 'text-gray-600 hover:bg-gray-50 border-l-2 border-l-transparent'
            ]"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon"></svg>
            {{ item.label }}
          </router-link>
        </nav>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <router-view />

        <div v-if="!isActive('/settings/api')" class="mt-4">
          <button
            @click="saveSettings"
            class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700"
          >
            {{ $t('Save Settings') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, provide, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { clearSettingsCache } from '../router'
import { $t } from '../utils/i18n'

const route = useRoute()

const navItems = [
  { to: '/settings/general', label: 'General', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' },
  { to: '/settings/api', label: 'API', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>' },
  { to: '/settings/alt-text', label: 'Alt Text', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>' },
  { to: '/settings/title', label: 'Image Title', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>' },
  { to: '/settings/rename', label: 'File Renaming', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>' },
  { to: '/settings/woocommerce', label: 'WooCommerce', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>' },
  { to: '/settings/seo', label: 'SEO', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>' },
]

const isActive = (path) => route.path === path || (path === '/settings/general' && route.path === '/settings')

const settings = ref({})
const apiKey = ref('')
const apiSetting = ref(null)
const saving = ref(false)
const testing = ref(false)
const testResult = ref(null)
const woocommerceInstalled = ref(false)
const seoPlugin = ref(null)
const availableModels = ref({})
const availableLanguages = ref({})
const availablePostTypes = ref({})
const excludedPostTypes = ref([])

const sortedLanguages = computed(() => {
  const entries = Object.entries(availableLanguages.value)
  entries.sort((a, b) => a[1].localeCompare(b[1]))
  return Object.fromEntries(entries)
})

const currentModels = computed(() => {
  const provider = settings.value.icais_api_provider
  return availableModels.value[provider] || []
})

const modelSettingKey = computed(() => {
  const provider = settings.value.icais_api_provider
  return 'icais_' + provider + '_model'
})

const showModelSelector = computed(() => {
  return currentModels.value.length > 1
})

const providerDocs = {
  anthropic: { url: 'https://console.anthropic.com/settings/keys', label: 'Anthropic Console', pricingUrl: 'https://www.anthropic.com/pricing#702702' },
  openai: { url: 'https://platform.openai.com/api-keys', label: 'OpenAI Platform', pricingUrl: 'https://openai.com/api/pricing/' },
  gemini: { url: 'https://aistudio.google.com/apikey', label: 'Google AI Studio', pricingUrl: 'https://ai.google.dev/pricing' },
}

const apiDocsUrl = computed(() => {
  const provider = settings.value.icais_api_provider
  return providerDocs[provider]?.url || null
})

const pricingUrl = computed(() => {
  const provider = settings.value.icais_api_provider
  return providerDocs[provider]?.pricingUrl || null
})

const apiDocsLabel = computed(() => {
  const provider = settings.value.icais_api_provider
  return providerDocs[provider]?.label || ''
})

const loadSettings = async () => {
  try {
    const result = await Api.get('/settings')
    if (result.success) {
      settings.value = result.settings
      apiSetting.value = result.api_setting
      seoPlugin.value = result.seo_plugin || null
      availableModels.value = result.available_models || {}
      availableLanguages.value = result.available_languages || {}
      availablePostTypes.value = result.available_post_types || {}
      woocommerceInstalled.value = result.woo_active || false

      try {
        const parsed = JSON.parse(result.settings.icais_excluded_post_types || '[]')
        excludedPostTypes.value = Array.isArray(parsed) ? parsed : []
      } catch (e) {
        excludedPostTypes.value = []
      }

      if (result.api_setting && result.api_setting.is_active) {
        settings.value.icais_api_provider = result.api_setting.provider
      }
    }
  } catch (error) {
    notification.error($t('Failed to load settings'))
  }
}

const saveSettings = async () => {
  saving.value = true

  settings.value.icais_excluded_post_types = JSON.stringify(excludedPostTypes.value)

  try {
    const result = await Api.post('/settings', {
      settings: settings.value
    })
    if (result.success) {
      notification.success($t('Settings saved successfully'))
      clearSettingsCache()

      // Reload page so PHP-rendered configs (window.icais) refresh for all components
      setTimeout(() => window.location.reload(), 500)
    } else {
      notification.error(result.message || $t('Failed to save settings'))
    }
  } catch (error) {
    notification.error($t('Failed to save settings'))
  } finally {
    saving.value = false
  }
}

const saveApiKey = async () => {
  if (!apiKey.value) {
    notification.error($t('Please enter an API key'))
    return
  }

  try {
    const result = await Api.post('/settings/api-key', {
      provider: settings.value.icais_api_provider,
      api_key: apiKey.value
    })
    if (result.success) {
      notification.success($t('API key saved successfully'))
      setTimeout(() => window.location.reload(), 1000)
    } else {
      notification.error(result.message || $t('Failed to save API key'))
    }
  } catch (error) {
    notification.error($t('Failed to save API key'))
  }
}

const testConnection = async () => {
  if (!apiKey.value) {
    notification.error($t('Please enter an API key'))
    return
  }

  testing.value = true
  testResult.value = null

  try {
    const payload = {
      provider: settings.value.icais_api_provider,
      api_key: apiKey.value
    }
    if (showModelSelector.value) {
      payload.model = settings.value[modelSettingKey.value]
    }
    const result = await Api.post('/settings/test-api', payload)
    testResult.value = result
    if (result.success) {
      notification.success($t('Connection successful!'))
    } else {
      notification.error(result.error || $t('Connection failed'))
    }
  } catch (error) {
    testResult.value = { success: false, error: error.message }
    notification.error($t('Failed to test connection'))
  } finally {
    testing.value = false
  }
}

// Provide shared state to child route components
provide('settingsContext', {
  settings,
  apiKey,
  apiSetting,
  testing,
  testResult,
  woocommerceInstalled,
  seoPlugin,
  sortedLanguages,
  availablePostTypes,
  excludedPostTypes,
  currentModels,
  modelSettingKey,
  showModelSelector,
  apiDocsUrl,
  apiDocsLabel,
  pricingUrl,
  testConnection,
  saveApiKey,
})

onMounted(() => {
  loadSettings()
})
</script>
