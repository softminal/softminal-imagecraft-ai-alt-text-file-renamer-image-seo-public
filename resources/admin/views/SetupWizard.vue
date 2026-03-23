<template>
  <div class="setup-wizard max-w-4xl mx-auto p-6">
    <div v-if="currentStep === 1" class="welcome-step">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $t('Welcome to AI Alt Text Generator') }}</h1>
        <p class="text-lg text-gray-600 mb-8">
          {{ $t('Generate accessible alt text for your images using AI. You provide your own API key with no middleman fees.') }}
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-4xl mb-4">🚀</div>
          <h3 class="font-semibold mb-2">{{ $t('Easy Setup') }}</h3>
          <p class="text-sm text-gray-600">{{ $t('Get started in 3 minutes') }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-4xl mb-4">💰</div>
          <h3 class="font-semibold mb-2">{{ $t('BYOK Model') }}</h3>
          <p class="text-sm text-gray-600">{{ $t('Use your own API key') }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-4xl mb-4">♾️</div>
          <h3 class="font-semibold mb-2">{{ $t('Unlimited') }}</h3>
          <p class="text-sm text-gray-600">{{ $t('Process as many images as you want') }}</p>
        </div>
      </div>

      <div class="text-center mb-8">
        <button
          @click="currentStep = 2"
          class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
        >
          {{ $t('Get Started') }}
        </button>
      </div>

      <!-- Video Tutorial -->
      <div class="bg-white rounded-lg shadow border p-6">
        <h3 class="font-semibold mb-3 text-center">{{ $t('Watch How It Works') }}</h3>
        <div class="relative w-full" style="padding-bottom: 56.25%;">
          <iframe
            class="absolute inset-0 w-full h-full rounded-lg"
            src="https://www.youtube.com/embed/VVhnzSmrjLE"
            title="How to Use ImageCraft"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
          ></iframe>
        </div>
      </div>
    </div>

    <div v-if="currentStep === 2" class="provider-step">
      <h2 class="text-2xl font-bold mb-6">{{ $t('Choose Your AI Provider') }}</h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div
          @click="selectedProvider = 'anthropic'"
          :class="['provider-card p-6 rounded-lg border-2 cursor-pointer transition',
                   selectedProvider === 'anthropic' ? 'border-blue-600 bg-blue-50' : 'bg-white shadow border-gray-200']"
        >
          <h3 class="font-bold text-lg mb-2">{{ $t('Anthropic Claude') }}</h3>
          <p class="text-sm text-gray-600 mb-4">{{ $t('Low cost with quality results') }}</p>
          <div class="text-xs text-gray-500">
            <div>{{ $t('Best balance of quality and cost') }}</div>
          </div>
        </div>

        <div
          @click="selectedProvider = 'openai'"
          :class="['provider-card p-6 rounded-lg border-2 cursor-pointer transition',
                   selectedProvider === 'openai' ? 'border-blue-600 bg-blue-50' : 'bg-white shadow border-gray-200']"
        >
          <h3 class="font-bold text-lg mb-2">{{ $t('OpenAI GPT-4') }}</h3>
          <p class="text-sm text-gray-600 mb-4">{{ $t('Great quality, widely used') }}</p>
          <div class="text-xs text-gray-500">
            <div>{{ $t('Excellent results, higher cost') }}</div>
          </div>
        </div>

        <div
          @click="selectedProvider = 'gemini'"
          :class="['provider-card p-6 rounded-lg border-2 cursor-pointer transition',
                   selectedProvider === 'gemini' ? 'border-blue-600 bg-blue-50' : 'bg-white shadow border-gray-200']"
        >
          <h3 class="font-bold text-lg mb-2">{{ $t('Google Gemini') }}</h3>
          <p class="text-sm text-gray-600 mb-4">{{ $t("Google's AI vision model") }}</p>
          <div class="text-xs text-gray-500">
            <div>{{ $t('Most affordable option') }}</div>
          </div>
        </div>

      </div>

      <div class="flex justify-between">
        <button @click="currentStep = 1" class="text-gray-600 hover:text-gray-800">
          ← {{ $t('Back') }}
        </button>
        <button
          @click="currentStep = 3"
          :disabled="!selectedProvider"
          class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ $t('Continue') }}
        </button>
      </div>
    </div>

    <div v-if="currentStep === 3" class="api-key-step">
      <h2 class="text-2xl font-bold mb-6">{{ $t('Add Your API Key') }}</h2>

      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold mb-2">{{ $t('How to get your API key:') }}</h3>
        <ul class="list-disc list-inside text-sm space-y-1">
          <li v-if="selectedProvider === 'anthropic'">
            {{ $t('Visit') }} <a href="https://console.anthropic.com" target="_blank" class="text-blue-600 underline">console.anthropic.com</a>
          </li>
          <li v-if="selectedProvider === 'openai'">
            {{ $t('Visit') }} <a href="https://platform.openai.com" target="_blank" class="text-blue-600 underline">platform.openai.com</a>
          </li>
          <li v-if="selectedProvider === 'gemini'">
            {{ $t('Visit') }} <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 underline">Google AI Studio</a>
          </li>
          <li>{{ $t('Create an account and add credits') }}</li>
          <li>{{ $t('Generate an API key') }}</li>
          <li>{{ $t('Paste it below') }}</li>
        </ul>
      </div>

      <div v-if="showModelSelector" class="mb-6">
        <label class="block text-sm font-medium mb-2">{{ $t('Model') }}</label>
        <select
          v-model="currentSelectedModel"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option v-for="model in currentModels" :key="model" :value="model">{{ model }}</option>
        </select>
      </div>

      <div class="mb-6">
        <label class="block text-sm font-medium mb-2">{{ $t('API Key') }}</label>
        <input
          v-model="apiKey"
          type="password"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          :placeholder="$t('Enter your API key')"
        />
      </div>

      <div v-if="testResult" class="mb-4">
        <div v-if="testResult.success" class="bg-green-50 border border-green-200 text-green-800 p-3 rounded">
          ✓ {{ $t('Connection successful!') }}
        </div>
        <div v-else class="bg-red-50 border border-red-200 text-red-800 p-3 rounded">
          ✗ {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
        </div>
      </div>

      <div class="flex justify-between">
        <button @click="currentStep = 2" class="text-gray-600 hover:text-gray-800">
          ← {{ $t('Back') }}
        </button>
        <div class="space-x-3">
          <button
            @click="testConnection"
            :disabled="!apiKey || testing"
            class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-semibold hover:bg-gray-300 disabled:opacity-50"
          >
            {{ testing ? $t('Testing...') : $t('Test Connection') }}
          </button>
          <button
            @click="saveApiKey"
            :disabled="!apiKey || !testResult?.success || saving"
            class="bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50"
          >
            {{ saving ? $t('Saving...') : $t('Save & Continue') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="currentStep === 4" class="success-step text-center">
      <div class="text-6xl mb-4">🎉</div>
      <h2 class="text-2xl font-bold mb-4">{{ $t('Setup Complete!') }}</h2>
      <p class="text-gray-600 mb-8">{{ $t("You're all set to start generating alt text for your images.") }}</p>
      <button
        @click="completeWizard"
        class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700"
      >
        {{ $t('Go to Dashboard') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'

const currentStep = ref(1)
const selectedProvider = ref('anthropic')
const apiKey = ref('')
const testing = ref(false)
const saving = ref(false)
const testResult = ref(null)
const availableModels = ref({})
const selectedModels = ref({})

const currentModels = computed(() => {
  return availableModels.value[selectedProvider.value] || []
})

const showModelSelector = computed(() => {
  return currentModels.value.length > 1 && selectedProvider.value !== 'openrouter'
})

const currentSelectedModel = computed({
  get() {
    const provider = selectedProvider.value
    return selectedModels.value[provider] || currentModels.value[0] || ''
  },
  set(val) {
    selectedModels.value[selectedProvider.value] = val
  }
})

onMounted(async () => {
  try {
    const result = await Api.get('/settings')
    if (result.available_models) {
      availableModels.value = result.available_models
    }
  } catch (e) {
    // Non-critical, model selector just won't show
  }
})

const testConnection = async () => {
  if (!apiKey.value) return

  testing.value = true
  testResult.value = null

  try {
    const payload = {
      provider: selectedProvider.value,
      api_key: apiKey.value
    }
    if (showModelSelector.value) {
      payload.model = currentSelectedModel.value
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

const saveApiKey = async () => {
  if (!apiKey.value || !testResult.value?.success) return

  saving.value = true

  try {
    await Api.post('/settings/api-key', {
      provider: selectedProvider.value,
      api_key: apiKey.value
    })

    // Save selected model if applicable
    if (showModelSelector.value && currentSelectedModel.value) {
      const modelKey = 'icais_' + selectedProvider.value + '_model'
      await Api.post('/settings', {
        settings: { [modelKey]: currentSelectedModel.value }
      })
    }

    // Mark wizard as completed before reload so router guard allows dashboard
    await Api.post('/settings/wizard/complete')
    notification.success($t('API key saved successfully!'))
    setTimeout(() => {
      window.location.hash = '#/'
      window.location.reload()
    }, 1000)
  } catch (error) {
    notification.error($t('Failed to save API key'))
  } finally {
    saving.value = false
  }
}
</script>
