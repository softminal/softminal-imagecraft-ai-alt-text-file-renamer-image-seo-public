<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $t('Settings') }}</h1>

    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('API Configuration') }}</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Provider') }}</label>
        <select
          v-model="settings.icais_api_provider"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="anthropic">{{ $t('Anthropic Claude') }}</option>
          <option value="openai">{{ $t('OpenAI GPT-4') }}</option>
          <option value="gemini">{{ $t('Google Gemini') }}</option>
        </select>
      </div>

      <div v-if="showModelSelector" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Model') }}</label>
        <select
          v-model="settings[modelSettingKey]"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option v-for="model in currentModels" :key="model" :value="model">{{ model }}</option>
        </select>
        <p v-if="pricingUrl" class="text-xs text-gray-500 mt-1">
          <a :href="pricingUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
            {{ $t('View pricing') }} &rarr;
          </a>
        </p>
      </div>


      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('API Key') }}</label>
        <input
          v-model="apiKey"
          type="password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          :placeholder="$t('Enter API key')"
        />
        <p v-if="apiDocsUrl" class="text-xs text-gray-500 mt-1">
          {{ $t('Get your API key from') }}
          <a :href="apiDocsUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
            {{ apiDocsLabel }} &rarr;
          </a>
        </p>
        <div class="mt-2 flex items-center gap-3">
          <button
            @click="testConnection"
            :disabled="!apiKey || testing"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 disabled:opacity-50"
          >
            {{ testing ? $t('Testing...') : $t('Test Connection') }}
          </button>
          <button
            @click="saveApiKey"
            :disabled="!apiKey || !testResult?.success"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ $t('Save API Key') }}
          </button>
        </div>
        <div v-if="testResult" class="mt-2">
          <div v-if="testResult.success" class="bg-green-50 border border-green-200 text-green-800 p-3 rounded text-sm">
            {{ $t('Connection successful!') }}
          </div>
          <div v-else class="bg-red-50 border border-red-200 text-red-800 p-3 rounded text-sm">
            {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
          </div>
        </div>
      </div>

      <div v-if="apiSetting" class="text-sm text-gray-600">
        <div>{{ $t('Status:') }} {{ apiSetting.is_active ? $t('Active') : $t('Inactive') }}</div>
        <div>{{ $t('Provider:') }} {{ apiSetting.provider }}</div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('Generation Settings') }}</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Default Tone') }}</label>
        <select
          v-model="settings.icais_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="professional">{{ $t('Professional') }}</option>
          <option value="casual">{{ $t('Casual') }}</option>
          <option value="technical">{{ $t('Technical') }}</option>
          <option value="seo">{{ $t('SEO-Optimized') }}</option>
          <option value="custom">{{ $t('Custom Prompt') }}</option>
        </select>
      </div>

      <div v-if="settings.icais_default_tone === 'custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Custom Prompt') }}</label>
        <textarea
          v-model="settings.icais_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          :placeholder="$t('e.g. Generate alt text describing the subject, colors, and setting. Do not start with \'Image of\'. Return only the alt text.')"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('The max character length setting above will be applied automatically. Product context is added automatically for WooCommerce images.') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Max Character Length') }}</label>
        <input
          v-model.number="settings.icais_max_length"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="50"
          max="200"
        />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Language') }}</label>
        <select
          v-model="settings.icais_language"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option v-for="(name, code) in sortedLanguages" :key="code" :value="code">{{ $t(name) }}</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Alt text will be generated in the selected language') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_auto_generate"
          />
          <span>{{ $t('Auto-generate on upload') }}</span>
        </label>
      </div>

      <!-- Post type exclusion (only visible when auto-generate is on) -->
      <div v-if="settings.icais_auto_generate === '1' || settings.icais_auto_generate === 1 || settings.icais_auto_generate === true" class="mb-4 ml-6 p-3 bg-gray-50 rounded-lg border">
        <p class="text-sm font-medium text-gray-700 mb-2">{{ $t('Exclude post types from auto-generation') }}</p>
        <p class="text-xs text-gray-500 mb-3">{{ $t('Images uploaded to these post types will not be auto-generated.') }}</p>
        <div class="flex flex-wrap gap-3">
          <label
            v-for="(label, slug) in availablePostTypes"
            :key="slug"
            class="inline-flex items-center gap-1.5 cursor-pointer"
          >
            <input
              type="checkbox"
              :value="slug"
              v-model="excludedPostTypes"
              class="rounded"
            />
            <span class="text-sm text-gray-700">{{ label }}</span>
          </label>
        </div>
        <p v-if="Object.keys(availablePostTypes).length === 0" class="text-xs text-gray-400">
          {{ $t('No post types available') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('What to generate') }}</label>
        <div class="space-y-2">
          <label class="flex items-center gap-2">
            <input type="radio" value="0" v-model="settings.icais_generate_title" />
            <div>
              <span class="text-sm">{{ $t('Alt text only') }}</span>
              <p class="text-xs text-gray-500">{{ $t('Generates image alt text for accessibility and SEO.') }}</p>
            </div>
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" value="1" v-model="settings.icais_generate_title" />
            <div>
              <span class="text-sm">{{ $t('Alt text + Image title') }}</span>
              <p class="text-xs text-gray-500">{{ $t('Also generates an SEO-optimized image title. Same API cost.') }}</p>
            </div>
          </label>
        </div>
      </div>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_preview_before_accept"
          />
          <span>{{ $t('Preview before accepting') }}</span>
        </label>
      </div>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_skip_existing"
          />
          <span>{{ $t('Skip images with existing alt text') }}</span>
        </label>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('File Renaming') }}</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Rename Mode') }}</label>
        <div class="space-y-2">
          <label class="flex items-center gap-2">
            <input type="radio" value="title_only" v-model="settings.icais_rename_mode" />
            <div>
              <span class="text-sm">{{ $t('Title only') }}</span>
              <span class="text-xs text-green-600 ml-1">{{ $t('(recommended)') }}</span>
              <p class="text-xs text-gray-500">{{ $t('Updates the image title in your media library. Does not change file URLs.') }}</p>
            </div>
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" value="title_and_filename" v-model="settings.icais_rename_mode" />
            <div>
              <span class="text-sm">{{ $t('Title and filename') }}</span>
              <p class="text-xs text-gray-500">{{ $t('Updates both the title and the actual filename. This changes image URLs.') }}</p>
            </div>
          </label>
        </div>
        <div v-if="settings.icais_rename_mode === 'title_and_filename'" class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
          <p class="text-xs text-amber-700">
            {{ $t('Changing filenames changes image URLs. External links and cached pages may show broken images. Use with caution.') }}
          </p>
        </div>
      </div>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_auto_rename"
          />
          <span>{{ $t('Auto-rename files on upload') }}</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Rename uploaded files to SEO-friendly names based on generated alt text') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Max Filename Length') }}</label>
        <input
          v-model.number="settings.icais_rename_max_length"
          type="number"
          min="50"
          max="150"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Characters in filename (default: 120)') }}
        </p>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border p-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('Advanced Settings') }}</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Batch Size') }}</label>
        <input
          v-model.number="settings.icais_batch_size"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="1"
          max="20"
        />
        <p class="text-xs text-gray-500 mt-1">{{ $t('Number of images to process per batch') }}</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Request Timeout (seconds)') }}</label>
        <input
          v-model.number="settings.icais_request_timeout"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="30"
          max="120"
        />
      </div>
    </div>

    <div v-if="woocommerceInstalled" class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('WooCommerce Settings') }}</h2>
      <p class="text-sm text-gray-600 mb-4">{{ $t('Configure alt text generation for WooCommerce products') }}</p>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_woo_auto_generate"
          />
          <span>{{ $t('Auto-generate on product upload') }}</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Automatically generate alt text when adding product images') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_woo_include_product_info"
          />
          <span>{{ $t('Include product information') }}</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Use product name, categories, and SKU in alt text generation') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Default Tone for Products') }}</label>
        <select
          v-model="settings.icais_woo_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <optgroup :label="$t('WooCommerce Optimized')">
            <option value="woo_seo">{{ $t('Product SEO (Recommended)') }}</option>
            <option value="woo_descriptive">{{ $t('Product Descriptive (Accessibility)') }}</option>
            <option value="woo_lifestyle">{{ $t('Product Lifestyle (In-Use Photos)') }}</option>
            <option value="woo_custom">{{ $t('Custom Prompt') }}</option>
          </optgroup>
          <optgroup :label="$t('General')">
            <option value="professional">{{ $t('Professional') }}</option>
            <option value="casual">{{ $t('Casual') }}</option>
            <option value="technical">{{ $t('Technical') }}</option>
            <option value="seo">{{ $t('SEO-Optimized') }}</option>
          </optgroup>
        </select>
      </div>

      <div v-if="settings.icais_woo_default_tone === 'woo_custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Custom WooCommerce Prompt') }}</label>
        <textarea
          v-model="settings.icais_woo_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          :placeholder="$t('e.g. Generate product alt text with the product name, visible features, and use case. Do not start with \'Image of\'. Return only the alt text.')"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('The max character length setting will be applied automatically. Product name, category, and SKU are automatically included as context.') }}
        </p>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('WooCommerce Settings') }}</h2>
      <p class="text-sm text-gray-600">
        {{ $t('WooCommerce is not installed. Install WooCommerce to access product image optimization features.') }}
      </p>
    </div>

    <div v-if="seoPlugin?.active" class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('SEO Integration') }}</h2>
      <p class="text-sm text-gray-600 mb-4">
        {{ $t('Use your focus keyphrase to improve alt text SEO relevance.') }}
      </p>
      <p class="text-sm text-green-700 font-medium mb-4">
        {{ $t('Detected:') }} {{ seoPlugin.label }}
      </p>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_seo_integration"
          />
          <span>{{ $t('Enable SEO integration') }}</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('When enabled, the plugin will read SEO data from your SEO plugin to enhance alt text generation.') }}
        </p>
      </div>

      <div v-if="settings.icais_seo_integration === '1' || settings.icais_seo_integration === 1" class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_seo_use_keyphrase"
          />
          <span>{{ $t('Include focus keyphrase in alt text') }}</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('The AI will naturally incorporate the focus keyphrase from the parent post/page into the generated alt text. The image must be attached to a post that has a focus keyphrase set.') }}
        </p>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('SEO Integration') }}</h2>
      <p class="text-sm text-gray-600">
        {{ $t('No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.') }}
      </p>
    </div>

    <div class="mt-6">
      <button
        @click="saveSettings"
        class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700"
      >
        {{ $t('Save Settings') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { clearSettingsCache } from '../router'
import { $t } from '../utils/i18n'

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

      // Parse excluded post types from JSON string
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
  saving.value = true;

  // Sync excluded post types array into settings as JSON before saving
  settings.value.icais_excluded_post_types = JSON.stringify(excludedPostTypes.value)

  try {
    const result = await Api.post('/settings', {
      settings: settings.value
    })
    if (result.success) {
      notification.success($t('Settings saved successfully'))
      clearSettingsCache()
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

onMounted(() => {
  loadSettings()
})
</script>
