<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('Image Title Generation') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Generate SEO-optimized titles for your images using AI.') }}</p>
    <hr class="mb-6" />

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Title Tone') }}</label>
      <select
        v-model="settings.icais_title_default_tone"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
      >
        <option value="professional">{{ $t('Professional') }}</option>
        <option value="casual">{{ $t('Casual') }}</option>
        <option value="technical">{{ $t('Technical') }}</option>
        <option value="seo">{{ $t('SEO-Optimized') }}</option>
        <option value="custom">{{ $t('Custom Prompt') }}</option>
      </select>
    </div>

    <div v-if="settings.icais_title_default_tone === 'custom'" class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Custom Title Prompt') }}</label>
      <textarea
        v-model="settings.icais_title_custom_prompt"
        rows="3"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        :placeholder="$t('Write a title for this image. Max {max_length} characters...')"
      />
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Use {max_length} as a placeholder for the max title length.') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Max Title Length') }}</label>
      <input
        v-model.number="settings.icais_title_max_length"
        type="number"
        min="10"
        max="150"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
      />
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Characters in title (default: 30)') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_title_preview_before_accept"
        />
        <span>{{ $t('Preview before accepting') }}</span>
      </label>
      <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        <p class="text-xs text-amber-800">
          {{ $t('When enabled, generated titles are shown for review with accept/reject buttons before applying. Works in the Gutenberg editor and Kadence blocks.') }}
        </p>
      </div>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_title_auto_generate"
        />
        <span>{{ $t('Auto-generate title on upload') }}</span>
      </label>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Automatically generate a title when an image is uploaded.') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_title_skip_existing"
        />
        <span>{{ $t('Skip images with existing title') }}</span>
      </label>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings } = inject('settingsContext')
</script>
