<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('API Configuration') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Connect your AI provider to start generating alt text and titles.') }}</p>
    <hr class="mb-6" />

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

    <!-- Key Storage Method Tabs -->
    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('API Key Storage') }}</label>
      <div class="flex items-center gap-6 mb-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" v-model="activeTab" value="database" class="accent-blue-600" />
          <span class="text-sm">{{ $t('Store in Database') }}</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" v-model="activeTab" value="config" class="accent-blue-600" />
          <span class="text-sm">{{ $t('Store in wp-config.php') }}</span>
        </label>
      </div>

      <!-- Database Tab -->
      <div v-if="activeTab === 'database'">
        <div v-if="configApiKey" class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3">
          <p class="text-sm text-amber-800">
            {{ $t('A key is currently defined in wp-config.php and takes priority over the database key.') }}
          </p>
        </div>

        <input
          v-model="apiKey"
          type="password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
          :placeholder="$t('Enter API key')"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Encrypted with AES-256-CBC using your WordPress salts before saving.') }}
        </p>
        <p v-if="apiDocsUrl" class="text-xs text-gray-500 mt-1">
          {{ $t('Get your API key from') }}
          <a :href="apiDocsUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
            {{ apiDocsLabel }} &rarr;
          </a>
        </p>

        <div class="mt-3 flex items-center gap-3">
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
      </div>

      <!-- wp-config.php Tab -->
      <div v-if="activeTab === 'config'">
        <div v-if="configApiKey" class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg mb-3">
          <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span class="text-green-800 text-sm font-medium">{{ $t('API key detected in wp-config.php') }}</span>
        </div>

        <div v-else class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-3">
          <p class="text-sm text-gray-700 mb-3">
            {{ $t('Add this line to your wp-config.php file (before "That\'s all, stop editing!"):') }}
          </p>
          <code class="block bg-gray-800 text-green-400 px-4 py-3 rounded text-sm font-mono select-all">
            define( 'ICAIS_API_KEY', 'your-api-key-here' );
          </code>
        </div>

        <p class="text-xs text-gray-500 mb-3">
          {{ $t('The key stays in your server files — never stored in the database. Ideal for version-controlled deployments and security-conscious setups.') }}
        </p>

        <div class="mt-3">
          <button
            @click="testConnection"
            :disabled="!configApiKey || testing"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 disabled:opacity-50"
          >
            {{ testing ? $t('Testing...') : $t('Test Connection') }}
          </button>
        </div>
      </div>

      <!-- Shared test result -->
      <div v-if="testResult" class="mt-3">
        <div v-if="testResult.success" class="bg-green-50 border border-green-200 text-green-800 p-3 rounded text-sm">
          {{ $t('Connection successful!') }}
        </div>
        <div v-else class="bg-red-50 border border-red-200 text-red-800 p-3 rounded text-sm">
          {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
        </div>
      </div>
    </div>

    <!-- Current status -->
    <div v-if="apiSetting" class="mt-4 pt-4 border-t text-sm text-gray-600 space-y-1">
      <div class="flex items-center gap-2">
        <span :class="apiSetting.is_active ? 'text-green-600' : 'text-red-600'">{{ apiSetting.is_active ? '●' : '○' }}</span>
        <span>{{ apiSetting.is_active ? $t('Connected') : $t('Inactive') }} — {{ apiSetting.provider }}</span>
      </div>
      <div v-if="apiSetting.source" class="text-xs text-gray-400">
        {{ $t('Key source:') }} {{ apiSetting.source === 'config' ? 'wp-config.php' : $t('Database (encrypted)') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, apiKey, apiSetting, configApiKey, testing, testResult, currentModels, modelSettingKey, showModelSelector, apiDocsUrl, apiDocsLabel, pricingUrl, testConnection, saveApiKey } = inject('settingsContext')

const activeTab = ref(configApiKey.value ? 'config' : 'database')

// Reset test result when switching tabs
watch(activeTab, () => {
  testResult.value = null
})
</script>
