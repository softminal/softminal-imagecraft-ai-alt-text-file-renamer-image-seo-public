<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6">Settings</h1>
    
    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">API Configuration</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Provider</label>
        <select 
          v-model="settings.satai_api_provider"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="anthropic">Anthropic Claude</option>
          <option value="openai">OpenAI GPT-4</option>
          <option value="gemini">Google Gemini</option>
        </select>
      </div>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">API Key</label>
        <input 
          v-model="apiKey"
          type="password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          placeholder="Enter API key"
        />
        <button 
          @click="saveApiKey"
          class="mt-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          Save API Key
        </button>
      </div>
      
      <div v-if="apiSetting" class="text-sm text-gray-600">
        <div>Status: {{ apiSetting.is_active ? 'Active' : 'Inactive' }}</div>
        <div>Provider: {{ apiSetting.provider }}</div>
      </div>
    </div>
    
    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Generation Settings</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Default Tone</label>
        <select 
          v-model="settings.satai_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="professional">Professional</option>
          <option value="casual">Casual</option>
          <option value="technical">Technical</option>
          <option value="seo">SEO-Optimized</option>
          <option value="custom">Custom Prompt</option>
        </select>
      </div>

      <div v-if="settings.satai_default_tone === 'custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">Custom Prompt</label>
        <textarea
          v-model="settings.satai_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          placeholder="e.g. Generate alt text (max {max_length} characters) describing the subject, colors, and setting. Do not start with 'Image of'. Return only the alt text."
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          Use <code class="bg-gray-100 px-1 rounded">{max_length}</code> as a placeholder for your max character setting. Product context is added automatically for WooCommerce images.
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Max Character Length</label>
        <input
          v-model.number="settings.satai_max_length"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="50"
          max="200"
        />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Language</label>
        <select
          v-model="settings.satai_language"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="en">English</option>
          <option value="es">Spanish</option>
          <option value="fr">French</option>
          <option value="de">German</option>
          <option value="it">Italian</option>
          <option value="pt">Portuguese</option>
          <option value="nl">Dutch</option>
          <option value="ru">Russian</option>
          <option value="ja">Japanese</option>
          <option value="zh">Chinese</option>
          <option value="ko">Korean</option>
          <option value="ar">Arabic</option>
          <option value="hi">Hindi</option>
          <option value="bn">Bengali</option>
          <option value="tr">Turkish</option>
          <option value="pl">Polish</option>
          <option value="sv">Swedish</option>
          <option value="da">Danish</option>
          <option value="fi">Finnish</option>
          <option value="no">Norwegian</option>
          <option value="th">Thai</option>
          <option value="vi">Vietnamese</option>
          <option value="id">Indonesian</option>
          <option value="ms">Malay</option>
          <option value="uk">Ukrainian</option>
          <option value="cs">Czech</option>
          <option value="el">Greek</option>
          <option value="he">Hebrew</option>
          <option value="ro">Romanian</option>
          <option value="hu">Hungarian</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          Alt text will be generated in the selected language
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
          <span>Auto-generate on upload</span>
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
          <span>Preview before accepting</span>
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
          <span>Skip images with existing alt text</span>
        </label>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">File Renaming</h2>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.satai_auto_rename"
          />
          <span>Auto-rename files on upload</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          Rename uploaded files to SEO-friendly names based on generated alt text
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Max Filename Length</label>
        <input
          v-model.number="settings.satai_rename_max_length"
          type="number"
          min="50"
          max="150"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />
        <p class="text-xs text-gray-500 mt-1">
          Characters in filename (default: 120)
        </p>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow border p-6">
      <h2 class="text-lg font-semibold mb-4">Advanced Settings</h2>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Batch Size</label>
        <input
          v-model.number="settings.satai_batch_size"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="1"
          max="20"
        />
        <p class="text-xs text-gray-500 mt-1">Number of images to process per batch</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Request Timeout (seconds)</label>
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
      <h2 class="text-lg font-semibold mb-4">WooCommerce Settings</h2>
      <p class="text-sm text-gray-600 mb-4">Configure alt text generation for WooCommerce products</p>

      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.satai_woo_auto_generate"
          />
          <span>Auto-generate on product upload</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          Automatically generate alt text when adding product images
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
          <span>Include product information</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          Use product name, categories, and SKU in alt text generation
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Default Tone for Products</label>
        <select
          v-model="settings.satai_woo_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <optgroup label="WooCommerce Optimized">
            <option value="woo_seo">Product SEO (Recommended)</option>
            <option value="woo_descriptive">Product Descriptive (Accessibility)</option>
            <option value="woo_lifestyle">Product Lifestyle (In-Use Photos)</option>
            <option value="woo_custom">Custom Prompt</option>
          </optgroup>
          <optgroup label="General">
            <option value="professional">Professional</option>
            <option value="casual">Casual</option>
            <option value="technical">Technical</option>
            <option value="seo">SEO-Optimized</option>
          </optgroup>
        </select>
      </div>

      <div v-if="settings.satai_woo_default_tone === 'woo_custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">Custom WooCommerce Prompt</label>
        <textarea
          v-model="settings.satai_woo_custom_prompt"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          rows="4"
          placeholder="e.g. Generate product alt text (max {max_length} characters) with the product name, visible features, and use case. Do not start with 'Image of'. Return only the alt text."
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          Use <code class="bg-gray-100 px-1 rounded">{max_length}</code> as a placeholder. Product name, category, and SKU are automatically included as context.
        </p>
      </div>
    </div>

    <div v-else class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">WooCommerce Settings</h2>
      <p class="text-sm text-gray-600">
        WooCommerce is not installed. Install WooCommerce to access product image optimization features.
      </p>
    </div>
    
    <div class="mt-6">
      <button 
        @click="saveSettings"
        class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700"
      >
        Save Settings
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { clearSettingsCache } from '../router'

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
    notification.error('Failed to load settings')
  }
}

const saveSettings = async () => {
  saving.value = true;
  try {
    const result = await Api.post('/settings', {
      settings: settings.value
    })
    if (result.success) {
      notification.success('Settings saved successfully')
      clearSettingsCache() // Clear cache so router guard gets fresh data
    }
  } catch (error) {
    notification.error('Failed to save settings')
  } finally {
    saving.value = false
  }
}

const saveApiKey = async () => {
  if (!apiKey.value) {
    notification.error('Please enter an API key')
    return
  }
  
  try {
    const result = await Api.post('/settings/api-key', {
      provider: settings.value.satai_api_provider,
      api_key: apiKey.value
    })
    if (result.success) {
      notification.success('API key saved successfully')
      apiKey.value = ''
      clearSettingsCache() // Clear cache so router guard gets fresh data
      loadSettings()
    }
  } catch (error) {
    notification.error('Failed to save API key')
  }
}

onMounted(() => {
  loadSettings()
})
</script>
