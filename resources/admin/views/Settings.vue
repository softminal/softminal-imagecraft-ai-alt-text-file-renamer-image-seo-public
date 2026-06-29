<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6 mt-0!">{{ $t('Settings') }}</h1>

    <div class="flex gap-6">
      <!-- Sidebar -->
      <div class="w-48 shrink-0">
        <nav class="bg-white rounded-lg shadow border overflow-hidden sticky top-6">
          <template v-for="item in navItems" :key="item.to">
            <div
              v-if="item.section"
              class="px-4 py-2 bg-gray-100 text-xs font-bold uppercase tracking-wider text-gray-700 border-y border-gray-200"
            >
              {{ $t(item.section) }}
            </div>
            <router-link
              :to="item.to"
              :class="[
                'flex items-center gap-2.5 w-full text-left px-4 py-3 text-sm border-b last:border-b-0 transition shadow-none! focus:outline-hidden focus:shadow-none',
                isActive(item.to)
                  ? 'bg-blue-50 text-blue-700 border-l-2 border-l-blue-600'
                  : 'text-gray-600 hover:bg-gray-50 border-l-2 border-l-transparent'
              ]"
            >
              <!-- eslint-disable-next-line vue/no-v-html -->
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="item.icon"></svg>
              {{ item.label }}
            </router-link>
          </template>
        </nav>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 max-w-3xl">
        <router-view />

        <div v-if="route.path !== '/settings/integrations' && route.path !== '/settings/license'" class="mt-4">
          <button
            @click="saveSettings"
            :disabled="saving"
            class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
  { to: '/settings/general', section: 'General', label: 'General', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>' },
  { to: '/settings/api', label: 'API', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>' },
  { to: '/settings/permissions', label: 'Permissions', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>' },
  { to: '/settings/alt-text', section: 'Image SEO', label: 'Alt Text', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>' },
  { to: '/settings/title', label: 'Image Title', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>' },
  { to: '/settings/meta', label: 'Generate All', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>' },
  { to: '/settings/rename', label: 'File Renaming', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>' },
  { to: '/settings/integrations', label: 'Integrations', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"/>' },
  { to: '/settings/compression', section: 'Compression', label: 'Compression', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>' },
  { to: '/settings/license', section: 'Account', label: 'License', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>' },
]

const isActive = (path) => {
  if (path === '/settings/general' && route.path === '/settings') return true
  if (path === '/settings/integrations' && route.path.startsWith('/settings/integrations')) return true
  return route.path === path
}

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
const availableRoles = ref({})
const allowedRoles = ref([])
const configApiKey = ref(false)
const integrations = ref([])
const wp7Available = ref(false)

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
      availableRoles.value = result.available_roles || {}
      woocommerceInstalled.value = result.woo_active || false
      configApiKey.value = result.config_api_key || false
      integrations.value = result.integrations || []
      wp7Available.value = result.wp7_available || false

      try {
        const parsed = JSON.parse(result.settings.icais_excluded_post_types || '[]')
        excludedPostTypes.value = Array.isArray(parsed) ? parsed : []
      } catch {
        excludedPostTypes.value = []
      }

      try {
        const parsedRoles = JSON.parse(result.settings.icais_allowed_roles || '[]')
        allowedRoles.value = Array.isArray(parsedRoles) ? parsedRoles : []
      } catch {
        allowedRoles.value = []
      }

      if (result.api_setting && result.api_setting.is_active) {
        settings.value.icais_api_provider = result.api_setting.provider
      }
    }
  } catch {
    notification.error($t('Failed to load settings'))
  }
}

const saveSettings = async () => {
  saving.value = true

  settings.value.icais_excluded_post_types = JSON.stringify(excludedPostTypes.value)
  settings.value.icais_allowed_roles = JSON.stringify(allowedRoles.value)

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
  } catch {
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
  } catch {
    notification.error($t('Failed to save API key'))
  }
}

const testConnection = async () => {
  if (!configApiKey.value && !apiKey.value) {
    notification.error($t('Please enter an API key'))
    return
  }

  testing.value = true
  testResult.value = null

  try {
    const payload = {
      provider: settings.value.icais_api_provider,
    }
    // Only send api_key if not using config constant
    if (!configApiKey.value) {
      payload.api_key = apiKey.value
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

const testWP7Connection = async () => {
  testing.value = true
  testResult.value = null
  try {
    const result = await Api.post('/settings/test-api', { provider: 'wp7' })
    testResult.value = result
    if (result.success) {
      notification.success($t('WordPress AI Client is ready!'))
    } else {
      notification.error(result.error || result.message || $t('WordPress AI Client is not available'))
    }
  } catch (error) {
    testResult.value = { success: false, error: error.message }
    notification.error($t('Failed to test WordPress AI Client'))
  } finally {
    testing.value = false
  }
}

// Provide shared state to child route components
provide('settingsContext', {
  settings,
  apiKey,
  apiSetting,
  configApiKey,
  testing,
  testResult,
  woocommerceInstalled,
  seoPlugin,
  sortedLanguages,
  availablePostTypes,
  excludedPostTypes,
  availableRoles,
  allowedRoles,
  currentModels,
  modelSettingKey,
  showModelSelector,
  apiDocsUrl,
  apiDocsLabel,
  pricingUrl,
  testConnection,
  testWP7Connection,
  saveApiKey,
  saveSettings,
  integrations,
  wp7Available,
})

onMounted(() => {
  loadSettings()
})
</script>

<style scoped>
nav a {
    text-decoration: none;
    color: #6b7280;
}

nav a.text-blue-700 {
    color: #1d4ed8;
}

nav a:hover {
    text-decoration: none;
}

nav a:focus,
nav a:active {
    outline: none;
    box-shadow: none;
}

/* Numeric settings (batch size, timeout, max length, quality, dimensions) hold
   short values — keep their inputs compact instead of stretching full width. */
.settings :deep(input[type="number"]) {
    max-width: 160px;
}
</style>
