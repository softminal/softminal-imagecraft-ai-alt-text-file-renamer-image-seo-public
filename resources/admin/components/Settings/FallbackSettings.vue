<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <div>
      <h2 class="text-lg font-semibold">{{ $t('Image Fallbacks') }}</h2>
      <p class="text-sm text-gray-500">{{ $t('Never show a broken or missing image. Swap broken images for a fallback and give posts without a featured image a default one — nothing is written to your content.') }}</p>
    </div>
    <hr class="my-6" />

    <!-- Master toggle -->
    <div class="mb-4">
      <Checkbox true-value="1" false-value="0" v-model="settings.icais_fallback_enabled">
        <span>{{ $t('Enable image fallbacks') }}</span>
      </Checkbox>
      <p class="text-xs text-gray-500 mt-1">
        {{ $t('Master switch for both fallback features below. Fully non-destructive — turn it off and everything reverts.') }}
      </p>
    </div>

    <template v-if="isEnabled">
      <hr class="my-6" />

      <!-- ============ Broken-image fallback ============ -->
      <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $t('Broken images') }}</h3>
      <p class="text-xs text-gray-500 mb-3">
        {{ $t('When an image on your site fails to load (deleted file, dead external link), visitors see your fallback image instead of a broken icon.') }}
      </p>

      <div class="mb-4">
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_fallback_broken_enabled">
          <span>{{ $t('Replace broken images on the front end') }}</span>
        </Checkbox>
      </div>

      <div v-if="brokenEnabled" class="mb-5 ml-6">
        <label class="block text-sm font-medium mb-2">{{ $t('Fallback image') }}</label>
        <ImagePick
          :attachment-id="brokenImageId"
          @pick="(id) => (settings.icais_fallback_broken_image_id = id)"
        />
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Give this image good alt text — it is used when the broken image had none.') }}
        </p>
        <p v-if="!brokenImageId" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-2 inline-block">
          {{ $t('Choose a fallback image above — nothing happens until one is set.') }}
        </p>
      </div>

      <hr class="my-6" />

      <!-- ============ Missing featured image fallback ============ -->
      <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $t('Missing featured images') }}</h3>
      <p class="text-xs text-gray-500 mb-3">
        {{ $t('Posts without a featured image use your fallback in archives, social previews and anywhere the theme shows one. The post itself is never modified.') }}
      </p>

      <div class="mb-4">
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_fallback_featured_enabled">
          <span>{{ $t('Use a default featured image') }}</span>
        </Checkbox>
      </div>

      <template v-if="featuredEnabled">
        <div class="mb-5 ml-6">
          <label class="block text-sm font-medium mb-2">{{ $t('Default featured image') }}</label>
          <ImagePick
            :attachment-id="featuredImageId"
            @pick="(id) => (settings.icais_fallback_featured_image_id = id)"
          />
          <button
            v-if="!featuredImageId && brokenImageId"
            type="button"
            @click="settings.icais_fallback_featured_image_id = brokenImageId"
            class="text-sm text-blue-600 hover:text-blue-800 underline mt-2"
          >
            {{ $t('Use the same image as the broken-image fallback') }}
          </button>
          <p v-if="!featuredImageId" class="block text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-2 w-fit">
            {{ $t('Choose a default featured image — nothing happens until one is set.') }}
          </p>
        </div>

        <div class="mb-5 ml-6">
          <label class="block text-sm font-medium mb-2">{{ $t('Apply to post types') }}</label>
          <p class="text-xs text-gray-500 mb-3">{{ $t('Leave all unchecked to apply to every public post type.') }}</p>
          <div class="flex flex-wrap gap-3">
            <Checkbox
              v-for="(label, slug) in availablePostTypes"
              :key="slug"
              :value="slug"
              v-model="featuredPostTypes"
              size="sm"
            >
              <span class="text-sm text-gray-700">{{ label }}</span>
            </Checkbox>
          </div>
        </div>

        <!-- Per-post-type fallback images (Pro) -->
        <div class="mb-5 ml-6" :class="{ 'opacity-60': !isPro }">
          <label class="block text-sm font-medium mb-2">
            {{ $t('Per-post-type fallback images') }}
            <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
          </label>
          <p class="text-xs text-gray-500 mb-3">
            {{ isPro
              ? $t('Override the default featured image for specific post types (e.g. a product placeholder for WooCommerce).')
              : $t('Upgrade to Pro to set a different fallback per post type (e.g. a product placeholder for WooCommerce).') }}
          </p>
          <div v-if="isPro" class="space-y-3">
            <div v-for="(label, slug) in availablePostTypes" :key="slug" class="flex items-center gap-3">
              <span class="w-32 text-sm text-gray-700 shrink-0">{{ label }}</span>
              <ImagePick
                :attachment-id="featuredMap[slug] || 0"
                compact
                @pick="(id) => setMapImage(slug, id)"
              />
            </div>
          </div>
        </div>
      </template>

      <hr class="my-6" />

      <!-- ============ Broken-image scan (health) ============ -->
      <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $t('Broken image scan') }}</h3>
      <p class="text-xs text-gray-500 mb-3">
        {{ $t('The Dashboard scans your media library for images whose files are missing and shows them under "Needs attention".') }}
      </p>

      <!-- Scheduled scan + email (Pro) -->
      <div class="mb-4" :class="{ 'opacity-60': !isPro }">
        <label class="block text-sm font-medium mb-2">
          {{ $t('Scheduled scan') }}
          <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
        </label>
        <select v-model="settings.icais_fallback_scan_schedule" :disabled="!isPro" class="icais-select w-48">
          <option value="0">{{ $t('Off') }}</option>
          <option value="daily">{{ $t('Daily') }}</option>
          <option value="weekly">{{ $t('Weekly') }}</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Automatically re-scan in the background and catch broken images early.') }}
        </p>
      </div>

      <div class="mb-2" :class="{ 'opacity-60': !isPro }">
        <Checkbox true-value="1" false-value="0" :disabled="!isPro" v-model="settings.icais_fallback_scan_email">
          <span>
            {{ $t('Email me when new broken images are found') }}
            <span v-if="!isPro" class="ml-1 text-xs text-amber-600 font-semibold">{{ $t('Pro') }}</span>
          </span>
        </Checkbox>
      </div>
    </template>
  </div>
