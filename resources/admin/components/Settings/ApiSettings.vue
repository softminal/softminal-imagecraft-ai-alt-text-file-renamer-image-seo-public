<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('API Configuration') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Connect your AI provider to start generating alt text and titles.') }}</p>
    <hr class="mb-6" />

    <!-- WordPress 7.0 AI Client — only shown when WP7 + a configured connector is detected -->
    <div v-if="wp7Available" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <p class="font-semibold text-blue-900">{{ $t('WordPress AI Client detected') }}</p>
          <p class="mt-1 text-sm text-blue-700">
            {{ $t('Your site has a WordPress AI connector configured. You can use it instead of entering your own API key — no key storage needed.') }}
          </p>
        </div>
        <Checkbox
          true-value="1"
          false-value="0"
          v-model="settings.icais_use_wp7_ai"
          class="shrink-0 pt-0.5"
        >
          <span class="text-sm font-medium text-blue-900 whitespace-nowrap">{{ $t('Use WordPress AI Client') }}</span>
        </Checkbox>
      </div>

      <!-- Active state: test button + status -->
      <div v-if="settings.icais_use_wp7_ai === '1'" class="mt-3 flex items-center gap-3">
        <button
          @click="testWP7Connection"
          :disabled="testing"
          class="rounded-lg bg-white border border-blue-300 px-4 py-2 text-sm text-blue-700 hover:bg-blue-100 disabled:opacity-50"
        >
          {{ testing ? $t('Testing...') : $t('Test Connection') }}
        </button>
        <span class="text-xs text-blue-600">
          {{ $t('Save settings after enabling to apply.') }}
        </span>
      </div>

      <!-- Shared test result (reused below too) -->
      <div v-if="settings.icais_use_wp7_ai === '1' && testResult" class="mt-2">
        <div v-if="testResult.success" class="rounded bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
          {{ $t('WordPress AI Client is ready.') }}
        </div>
        <div v-else class="rounded bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
          {{ testResult.error || testResult.message }}
        </div>
      </div>
    </div>

    <!-- BYOK form — hidden when WordPress AI Client is active -->
    <div v-if="settings.icais_use_wp7_ai !== '1'">
    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Provider') }}</label>
      <select
        v-model="settings.icais_api_provider"
        class="icais-select w-full"
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
        class="icais-select w-full"
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
          class="icais-input w-full"
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

    </div><!-- end BYOK form -->

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
import { Checkbox } from '../../@ui'

const { settings, apiKey, apiSetting, configApiKey, testing, testResult, currentModels, modelSettingKey, showModelSelector, apiDocsUrl, apiDocsLabel, pricingUrl, testConnection, testWP7Connection, saveApiKey, wp7Available } = inject('settingsContext')

const activeTab = ref(configApiKey.value ? 'config' : 'database')

// Reset test result when switching tabs
watch(activeTab, () => {
  testResult.value = null
})
</script>
