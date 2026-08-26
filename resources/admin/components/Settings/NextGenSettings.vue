<template>
  <UiCard
    :title="$t('Next-gen formats (WebP / AVIF)')"
    :description="$t('Serve smaller, faster next-gen copies of your images without ever touching the originals.')"
  >
    <div class="flex flex-col gap-5">
      <!-- How it works -->
      <p class="text-[0.8125rem] text-ic-soft m-0">
        {{ $t('ImageCraft creates a WebP and AVIF copy next to each original image. Browsers that support next-gen formats get the smaller copy; everyone else gets the original. Nothing is rewritten, and removing the copies restores everything exactly as it was.') }}
      </p>

      <!-- WebP (free) -->
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <span class="flex items-center gap-1.5 text-sm font-medium text-ic-text">
            {{ $t('Generate WebP copies') }}
            <UiBadge v-if="!canWebp" tone="warning">{{ $t('Not supported by server') }}</UiBadge>
          </span>
          <p class="mt-0.5 text-[0.8125rem] text-ic-soft m-0">
            {{ canWebp
              ? $t('WebP is typically 25–35% smaller than JPEG at the same visual quality.')
              : $t('Your server cannot create WebP images (neither Imagick nor GD supports it). Ask your host to enable WebP support.') }}
          </p>
        </div>
        <Switch
          :model-value="settings.icais_compression_convert_webp === '1'"
          :disabled="!canWebp"
          @update:model-value="settings.icais_compression_convert_webp = $event ? '1' : '0'"
        />
      </div>

      <!-- AVIF (capability-gated only) -->
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <span class="flex items-center gap-1.5 text-sm font-medium text-ic-text">
            {{ $t('Generate AVIF copies') }}
            <UiBadge v-if="!canAvif" tone="warning">{{ $t('Not supported by server') }}</UiBadge>
          </span>
          <p class="mt-0.5 text-[0.8125rem] text-ic-soft m-0">
            <template v-if="!canAvif">
              {{ $t('Your server cannot create AVIF images. AVIF needs PHP 8.1+ (GD) or an Imagick build with AVIF support — ask your host.') }}
            </template>
            <template v-else>
              {{ $t('AVIF is often ~50% smaller than JPEG — the smallest format modern browsers support.') }}
            </template>
          </p>
        </div>
        <Switch
          :model-value="settings.icais_compression_convert_avif === '1'"
          :disabled="!canAvif"
          @update:model-value="settings.icais_compression_convert_avif = $event ? '1' : '0'"
        />
      </div>

      <!-- Auto-convert on upload (Pro) -->
      <div class="flex items-start justify-between gap-4" :class="{ 'opacity-60': !isPro }">
        <div class="min-w-0">
          <span class="flex items-center gap-1.5 text-sm font-medium text-ic-text">
            {{ $t('Auto-convert new uploads') }}
            <UiBadge v-if="!isPro" tone="pro">Pro</UiBadge>
          </span>
          <p class="mt-0.5 text-[0.8125rem] text-ic-soft m-0">
            {{ $t('Automatically generate next-gen copies for every new image you upload.') }}
          </p>
        </div>
        <Switch
          :model-value="settings.icais_nextgen_auto === '1'"
          :disabled="!isPro"
          @update:model-value="settings.icais_nextgen_auto = $event ? '1' : '0'"
        />
      </div>

      <!-- Frontend delivery -->
      <div class="flex items-start justify-between gap-4 pt-4 border-t border-ic-border-soft">
        <div class="min-w-0">
          <span class="text-sm font-medium text-ic-text">{{ $t('Serve next-gen images to visitors') }}</span>
          <p class="mt-0.5 text-[0.8125rem] text-ic-soft m-0">
            {{ $t('Wraps images in a <picture> tag so supporting browsers load the WebP/AVIF copy, with the original as automatic fallback. Off by default — enable after converting your images.') }}
          </p>
        </div>
        <Switch
          :model-value="settings.icais_nextgen_delivery_enabled === '1'"
          @update:model-value="settings.icais_nextgen_delivery_enabled = $event ? '1' : '0'"
        />
      </div>

      <!-- Where to convert -->
      <div class="bg-ic-muted rounded-ic p-3 text-[0.8125rem] text-ic-soft">
        {{ $t('Convert images from the Performance page — select images and use "Convert Selected", or convert your whole library in bulk.') }}
        <router-link to="/performance" class="text-brand font-medium underline">{{ $t('Open Performance') }} →</router-link>
      </div>
    </div>
  </UiCard>
</template>

<script setup>
import { inject } from 'vue'
import { UiCard, UiBadge, Switch } from '../../@ui'
import { $t } from '../../utils/i18n'

const { settings } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false
const canWebp = window.icais?.canWebp ?? false
const canAvif = window.icais?.canAvif ?? false
</script>
