<template>
  <div class="setup-wizard max-w-4xl mx-auto p-6">
    <!-- Step indicator (steps 2+3 are one "AI setup" stage; skip path bypasses it) -->
    <nav class="flex items-center justify-center gap-2 mb-10" :aria-label="$t('Setup progress')">
      <template v-for="(stage, i) in stages" :key="stage">
        <div class="flex items-center gap-2">
          <span
            :class="[
              'w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold transition-colors',
              currentStage > i + 1 ? 'bg-ic-success text-white'
                : currentStage === i + 1 ? 'bg-brand text-white'
                : 'bg-ic-card text-ic-soft border border-ic-border',
            ]"
            aria-hidden="true"
          >
            <Check v-if="currentStage > i + 1" class="w-3.5 h-3.5" />
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span
            :class="[
              'text-xs font-medium hidden sm:inline',
              currentStage === i + 1 ? 'text-ic-text' : 'text-ic-soft',
            ]"
          >{{ stage }}</span>
        </div>
        <span v-if="i < stages.length - 1" class="w-8 h-px bg-ic-border" aria-hidden="true"></span>
      </template>
    </nav>

    <!-- ============ Step 1: Welcome ============ -->
    <div v-if="currentStep === 1" class="welcome-step">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-ic-text mb-3">{{ $t('Welcome to ImageCraft') }}</h1>
        <p class="text-lg text-ic-soft max-w-2xl mx-auto!">
          {{ $t('The all-in-one image toolkit — AI alt text, titles & descriptions, local image compression, WebP conversion, and image fallbacks.') }}
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-ic-card p-5 rounded-card shadow-card border border-ic-border">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-ic bg-pillar-seo-soft text-pillar-seo mb-3">
            <Sparkles class="w-4.5 h-4.5" aria-hidden="true" />
          </span>
          <h3 class="font-semibold text-ic-text mb-1">{{ $t('AI Image SEO') }}</h3>
          <p class="text-sm text-ic-soft">{{ $t('Alt text, titles & descriptions with your own AI key — pay your provider directly, no markup') }}</p>
        </div>
        <div class="bg-ic-card p-5 rounded-card shadow-card border border-ic-border">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-ic bg-pillar-perf-soft text-pillar-perf mb-3">
            <Gauge class="w-4.5 h-4.5" aria-hidden="true" />
          </span>
          <h3 class="font-semibold text-ic-text mb-1">{{ $t('Works without AI') }}</h3>
          <p class="text-sm text-ic-soft">{{ $t('Compression, WebP conversion & fallbacks run locally — no API key, no account') }}</p>
        </div>
        <div class="bg-ic-card p-5 rounded-card shadow-card border border-ic-border">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-ic bg-brand-soft text-brand mb-3">
            <Zap class="w-4.5 h-4.5" aria-hidden="true" />
          </span>
          <h3 class="font-semibold text-ic-text mb-1">{{ $t('Free to start') }}</h3>
          <p class="text-sm text-ic-soft">{{ $t('50 free actions per day across AI generation and compression — Pro removes the limit') }}</p>
        </div>
      </div>

      <div class="text-center mb-8">
        <UiButton size="md" @click="currentStep = 2">
          {{ $t('Set up AI generation') }}
        </UiButton>
        <div class="mt-4">
          <button
            @click="skipToFallbacks"
            :disabled="saving"
            class="text-sm text-ic-soft hover:text-ic-text underline disabled:opacity-50 cursor-pointer"
          >
            {{ $t('Start without AI — compression, WebP & fallbacks need no key') }}
          </button>
        </div>
      </div>

      <!-- Video tutorial: click-to-load, nothing external until the user asks -->
      <div class="bg-ic-card rounded-card shadow-card border border-ic-border p-5">
        <h3 class="font-semibold text-ic-text mb-3 text-center">{{ $t('Watch How It Works') }}</h3>
        <VideoEmbed video-id="VVhnzSmrjLE" :title="$t('How to Use ImageCraft')" />
      </div>
    </div>

    <!-- ============ Step 2: Provider ============ -->
    <div v-if="currentStep === 2" class="provider-step">
      <h2 class="text-2xl font-bold text-ic-text mb-2">{{ $t('Choose Your AI Provider') }}</h2>
      <p class="text-sm text-ic-soft mb-6">{{ $t('Approximate cost per image, billed by the provider at their standard rates.') }}</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8" role="radiogroup" :aria-label="$t('Choose Your AI Provider')">
        <button
          v-for="p in providerCards"
          :key="p.id"
          type="button"
          role="radio"
          :aria-checked="selectedProvider === p.id"
          @click="selectedProvider = p.id"
          :class="['p-5 rounded-card border-2 text-left transition-colors cursor-pointer outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1',
                   selectedProvider === p.id ? 'border-brand bg-brand-soft' : 'bg-ic-card shadow-card border-ic-border hover:border-brand']"
        >
          <div class="flex items-center justify-between mb-1.5">
            <h3 class="font-bold text-ic-text">{{ $t(p.name) }}</h3>
            <UiBadge v-if="p.badge" tone="info">{{ $t(p.badge) }}</UiBadge>
          </div>
          <p class="text-sm text-ic-soft mb-2">{{ $t(p.tagline) }}</p>
          <p class="text-xs text-ic-soft">{{ $t(p.cost) }}</p>
        </button>
      </div>

      <div class="flex justify-between items-center">
        <button @click="currentStep = 1" class="text-ic-soft hover:text-ic-text cursor-pointer">
          ← {{ $t('Back') }}
        </button>
        <div class="flex items-center gap-4">
          <button
            @click="skipToFallbacks"
            :disabled="saving"
            class="text-sm text-ic-soft hover:text-ic-text underline disabled:opacity-50 cursor-pointer"
          >
            {{ $t('Skip for now') }}
          </button>
          <UiButton :disabled="!selectedProvider" @click="currentStep = 3">
            {{ $t('Continue') }}
          </UiButton>
        </div>
      </div>
    </div>

    <!-- ============ Step 3: API key ============ -->
    <div v-if="currentStep === 3" class="api-key-step">
      <h2 class="text-2xl font-bold text-ic-text mb-6">{{ $t('Add Your API Key') }}</h2>

      <!-- WordPress Native AI Connector selected — no key needed -->
      <div v-if="selectedProvider === 'wp7'" class="mb-6">
        <div class="bg-ic-success-soft border border-ic-border rounded-ic p-4 mb-4">
          <div class="flex items-center gap-2 mb-2">
            <ShieldCheck class="w-5 h-5 text-ic-success" aria-hidden="true" />
            <span class="font-semibold text-ic-text">{{ $t('WordPress AI Client detected') }}</span>
          </div>
          <p class="text-sm text-ic-soft">
            {{ $t('Your site has a WordPress AI connector configured. You can use it instead of entering your own API key — no key storage needed.') }}
          </p>
        </div>

        <div v-if="testResult" class="mb-4">
          <div v-if="testResult.success" class="bg-ic-success-soft text-ic-success border border-ic-border p-3 rounded-ic">
            {{ $t('Connection successful!') }}
          </div>
          <div v-else class="bg-ic-danger-soft text-ic-danger border border-ic-border p-3 rounded-ic">
            {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
          </div>
        </div>

        <div class="flex justify-between">
          <button @click="currentStep = 2" class="text-ic-soft hover:text-ic-text cursor-pointer">
            ← {{ $t('Back') }}
          </button>
          <div class="space-x-3">
            <UiButton variant="secondary" :disabled="testing" :loading="testing" @click="testConnection">
              {{ testing ? $t('Testing...') : $t('Test Connection') }}
            </UiButton>
            <UiButton :disabled="!testResult?.success || saving" :loading="saving" @click="saveWP7AndComplete">
              {{ saving ? $t('Saving...') : $t('Save & Continue') }}
            </UiButton>
          </div>
        </div>
      </div>

      <!-- Config key detected -->
      <div v-else-if="configApiKey" class="mb-6">
        <div class="bg-ic-success-soft border border-ic-border rounded-ic p-4 mb-4">
          <div class="flex items-center gap-2 mb-2">
            <ShieldCheck class="w-5 h-5 text-ic-success" aria-hidden="true" />
            <span class="font-semibold text-ic-text">{{ $t('API Key Detected in wp-config.php') }}</span>
          </div>
          <p class="text-sm text-ic-soft">
            {{ $t('Your API key is defined via the') }} <code class="bg-ic-muted px-1 rounded">ICAIS_API_KEY</code> {{ $t('constant. No need to enter it manually.') }}
          </p>
        </div>

        <div v-if="showModelSelector" class="mb-6">
          <label class="block text-sm font-medium text-ic-text mb-2">{{ $t('Model') }}</label>
          <select v-model="currentSelectedModel" class="icais-select w-full">
            <option v-for="model in currentModels" :key="model" :value="model">{{ model }}</option>
          </select>
        </div>

        <div v-if="testResult" class="mb-4">
          <div v-if="testResult.success" class="bg-ic-success-soft text-ic-success border border-ic-border p-3 rounded-ic">
            {{ $t('Connection successful!') }}
          </div>
          <div v-else class="bg-ic-danger-soft text-ic-danger border border-ic-border p-3 rounded-ic">
            {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
          </div>
        </div>

        <div class="flex justify-between">
          <button @click="currentStep = 2" class="text-ic-soft hover:text-ic-text cursor-pointer">
            ← {{ $t('Back') }}
          </button>
          <div class="space-x-3">
            <UiButton variant="secondary" :disabled="testing" :loading="testing" @click="testConnection">
              {{ testing ? $t('Testing...') : $t('Test Connection') }}
            </UiButton>
            <UiButton :disabled="!testResult?.success || saving" :loading="saving" @click="saveConfigAndComplete">
              {{ saving ? $t('Saving...') : $t('Save & Continue') }}
            </UiButton>
          </div>
        </div>
      </div>

      <!-- Manual key input -->
      <div v-else>
        <div class="bg-brand-soft border border-ic-border rounded-ic p-4 mb-6">
          <h3 class="font-semibold text-ic-text mb-2">{{ $t('How to get your API key:') }}</h3>
          <ul class="list-disc list-inside text-sm text-ic-soft space-y-1">
            <li v-if="selectedProvider === 'anthropic'">
              {{ $t('Visit') }} <a href="https://console.anthropic.com" target="_blank" rel="noopener noreferrer" class="text-brand underline">console.anthropic.com</a>
            </li>
            <li v-if="selectedProvider === 'openai'">
              {{ $t('Visit') }} <a href="https://platform.openai.com" target="_blank" rel="noopener noreferrer" class="text-brand underline">platform.openai.com</a>
            </li>
            <li v-if="selectedProvider === 'gemini'">
              {{ $t('Visit') }} <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" class="text-brand underline">Google AI Studio</a>
            </li>
            <li>{{ $t('Create an account and add credits') }}</li>
            <li>{{ $t('Generate an API key') }}</li>
            <li>{{ $t('Paste it below') }}</li>
          </ul>
        </div>

        <div v-if="showModelSelector" class="mb-6">
          <label class="block text-sm font-medium text-ic-text mb-2">{{ $t('Model') }}</label>
          <select v-model="currentSelectedModel" class="icais-select w-full">
            <option v-for="model in currentModels" :key="model" :value="model">{{ model }}</option>
          </select>
        </div>

        <div class="mb-6">
          <label for="icais-wizard-api-key" class="block text-sm font-medium text-ic-text mb-2">{{ $t('API Key') }}</label>
          <input
            id="icais-wizard-api-key"
            v-model="apiKey"
            type="password"
            class="icais-input w-full"
            :placeholder="$t('Enter your API key')"
          />
          <p class="text-xs text-ic-soft mt-1">
            {{ $t('You can also define your key in wp-config.php:') }}
            <code class="bg-ic-muted px-1 rounded">define('ICAIS_API_KEY', 'your-key');</code>
          </p>
        </div>

        <div v-if="testResult" class="mb-4">
          <div v-if="testResult.success" class="bg-ic-success-soft text-ic-success border border-ic-border p-3 rounded-ic">
            {{ $t('Connection successful!') }}
          </div>
          <div v-else class="bg-ic-danger-soft text-ic-danger border border-ic-border p-3 rounded-ic">
            {{ $t('Connection failed:') }} {{ testResult.error || testResult.message }}
          </div>
        </div>

        <div class="flex justify-between">
          <button @click="currentStep = 2" class="text-ic-soft hover:text-ic-text cursor-pointer">
            ← {{ $t('Back') }}
          </button>
          <div class="space-x-3">
            <UiButton variant="secondary" :disabled="!apiKey || testing" :loading="testing" @click="testConnection">
              {{ testing ? $t('Testing...') : $t('Test Connection') }}
            </UiButton>
            <UiButton :disabled="!apiKey || !testResult?.success || saving" :loading="saving" @click="saveApiKey">
              {{ saving ? $t('Saving...') : $t('Save & Continue') }}
            </UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Step 4: Image Fallbacks (optional, no key needed) ============ -->
    <div v-if="currentStep === 4" class="fallback-step">
      <div class="text-center mb-8">
        <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-pillar-perf-soft text-pillar-perf mb-4">
          <ImageOff class="w-7 h-7" aria-hidden="true" />
        </span>
        <h2 class="text-2xl font-bold text-ic-text mb-2">{{ $t('One more thing — never show a broken image') }}</h2>
        <p class="text-ic-soft max-w-xl mx-auto!">
          {{ $t('Pick one image and ImageCraft will show it whenever an image on your site is broken, and as the featured image for posts that have none. Nothing is written to your content — turn it off anytime.') }}
        </p>
      </div>

      <div class="bg-ic-card rounded-card shadow-card border border-ic-border p-5 max-w-md mx-auto mb-8">
        <label class="block text-sm font-medium text-ic-text mb-3">{{ $t('Your fallback image') }}</label>
        <ImagePick
          :attachment-id="fallbackImageId"
          @pick="(id) => (fallbackImageId = id)"
        />
        <p class="text-xs text-ic-soft mt-3">
          {{ $t('Tip: use your logo or a simple branded placeholder. You can fine-tune everything later in Settings → Image Fallbacks.') }}
        </p>
      </div>

      <div class="flex justify-center items-center gap-4">
        <button
          @click="finishWizard(false)"
          :disabled="saving"
          class="text-sm text-ic-soft hover:text-ic-text underline disabled:opacity-50 cursor-pointer"
        >
          {{ $t('Skip for now') }}
        </button>
        <UiButton :disabled="!fallbackImageId || saving" :loading="saving" @click="finishWizard(true)">
          {{ saving ? $t('Saving...') : $t('Enable & Finish') }}
        </UiButton>
      </div>
    </div>

    <!-- ============ Step 5: Done ============ -->
    <div v-if="currentStep === 5" class="success-step text-center">
      <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-ic-success-soft text-ic-success mb-4">
        <CheckCircle2 class="w-8 h-8" aria-hidden="true" />
      </span>
      <h2 class="text-2xl font-bold text-ic-text mb-4">{{ $t('Setup Complete!') }}</h2>
      <p class="text-ic-soft mb-8 max-w-md mx-auto!">
        {{ $t('Generate alt text, compress images, convert to WebP, and audit your media library — all from your dashboard.') }}
      </p>
      <UiButton size="md" @click="goToDashboard">
        {{ $t('Go to Dashboard') }}
      </UiButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Check, CheckCircle2, Gauge, ImageOff, ShieldCheck, Sparkles, Zap } from 'lucide-vue-next'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'
import { UiBadge, UiButton } from '../@ui'
import ImagePick from '../components/Settings/ImagePick.vue'
import VideoEmbed from '../components/VideoEmbed.vue'

const currentStep = ref(1)
const fallbackImageId = ref(0)
const selectedProvider = ref('anthropic')
const apiKey = ref('')
const testing = ref(false)
const saving = ref(false)
const testResult = ref(null)
const availableModels = ref({})
const selectedModels = ref({})
const configApiKey = ref(false)
const wp7Available = ref(false)

// Step indicator: steps 2+3 collapse into one "AI setup" stage.
const stages = [$t('Welcome'), $t('AI setup'), $t('Fallbacks'), $t('Finish')]
const currentStage = computed(() => {
  if (currentStep.value === 1) return 1
  if (currentStep.value <= 3) return 2
  if (currentStep.value === 4) return 3
  return 4
})

// Costs mirror the provider defaults (Haiku / GPT-4o mini / Gemini 2.5 Flash);
// keep in sync with AIFactory defaults and the marketing-site FAQ.
const baseProviders = [
  {
    id: 'anthropic',
    name: 'Anthropic Claude',
    badge: 'Recommended',
    tagline: 'Best quality at the lowest cost',
    cost: '~$0.0008 per image (Claude Haiku)',
  },
  {
    id: 'openai',
    name: 'OpenAI GPT-4o',
    badge: '',
    tagline: 'Great quality, widely used',
    cost: '~$0.003 per image (GPT-4o mini)',
  },
  {
    id: 'gemini',
    name: 'Google Gemini',
    badge: '',
    tagline: 'Fast and affordable',
    cost: '~$0.001 per image (Gemini 2.5 Flash)',
  },
]

const providerCards = computed(() => {
  if (!wp7Available.value) return baseProviders
  return [
    ...baseProviders,
    {
      id: 'wp7',
      name: 'WordPress Native AI Connector',
      badge: 'No key needed',
      tagline: "Use your site's built-in AI — no API key needed",
      cost: 'Managed by WordPress, nothing to configure',
    },
  ]
})

const currentModels = computed(() => {
  return availableModels.value[selectedProvider.value] || []
})

const showModelSelector = computed(() => {
  // WordPress Native AI Connector manages its own model — no selector
  if (selectedProvider.value === 'wp7') return false
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

// A test result only vouches for the provider+key it was run against. Changing
// either invalidates it — otherwise an untested key can be saved (e.g. test
// Anthropic, go back, pick Gemini, paste an untested Gemini key: Save would
// already be enabled).
watch([selectedProvider, apiKey], () => {
  testResult.value = null
})

onMounted(async () => {
  try {
    const result = await Api.get('/settings')
    if (result.available_models) {
      availableModels.value = result.available_models
    }
    if (result.config_api_key) {
      configApiKey.value = true
    }
    if (result.wp7_available) {
      wp7Available.value = true
    }
  } catch {
    // Non-critical, model selector just won't show
  }
})

const testConnection = async () => {
  // WP7 needs no key; BYOK/config require a key to be present
  if (selectedProvider.value !== 'wp7' && !configApiKey.value && !apiKey.value) return

  testing.value = true
  testResult.value = null

  try {
    const payload = {
      provider: selectedProvider.value,
    }
    if (selectedProvider.value !== 'wp7' && !configApiKey.value) {
      payload.api_key = apiKey.value
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

const saveConfigAndComplete = async () => {
  if (!testResult.value?.success) return

  saving.value = true

  try {
    // Save provider and model settings
    const settingsPayload = { icais_api_provider: selectedProvider.value }
    if (showModelSelector.value && currentSelectedModel.value) {
      settingsPayload['icais_' + selectedProvider.value + '_model'] = currentSelectedModel.value
    }
    await Api.post('/settings', { settings: settingsPayload })

    await Api.post('/settings/wizard/complete')
    // Wizard is complete (safe to abandon) — offer the optional Fallbacks step.
    currentStep.value = 4
  } catch {
    notification.error($t('Failed to complete setup'))
  } finally {
    saving.value = false
  }
}

const saveWP7AndComplete = async () => {
  if (!testResult.value?.success) return

  saving.value = true

  try {
    // Enable the WordPress Native AI Connector site-wide
    await Api.post('/settings', { settings: { icais_use_wp7_ai: '1' } })

    await Api.post('/settings/wizard/complete')
    // Wizard is complete (safe to abandon) — offer the optional Fallbacks step.
    currentStep.value = 4
  } catch {
    notification.error($t('Failed to complete setup'))
  } finally {
    saving.value = false
  }
}

// Final wizard step: optionally enable Image Fallbacks with ONE image used for
// both broken images and missing featured images (fine-tunable later in
// Settings → Image Fallbacks). The wizard is already marked complete by this
// point, so skipping or abandoning here loses nothing.
const finishWizard = async (enableFallbacks) => {
  saving.value = true
  try {
    // New users meet Image Fallbacks (and the Performance pillar) right here —
    // suppress the wp-admin "What's New" notices meant for existing users
    // updating. Fire-and-forget.
    Api.post('/notice/dismiss', { notice: 'fallback_feature' }).catch(() => {})
    Api.post('/notice/dismiss', { notice: 'nextgen_feature' }).catch(() => {})

    if (enableFallbacks && fallbackImageId.value > 0) {
      await Api.post('/settings', {
        settings: {
          icais_fallback_enabled: '1',
          icais_fallback_broken_enabled: '1',
          icais_fallback_broken_image_id: fallbackImageId.value,
          icais_fallback_featured_enabled: '1',
          icais_fallback_featured_image_id: fallbackImageId.value,
        },
      })
      notification.success($t('Image fallbacks enabled!'))
    }
    currentStep.value = 5
  } catch {
    notification.error($t('Failed to save fallback settings'))
  } finally {
    saving.value = false
  }
}

const goToDashboard = () => {
  window.location.hash = '#/'
  window.location.reload()
}

// Skip AI setup without configuring a key. Marks the wizard complete first (so
// abandoning costs nothing), then routes to the key-free Fallbacks step instead
// of straight to the dashboard — the user skipping AI is exactly the
// compression/WebP/fallbacks audience. AI features prompt for a key at point of
// use later.
const skipToFallbacks = async () => {
  saving.value = true
  try {
    await Api.post('/settings/wizard/complete')
    currentStep.value = 4
  } catch {
    notification.error($t('Failed to skip setup'))
  } finally {
    saving.value = false
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

    // Mark wizard as completed before moving on so the router guard allows
    // the dashboard even if the user abandons the optional Fallbacks step.
    await Api.post('/settings/wizard/complete')
    notification.success($t('API key saved successfully!'))
    // Offer the optional Fallbacks step before finishing.
    currentStep.value = 4
  } catch {
    notification.error($t('Failed to save API key'))
  } finally {
    saving.value = false
  }
}
</script>
