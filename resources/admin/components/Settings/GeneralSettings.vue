<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('General') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Global settings shared across all generation features.') }}</p>
    <hr class="mb-6" />

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Language') }}</label>
      <select
        v-model="settings.icais_language"
        class="icais-select w-full"
      >
        <option v-for="(name, code) in sortedLanguages" :key="code" :value="code">{{ $t(name) }}</option>
      </select>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('All AI-generated content (alt text, titles) will use this language.') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Batch Size') }}</label>
      <input
        v-model.number="settings.icais_batch_size"
        type="number"
        class="icais-input w-40"
        min="1"
        max="20"
      />
      <p class="text-xs text-gray-500 mt-1">{{ $t('Number of images to process per batch request') }}</p>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Request Timeout (seconds)') }}</label>
      <input
        v-model.number="settings.icais_request_timeout"
        type="number"
        class="icais-input w-40"
        min="30"
        max="120"
      />
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings, sortedLanguages } = inject('settingsContext')
</script>
