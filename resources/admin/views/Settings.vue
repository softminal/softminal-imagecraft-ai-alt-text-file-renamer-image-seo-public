<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $t('Settings') }}</h1>

    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('API Configuration') }}</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Provider') }}</label>
        <select
          v-model="settings.satai_api_provider"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="anthropic">{{ $t('Anthropic Claude') }}</option>
          <option value="openai">{{ $t('OpenAI GPT-4') }}</option>
          <option value="gemini">{{ $t('Google Gemini') }}</option>
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
          v-model="settings.satai_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="professional">{{ $t('Professional') }}</option>
          <option value="casual">{{ $t('Casual') }}</option>
          <option value="technical">{{ $t('Technical') }}</option>
          <option value="seo">{{ $t('SEO-Optimized') }}</option>
          <option value="custom">{{ $t('Custom Prompt') }}</option>
        </select>
      </div>

      <div v-if="settings.satai_default_tone === 'custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Custom Prompt') }}</label>
        <textarea
          v-model="settings.satai_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          :placeholder="$t('e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with \'Image of\'. Return only the alt text.')"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Use {max_length} as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Max Character Length') }}</label>
        <input
          v-model.number="settings.satai_max_length"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="50"
          max="200"
        />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Language') }}</label>
        <select
          v-model="settings.satai_language"
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
            v-model="settings.satai_auto_generate"
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
            v-model="settings.satai_preview_before_accept"
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
            v-model="settings.satai_skip_existing"
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
            v-model="settings.satai_auto_rename"
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
          v-model.number="settings.satai_rename_max_length"
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
          v-model.number="settings.satai_batch_size"
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
          v-model.number="settings.satai_request_timeout"
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
            v-model="settings.satai_woo_auto_generate"
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
            v-model="settings.satai_woo_include_product_info"
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
          v-model="settings.satai_woo_default_tone"
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

      <div v-if="settings.satai_woo_default_tone === 'woo_custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Custom WooCommerce Prompt') }}</label>
        <textarea
          v-model="settings.satai_woo_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          :placeholder="$t('e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with \'Image of\'. Return only the alt text.')"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Use {max_length} as a placeholder. Product name, category, and SKU are automatically included as context.') }}
        </p>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('WooCommerce Settings') }}</h2>
      <p class="text-sm text-gray-600">
        {{ $t('WooCommerce is not installed. Install WooCommerce to access product image optimization features.') }}
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
import { ref, onMounted } from 'vue'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { clearSettingsCache } from '../router'
import { $t } from '../utils/i18n'

const settings = ref({})
const apiKey = ref('')
const apiSetting = ref(null)
const saving = ref(false)
const woocommerceInstalled = ref(false)

const loadSettings = async () => {
  try {
    const result = await Api.get('/settings')
    if (result.success) {
      settings.value = result.settings
      apiSetting.value = result.api_setting

      if (result.api_setting && result.api_setting.is_active) {
        settings.value.satai_api_provider = result.api_setting.provider
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
      provider: settings.value.satai_api_provider,
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
