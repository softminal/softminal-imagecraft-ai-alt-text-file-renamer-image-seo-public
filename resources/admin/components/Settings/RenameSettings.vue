<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('File Renaming') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('AI-powered file renaming for SEO-friendly image URLs.') }}</p>
    <hr class="mb-6" />

    <div class="mb-4">
      <Checkbox true-value="1" false-value="0" v-model="settings.icais_auto_rename">
        <span>{{ $t('Enable file renaming') }}</span>
      </Checkbox>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Shows "Rename File" buttons to generate SEO-friendly filenames using AI. This changes image URLs.') }}
      </p>
    </div>

    <template v-if="settings.icais_auto_rename === '1' || settings.icais_auto_rename === 1 || settings.icais_auto_rename === true">
      <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <p class="text-xs text-amber-700">
          {{ $t('Renaming files changes image URLs. External links and cached pages may show broken images.') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Max Filename Length') }}</label>
        <input
          v-model.number="settings.icais_rename_max_length"
          type="number"
          min="50"
          max="150"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Characters in filename (default: 120)') }}
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

const { settings } = inject('settingsContext')
</script>
