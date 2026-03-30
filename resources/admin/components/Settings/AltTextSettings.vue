<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('Alt Text Generation') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Configure how AI generates descriptive alt text for your images.') }}</p>
    <hr class="mb-6" />

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Default Tone') }}</label>
      <select
        v-model="settings.icais_default_tone"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
      >
        <option value="professional">{{ $t('Professional') }}</option>
        <option value="casual">{{ $t('Casual') }}</option>
        <option value="technical">{{ $t('Technical') }}</option>
        <option value="seo">{{ $t('SEO-Optimized') }}</option>
        <option value="custom">{{ $t('Custom Prompt') }}</option>
      </select>
    </div>

    <div v-if="settings.icais_default_tone === 'custom'" class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Custom Prompt') }}</label>
      <textarea
        v-model="settings.icais_custom_prompt"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        rows="4"
        :placeholder="$t('e.g. Generate alt text describing the subject, colors, and setting. Do not start with \'Image of\'. Return only the alt text.')"
      ></textarea>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('The max character length setting above will be applied automatically. Product context is added automatically for WooCommerce images.') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Max Character Length') }}</label>
      <input
        v-model.number="settings.icais_max_length"
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
          true-value="1"
          false-value="0"
          v-model="settings.icais_auto_generate"
        />
        <span>{{ $t('Auto-generate on upload') }}</span>
      </label>
    </div>

    <!-- Post type exclusion -->
    <div v-if="settings.icais_auto_generate === '1' || settings.icais_auto_generate === 1 || settings.icais_auto_generate === true" class="mb-4 ml-6 p-3 bg-gray-50 rounded-lg border">
      <p class="text-sm font-medium text-gray-700 mb-2">{{ $t('Exclude post types from auto-generation') }}</p>
      <p class="text-xs text-gray-500 mb-3">{{ $t('Images uploaded to these post types will not be auto-generated.') }}</p>
      <div class="flex flex-wrap gap-3">
        <label
          v-for="(label, slug) in availablePostTypes"
          :key="slug"
          class="inline-flex items-center gap-1.5 cursor-pointer"
        >
          <input
            type="checkbox"
            :value="slug"
            v-model="excludedPostTypes"
            class="rounded"
          />
          <span class="text-sm text-gray-700">{{ label }}</span>
        </label>
      </div>
      <p v-if="Object.keys(availablePostTypes).length === 0" class="text-xs text-gray-400">
        {{ $t('No post types available') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_preview_before_accept"
        />
        <span>{{ $t('Preview before accepting') }}</span>
      </label>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_skip_existing"
        />
        <span>{{ $t('Skip images with existing alt text') }}</span>
      </label>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, availablePostTypes, excludedPostTypes } = inject('settingsContext')
</script>
