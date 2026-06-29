<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <div>
      <h2 class="text-lg font-semibold">{{ $t('Generate All — Combined Metadata') }}</h2>
      <p class="text-sm text-gray-500">{{ $t('Generate alt text, title and caption together in a single AI call. Used by the Media Library bulk picker, the per-image media-modal picker, and auto-on-upload.') }}</p>
    </div>
    <hr class="my-6" />

    <!-- Default fields for the bulk picker -->
    <div class="mb-5">
      <label class="block text-sm font-medium mb-1">{{ $t('Default fields') }}</label>
      <p class="text-xs text-gray-500 mb-3">
        {{ $t('Which fields are pre-selected in the Media Library bulk picker (you can still change them per action).') }}
      </p>
      <div class="space-y-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_meta_include_alt"
          />
          <span class="text-sm text-gray-900">{{ $t('Alt text') }}</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_meta_include_title"
          />
          <span class="text-sm text-gray-900">{{ $t('Title') }}</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_meta_include_caption"
          />
          <span class="text-sm text-gray-900">{{ $t('Description (Caption)') }}</span>
        </label>
      </div>
      <p class="text-xs text-gray-500 mt-2">
        {{ $t('Alt text and title use their own tone/length settings (Alt Text and Image Title pages). Caption settings are below.') }}
      </p>
    </div>

    <hr class="my-6" />

    <!-- Caption settings -->
    <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ $t('Caption settings') }}</h3>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Caption Tone') }}</label>
      <select
        v-model="settings.icais_caption_default_tone"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
      >
        <option value="professional">{{ $t('Professional') }}</option>
        <option value="casual">{{ $t('Casual') }}</option>
        <option value="technical">{{ $t('Technical') }}</option>
        <option value="seo">{{ $t('SEO-Optimized') }}</option>
      </select>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium mb-2">{{ $t('Max Caption Length') }}</label>
      <input
        v-model.number="settings.icais_caption_max_length"
        type="number"
        min="20"
        max="500"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
      />
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Characters in caption (default: 150)') }}
      </p>
    </div>

    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_caption_skip_existing"
        />
        <span>{{ $t('Skip images with an existing caption') }}</span>
      </label>
    </div>

    <hr class="my-6" />

    <!-- Auto-generate on upload (Pro) -->
    <div class="mb-2" :class="{ 'opacity-60': !isPro }">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          :disabled="!isPro"
          v-model="settings.icais_meta_auto_generate"
        />
        <span>
          {{ $t('Auto-generate on upload') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </span>
      </label>
      <p class="text-xs text-gray-500 mt-1">
        {{ isPro
          ? $t('Automatically generate the default fields whenever an image is uploaded, in the background.')
          : $t('Upgrade to Pro to automatically generate metadata whenever an image is uploaded.') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { settings } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false
</script>
