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
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, apiKey, apiSetting, testing, testResult, currentModels, modelSettingKey, showModelSelector, apiDocsUrl, apiDocsLabel, pricingUrl, testConnection, saveApiKey } = inject('settingsContext')
</script>