</template>

<script setup>
import { inject, computed, ref, watch } from 'vue'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'
import ImagePick from './ImagePick.vue'

const { settings, availablePostTypes } = inject('settingsContext')

const isPro = window.icais?.isPro ?? false

const truthy = (v) => v === '1' || v === 1 || v === true
const isEnabled = computed(() => truthy(settings.value?.icais_fallback_enabled))
const brokenEnabled = computed(() => truthy(settings.value?.icais_fallback_broken_enabled))
const featuredEnabled = computed(() => truthy(settings.value?.icais_fallback_featured_enabled))
const brokenImageId = computed(() => Number(settings.value?.icais_fallback_broken_image_id) || 0)
const featuredImageId = computed(() => Number(settings.value?.icais_fallback_featured_image_id) || 0)

// JSON-backed settings: hydrate local state from the loaded settings once,
// then mirror every local change back as a JSON string (the parent Settings.vue
// posts settings.value as-is on save).
const featuredPostTypes = ref([])
const featuredMap = ref({})
let hydrated = false

watch(
  () => settings.value?.icais_fallback_featured_post_types,
  (raw) => {
    if (hydrated || raw === undefined) return
    hydrated = true
    try {
      const arr = JSON.parse(raw || '[]')
      featuredPostTypes.value = Array.isArray(arr) ? arr : []
    } catch {
      featuredPostTypes.value = []
    }
    try {
      const map = JSON.parse(settings.value?.icais_fallback_featured_map || '{}')
      featuredMap.value = map && typeof map === 'object' && !Array.isArray(map) ? map : {}
    } catch {
      featuredMap.value = {}
    }
  },
  { immediate: true }
)

watch(featuredPostTypes, (arr) => {
  if (!hydrated) return
  settings.value.icais_fallback_featured_post_types = JSON.stringify(arr)
}, { deep: true })

watch(featuredMap, (map) => {
  if (!hydrated) return
  settings.value.icais_fallback_featured_map = JSON.stringify(map)
}, { deep: true })

const setMapImage = (slug, id) => {
  if (id > 0) {
    featuredMap.value = { ...featuredMap.value, [slug]: id }
  } else {
    const next = { ...featuredMap.value }
    delete next[slug]
    featuredMap.value = next
  }
}
</script>
