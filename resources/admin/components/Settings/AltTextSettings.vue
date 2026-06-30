<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('Alt Text Generation') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Configure how AI generates descriptive alt text for your images.') }}</p>
    <hr class="mb-6" />

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Default Tone') }}</label>
      <select
        v-model="settings.icais_default_tone"
        class="icais-select w-full"
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
        class="icais-textarea w-full"
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
        class="icais-input w-40"
        min="50"
        max="200"
      />
    </div>

    <!-- Auto-generate on upload (Pro) -->
    <div class="mb-4" :class="{ 'opacity-60': !isPro }">
      <Checkbox true-value="1" false-value="0" :disabled="!isPro" v-model="settings.icais_auto_generate">
        <span>
          {{ $t('Auto-generate alt text on upload') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </span>
      </Checkbox>
      <p class="text-xs text-gray-500 mt-1">
        {{ isPro
          ? $t('Automatically generate alt text when an image is uploaded.')
          : $t('Upgrade to Pro to auto-generate alt text on upload.') }}
      </p>
    </div>

    <div class="mb-4">
      <Checkbox true-value="1" false-value="0" v-model="settings.icais_preview_before_accept">
        <span>{{ $t('Preview before accepting') }}</span>
      </Checkbox>
      <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        <p class="text-xs text-amber-800">
          {{ $t('When enabled, generated alt text is shown for review with accept/reject buttons before applying. Works in the Gutenberg editor and Kadence blocks.') }}
        </p>
      </div>
    </div>

    <div class="mb-4">
      <Checkbox true-value="1" false-value="0" v-model="settings.icais_skip_existing">
        <span>{{ $t('Skip images with existing alt text') }}</span>
      </Checkbox>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

const { settings } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false
</script>
