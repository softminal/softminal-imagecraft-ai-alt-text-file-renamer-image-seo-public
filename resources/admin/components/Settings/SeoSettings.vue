<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('SEO Integration') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Use focus keyphrases from your SEO plugin to improve generated content.') }}</p>
    <hr class="mb-6" />

    <template v-if="seoPlugin?.active">
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
    </template>

    <p v-else class="text-sm text-gray-600">
      {{ $t('No supported SEO plugin detected. Install Yoast SEO, Rank Math, All in One SEO, or SEOPress to enable focus keyphrase integration.') }}
    </p>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, seoPlugin } = inject('settingsContext')
</script>
