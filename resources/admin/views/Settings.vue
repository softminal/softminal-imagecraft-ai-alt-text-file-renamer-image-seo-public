<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6 mt-0!">{{ $t('Settings') }}</h1>

    <div class="flex gap-6">
      <!-- Sidebar — driven by config/navigation.js (single source of truth) -->
      <div class="w-60 shrink-0">
        <nav class="bg-ic-card rounded-card shadow-card border border-ic-border overflow-hidden sticky top-6">
          <template v-for="section in settingsSections" :key="section.section">
            <div
              class="px-4 pt-3 pb-1.5 text-xs font-semibold uppercase tracking-wider"
              :class="section.pillar === 'perf' ? 'text-pillar-perf' : section.pillar === 'seo' ? 'text-brand-soft-text' : 'text-ic-faint'"
            >
              {{ $t(section.section) }}
            </div>
            <template v-for="item in section.items" :key="item.path">
              <router-link
                :to="item.path"
                class="flex items-start gap-2.5 w-full text-left px-4 py-2.5 transition shadow-none! focus:outline-hidden focus:shadow-none border-l-2"
                :class="isActive(item.path)
                  ? 'bg-brand-soft border-l-brand'
                  : 'border-l-transparent hover:bg-ic-muted'"
              >
                <component
                  :is="item.icon"
                  :size="16"
                  :stroke-width="2"
                  class="shrink-0 mt-0.5"
                  :class="isActive(item.path) ? 'text-brand' : 'text-ic-faint'"
                  aria-hidden="true"
                />
                <span class="min-w-0">
                  <span class="block text-sm font-medium" :class="isActive(item.path) ? 'text-brand-soft-text' : 'text-ic-text'">
                    {{ $t(item.name) }}
                  </span>
                  <span class="block text-xs text-ic-soft truncate">{{ $t(item.description) }}</span>
                </span>
              </router-link>
              <!-- Visible sub-items (e.g. Integrations → WooCommerce / SEO) -->
              <router-link
                v-for="child in item.children || []"
                :key="child.path"
                :to="child.path"
                class="flex items-center gap-2 w-full text-left pl-11 pr-4 py-1.5 text-[0.8125rem] transition shadow-none! focus:outline-hidden focus:shadow-none border-l-2"
                :class="isActive(child.path)
                  ? 'bg-brand-soft border-l-brand text-brand-soft-text font-medium'
                  : 'border-l-transparent text-ic-soft hover:bg-ic-muted hover:text-ic-text'"
              >
                <component :is="child.icon" :size="13" :stroke-width="2" class="shrink-0" aria-hidden="true" />
                {{ $t(child.name) }}
              </router-link>
            </template>
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
            class="bg-brand text-white px-6 py-3 rounded-ic font-semibold hover:bg-brand-strong disabled:opacity-50 disabled:cursor-not-allowed"
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
import { settingsSections } from '../config/navigation'
import { $t } from '../utils/i18n'

const route = useRoute()

const isActive = (path) => {
  if (path === '/settings/general' && route.path === '/settings') return true
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
      } else if (!result.config_api_key) {
        // Nothing connected yet (no saved key, no wp-config key) — show the
        // "Select Provider" placeholder instead of implying a default provider.
        settings.value.icais_api_provider = ''
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
  if (!settings.value.icais_api_provider) {
    notification.error($t('Please select a provider first'))
    return
  }
  if (!apiKey.value) {
    notification.error($t('Please enter an API key'))
    return
  }

  try {
    const result = await Api.post('/settings/api-key', {
      provider: settings.value.icais_api_provider,
      api_key: apiKey.value
    })
    if (!result.success) {
      notification.error(result.message || $t('Failed to save API key'))
      return
    }

    // The key saved. Now persist the surrounding API settings — most importantly
    // the "Use WordPress AI Client" toggle. Clicking "Save API Key" from the BYOK
    // form means the user chose their own key over the WP AI Client, so that OFF
    // state (icais_use_wp7_ai) must be saved too; otherwise the hard reload below
    // restores the old DB value and the plugin keeps using the WP AI Client.
    try {
      await Api.post('/settings', { settings: settings.value })
    } catch {
      // Non-fatal: the key itself saved. Surface nothing extra; the reload will
      // reflect whatever persisted.
    }

    notification.success($t('API key saved successfully'))
    clearSettingsCache()
    setTimeout(() => window.location.reload(), 1000)
  } catch {
    notification.error($t('Failed to save API key'))
  }
}

const testConnection = async () => {
  if (!settings.value.icais_api_provider) {
    notification.error($t('Please select a provider first'))
    return
  }
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
