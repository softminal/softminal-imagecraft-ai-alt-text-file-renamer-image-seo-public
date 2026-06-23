<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold">{{ $t('Image Compression') }}</h2>
        <p class="text-sm text-gray-500">{{ $t('Shrink image file sizes to speed up your site. Originals are backed up by default so you can restore them.') }}</p>
      </div>
      <a
        v-if="!isPro"
        :href="proUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-amber-500 rounded-lg hover:bg-amber-600"
      >
        {{ $t('Get Pro') }}
      </a>
    </div>
    <hr class="my-6" />

    <!-- Master toggle -->
    <div class="mb-4">
      <label class="flex items-center gap-2">
        <input
          type="checkbox"
          true-value="1"
          false-value="0"
          v-model="settings.icais_compression_enabled"
        />
        <span>{{ $t('Enable image compression') }}</span>
      </label>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Adds the Compress tab and per-image compress buttons. Free plan includes %d compressions per day.', freeDailyLimit) }}
      </p>
    </div>

    <template v-if="isEnabled">
      <!-- Quality (Pro) -->
      <div class="mb-5" :class="{ 'opacity-60': !isPro }">
        <label class="block text-sm font-medium mb-2">
          {{ $t('Compression quality') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </label>
        <div class="flex items-center gap-3">
          <input
            v-model.number="settings.icais_compression_quality"
            type="range"
            min="50"
            max="100"
            step="1"
            :disabled="!isPro"
            class="flex-1"
          />
          <span class="w-12 text-sm text-gray-700 text-right">{{ qualityValue }}</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          {{ isPro
            ? $t('85 is visually lossless (recommended). Lower for smaller files. We never re-encode larger than the original.')
            : $t('Free plan uses a visually-lossless quality of 85. Upgrade to Pro to adjust.') }}
        </p>
      </div>

      <!-- Max dimension / downscale (free fixed 1920, Pro adjustable) -->
      <div class="mb-5" :class="{ 'opacity-60': !isPro }">
        <label class="block text-sm font-medium mb-2">
          {{ $t('Resize large images (max size, longest edge in px)') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </label>
        <input
          v-model.number="settings.icais_compression_max_width"
          type="number"
          min="0"
          max="10000"
          :disabled="!isPro"
          :placeholder="!isPro ? '1920' : ''"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ isPro
            ? $t('Downscale images whose longest edge exceeds this. 0 disables resizing.')
            : $t('Free plan downscales images to 2048px (longest edge) — invisible on the web and the biggest space saver. Upgrade to Pro to change or disable it.') }}
        </p>
      </div>

      <!-- Auto-compress on upload (Pro) -->
      <div class="mb-5" :class="{ 'opacity-60': !isPro }">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            :disabled="!isPro"
            v-model="settings.icais_compression_auto"
          />
          <span>
            {{ $t('Auto-compress new uploads') }}
            <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
          </span>
        </label>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Automatically compress every image as it is uploaded, in the background.') }}
        </p>
      </div>

      <hr class="my-6" />

      <!-- Skip already compressed -->
      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_compression_skip_compressed"
          />
          <span>{{ $t('Skip images that are already compressed') }}</span>
        </label>
      </div>

      <!-- Min size -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Skip images smaller than (KB)') }}</label>
        <input
          v-model.number="settings.icais_compression_min_size_kb"
          type="number"
          min="0"
          max="102400"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Tiny images barely benefit from compression. Default: 50 KB.') }}
        </p>
      </div>

      <!-- Back up originals -->
      <div class="mb-4">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            true-value="1"
            false-value="0"
            v-model="settings.icais_compression_keep_backup"
          />
          <span>{{ $t('Back up originals before compressing') }}</span>
        </label>
      </div>

      <div v-if="keepBackup" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-xs text-green-700">
          {{ $t('Originals are backed up to /uploads/icais-originals/. You can restore any image from the Compress tab.') }}
        </p>
      </div>
      <div v-else class="mt-4 p-3 bg-amber-50 border border-amber-300 rounded-lg">
        <p class="text-xs text-amber-800">
          {{ $t('Backups are off. Newly compressed images cannot be restored to their originals (images already backed up can still be restored). This saves disk space — use with care.') }}
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { inject, computed } from 'vue'
import { $t } from '../../utils/i18n'

const { settings } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false
const freeDailyLimit = 20
const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'

// `settings` is provided as a Vue ref — read through .value in script (templates auto-unwrap).
const isEnabled = computed(() => {
  const v = settings.value?.icais_compression_enabled
  return v === '1' || v === 1 || v === true
})

const qualityValue = computed(() => {
  if (!isPro) return 85
  const q = Number(settings.value?.icais_compression_quality)
  return Number.isFinite(q) && q > 0 ? q : 85
})

// Backups default ON; only an explicit '0' turns them off.
const keepBackup = computed(() => String(settings.value?.icais_compression_keep_backup ?? '1') !== '0')
</script>
