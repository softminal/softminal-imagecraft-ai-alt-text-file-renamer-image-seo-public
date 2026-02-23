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
          <option value="mistral">{{ $t('Mistral AI (Pixtral)') }}</option>
          <option value="openrouter">{{ $t('OpenRouter') }}</option>
        </select>
      </div>

      <div v-if="settings.icais_api_provider === 'openrouter'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('OpenRouter Model') }}</label>
        <select
          v-model="settings.icais_openrouter_model"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="anthropic/claude-3.5-sonnet">Anthropic Claude 3.5 Sonnet</option>
          <option value="openai/gpt-4o">OpenAI GPT-4o</option>
          <option value="google/gemini-2.0-flash-exp">Google Gemini 2.0 Flash</option>
          <option value="mistralai/pixtral-large-latest">Mistral Pixtral Large</option>
          <option value="meta-llama/llama-3.2-90b-vision-instruct">Meta Llama 3.2 90B Vision</option>
          <option value="qwen/qwen-2.5-vl-72b-instruct">Qwen 2.5 VL 72B</option>
        </select>
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
        <button
          @click="saveApiKey"
          class="mt-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          {{ $t('Save API Key') }}
        </button>
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
          <option value="en">{{ $t('English') }}</option>
          <option value="es">{{ $t('Spanish') }}</option>
          <option value="fr">{{ $t('French') }}</option>
          <option value="de">{{ $t('German') }}</option>
          <option value="it">{{ $t('Italian') }}</option>
          <option value="pt">{{ $t('Portuguese') }}</option>
          <option value="nl">{{ $t('Dutch') }}</option>
          <option value="ru">{{ $t('Russian') }}</option>
          <option value="ja">{{ $t('Japanese') }}</option>
          <option value="zh">{{ $t('Chinese') }}</option>
          <option value="ko">{{ $t('Korean') }}</option>
          <option value="ar">{{ $t('Arabic') }}</option>
          <option value="hi">{{ $t('Hindi') }}</option>
          <option value="bn">{{ $t('Bengali') }}</option>
          <option value="tr">{{ $t('Turkish') }}</option>
          <option value="pl">{{ $t('Polish') }}</option>
          <option value="sv">{{ $t('Swedish') }}</option>
          <option value="da">{{ $t('Danish') }}</option>
          <option value="fi">{{ $t('Finnish') }}</option>
          <option value="no">{{ $t('Norwegian') }}</option>
          <option value="th">{{ $t('Thai') }}</option>
          <option value="vi">{{ $t('Vietnamese') }}</option>
          <option value="id">{{ $t('Indonesian') }}</option>
          <option value="ms">{{ $t('Malay') }}</option>
          <option value="uk">{{ $t('Ukrainian') }}</option>
          <option value="cs">{{ $t('Czech') }}</option>
          <option value="el">{{ $t('Greek') }}</option>
          <option value="he">{{ $t('Hebrew') }}</option>
          <option value="ro">{{ $t('Romanian') }}</option>
          <option value="hu">{{ $t('Hungarian') }}</option>
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
const woocommerceInstalled = ref(false)
const seoPlugin = ref(null)

const providerDocs = {
  anthropic: { url: 'https://console.anthropic.com/settings/keys', label: 'Anthropic Console' },
  openai: { url: 'https://platform.openai.com/api-keys', label: 'OpenAI Platform' },
  gemini: { url: 'https://aistudio.google.com/apikey', label: 'Google AI Studio' },
  mistral: { url: 'https://console.mistral.ai/api-keys', label: 'Mistral Console' },
  openrouter: { url: 'https://openrouter.ai/keys', label: 'OpenRouter' },
}

const apiDocsUrl = computed(() => {
  const provider = settings.value.icais_api_provider
  return providerDocs[provider]?.url || null
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

      if (result.api_setting && result.api_setting.is_active) {
        settings.value.icais_api_provider = result.api_setting.provider
      }
    }

    try {
      const wooStats = await Api.get('/woocommerce/stats')
      woocommerceInstalled.value = wooStats.is_active || false
    } catch (e) {
      woocommerceInstalled.value = false
    }
  } catch (error) {
    notification.error($t('Failed to load settings'))
  }
}

const saveSettings = async () => {
  saving.value = true;
  try {
    const result = await Api.post('/settings', {
      settings: settings.value
    })
    if (result.success) {
      notification.success($t('Settings saved successfully'))
      clearSettingsCache()
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
      apiKey.value = ''
      clearSettingsCache()
      loadSettings()
    }
  } catch (error) {
    notification.error($t('Failed to save API key'))
  }
}

onMounted(() => {
  loadSettings()
})
</script>
