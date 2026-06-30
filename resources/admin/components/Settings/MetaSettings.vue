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
      <div class="flex flex-col gap-3 items-start">
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_meta_include_alt">
          <span class="text-sm text-gray-900">{{ $t('Alt text') }}</span>
        </Checkbox>
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_meta_include_title">
          <span class="text-sm text-gray-900">{{ $t('Title') }}</span>
        </Checkbox>
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_meta_include_caption">
          <span class="text-sm text-gray-900">{{ $t('Description (Caption)') }}</span>
        </Checkbox>
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
        class="icais-select w-full"
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
        class="icais-input w-40"
      />
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Characters in caption (default: 150)') }}
      </p>
    </div>

    <div class="mb-4">
      <Checkbox true-value="1" false-value="0" v-model="settings.icais_caption_skip_existing">
        <span>{{ $t('Skip images with an existing caption') }}</span>
      </Checkbox>
    </div>

    <hr class="my-6" />

    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $t('Auto-generate on upload') }}</h3>
    <p class="text-xs text-gray-500 mb-3">
      {{ $t('Generate metadata automatically when an image is uploaded. Alt text and title have their own toggles on their settings pages.') }}
    </p>

    <!-- Auto-generate description on upload (Pro) -->
    <div class="mb-3" :class="{ 'opacity-60': !isPro }">
      <Checkbox true-value="1" false-value="0" :disabled="!isPro" v-model="settings.icais_caption_auto_generate">
        <span>
          {{ $t('Auto-generate description on upload') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </span>
      </Checkbox>
    </div>

    <!-- Auto-generate ALL fields on upload (Pro, priority) -->
    <div class="mb-2" :class="{ 'opacity-60': !isPro }">
      <Checkbox true-value="1" false-value="0" :disabled="!isPro" v-model="settings.icais_meta_auto_generate">
        <span>
          {{ $t('Auto-generate ALL fields on upload') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </span>
      </Checkbox>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Generates every selected field in one call. Takes priority — when on, it overrides the individual alt / title / description auto-generate options.') }}
      </p>
    </div>

    <!-- Post type exclusion (applies to auto-generate-on-upload) -->
    <div
      v-if="isPro && autoEnabled"
      class="mb-2 ml-6 p-3 bg-gray-50 rounded-lg border"
    >
      <p class="text-sm font-medium text-gray-700 mb-2">{{ $t('Exclude post types from auto-generation') }}</p>
      <p class="text-xs text-gray-500 mb-3">{{ $t('Images uploaded to these post types will not be auto-generated.') }}</p>
      <div class="flex flex-wrap gap-3">
        <Checkbox
          v-for="(label, slug) in availablePostTypes"
          :key="slug"
          :value="slug"
          v-model="excludedPostTypes"
          size="sm"
        >
          <span class="text-sm text-gray-700">{{ label }}</span>
        </Checkbox>
      </div>
      <p v-if="Object.keys(availablePostTypes).length === 0" class="text-xs text-gray-400">
        {{ $t('No post types available') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

const { settings, availablePostTypes, excludedPostTypes } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false

// `settings` is a Vue ref — read through .value in script (templates auto-unwrap).
// Post-type exclusion applies to any auto-on-upload, so show it when any toggle is on.
const autoEnabled = computed(() => {
  const on = (k) => {
    const v = settings.value?.[k]
    return v === '1' || v === 1 || v === true
  }
  return on('icais_meta_auto_generate') || on('icais_caption_auto_generate')
    || on('icais_auto_generate') || on('icais_title_auto_generate')
})
</script>
