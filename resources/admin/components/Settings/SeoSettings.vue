<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <router-link to="/settings/integrations" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      {{ $t('Back to Integrations') }}
    </router-link>
    <h2 class="text-lg font-semibold">{{ $t('SEO Integration') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Use focus keyphrases from your SEO plugin to improve generated content.') }}</p>
    <hr class="mb-6" />

    <p class="text-sm text-green-700 font-medium mb-4">
      {{ $t('Detected:') }} {{ seoPlugin?.label }}
    </p>

    <div class="mb-4">
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
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, seoPlugin } = inject('settingsContext')
</script>
