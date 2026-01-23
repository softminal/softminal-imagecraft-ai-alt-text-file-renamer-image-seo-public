<template>
  <div class="settings p-6">
    <h1 class="text-2xl font-bold mb-6">Settings</h1>
    
    <div class="bg-white rounded-lg shadow border p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">API Configuration</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Provider</label>
        <select 
          v-model="settings.alttext_api_provider"
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
          v-model="settings.alttext_default_tone"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        >
          <option value="professional">Professional</option>
          <option value="casual">Casual</option>
          <option value="technical">Technical</option>
          <option value="seo">SEO-Optimized</option>
        </select>
      </div>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Max Character Length</label>
        <input 
          v-model.number="settings.alttext_max_length"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="50"
          max="200"
        />
      </div>
      
      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input 
            type="checkbox"
            v-model="settings.alttext_auto_generate"
          />
          <span>Auto-generate on upload</span>
        </label>
      </div>
      
      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input 
            type="checkbox"
            v-model="settings.alttext_preview_before_accept"
          />
          <span>Preview before accepting</span>
        </label>
      </div>
      
      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input 
            type="checkbox"
            v-model="settings.alttext_skip_existing"
          />
          <span>Skip images with existing alt text</span>
        </label>
      </div>
    </div>
    
    <div class="bg-white rounded-lg shadow border p-6">
      <h2 class="text-lg font-semibold mb-4">Advanced Settings</h2>
      
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Batch Size</label>
        <input 
          v-model.number="settings.alttext_batch_size"
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
          v-model.number="settings.alttext_request_timeout"
          type="number"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          min="30"
          max="120"
        />
      </div>
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

const settings = ref({})
const apiKey = ref('')
const apiSetting = ref(null)
const saving = ref(false)

const loadSettings = async () => {
  try {
    const result = await Api.get('/settings')
    if (result.success) {
      settings.value = result.settings
      apiSetting.value = result.api_setting
    }
  } catch (error) {
    notification.error('Failed to load settings')
  }
}

const saveSettings = async () => {
  saving.value = true;
  console.log(settings.value)
  try {
    const result = await Api.post('/settings', {
      settings: settings.value
    })
    if (result.success) {
      notification.success('Settings saved successfully')
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
      provider: settings.value.alttext_api_provider,
      api_key: apiKey.value
    })
    if (result.success) {
      notification.success('API key saved successfully')
      apiKey.value = ''
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
