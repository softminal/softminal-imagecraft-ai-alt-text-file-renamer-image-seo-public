<template>
  <div
    @click="$emit('toggle-select', product.id)"
    :class="['product-card p-4 rounded-lg border-2 cursor-pointer transition',
             selected ? 'border-purple-600 bg-purple-50' : 'border-gray-200']"
  >
    <!-- Image strip: Featured + Gallery thumbnails -->
    <div class="flex items-center gap-1.5 mb-3">
      <div class="relative shrink-0">
        <img
          :src="product.thumbnail_url || noImagePlaceholder"
          :alt="product.title"
          class="w-16 h-16 object-cover rounded border-2 border-purple-200"
        />
        <input
          type="checkbox"
          :checked="selected"
          @click.stop="$emit('toggle-select', product.id)"
          class="absolute top-0.5 left-0.5"
        />
      </div>
      <template v-if="product.gallery_thumb_urls && product.gallery_thumb_urls.length > 0">
        <img
          v-for="(thumbUrl, idx) in product.gallery_thumb_urls.slice(0, 3)"
          :key="idx"
          :src="thumbUrl"
          class="w-12 h-12 object-cover rounded border border-gray-200"
        />
        <div
          v-if="product.gallery_ids && product.gallery_ids.length > 3"
          class="w-12 h-12 bg-gray-100 rounded border border-gray-200 flex items-center justify-center text-xs text-gray-500 font-medium"
        >
          +{{ product.gallery_ids.length - 3 }}
        </div>
      </template>
    </div>

    <!-- Product info -->
    <div class="min-w-0">
      <div class="flex items-start justify-between gap-2">
        <h3 class="font-medium text-sm truncate" :title="product.title">{{ product.title }}</h3>
        <span
          v-if="product.image_coverage"
          :class="[
            'shrink-0 text-xs font-medium px-1.5 py-0.5 rounded',
            coverageBadgeClass
          ]"
        >
          {{ product.image_coverage.has_alt }}/{{ product.image_coverage.total }} alt
        </span>
      </div>
      <p v-if="product.sku" class="text-xs text-gray-500">{{ $t('SKU:') }} {{ product.sku }}</p>
      <div class="text-sm text-gray-600 mt-0.5" v-html="product.price"></div>

      <!-- Coverage progress bar -->
      <div v-if="product.image_coverage && product.image_coverage.total > 0" class="mt-2 h-1 bg-gray-200 rounded-full overflow-hidden">
        <div
          :class="['h-full rounded-full transition-all', coverageBarClass]"
          :style="{ width: coveragePercent + '%' }"
        ></div>
      </div>
    </div>

    <!-- Card actions -->
    <div class="flex gap-2 mt-3">
      <button
        @click.stop="$emit('open-modal', product)"
        class="text-xs flex-1 bg-gray-100 text-gray-700 px-2 py-1.5 rounded hover:bg-gray-200"
      >
        {{ $t('View Details') }}
      </button>
      <button
        @click.stop="$emit('generate-all', product)"
        :disabled="generating"
        class="text-xs flex-1 bg-purple-100 text-purple-700 px-2 py-1.5 rounded hover:bg-purple-200 disabled:opacity-50"
      >
        <template v-if="generating">
          {{ $t('Generating %1$s/%2$s...', generating.current, generating.total) }}
        </template>
        <template v-else>
          {{ $t('Generate All') }}
        </template>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { $t } from '../../utils/i18n'

const props = defineProps({
  product: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  generating: { type: Object, default: null }, // { current, total } or null
})

defineEmits(['toggle-select', 'open-modal', 'generate-all'])

const noImagePlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100'%3E%3Crect fill='%23ddd' width='100' height='100'/%3E%3Ctext x='50' y='55' text-anchor='middle' fill='%23999' font-size='12'%3ENo Image%3C/text%3E%3C/svg%3E"

const coveragePercent = computed(() => {
  const c = props.product.image_coverage
  if (!c || c.total === 0) return 0
  return Math.round((c.has_alt / c.total) * 100)
})

const coverageBadgeClass = computed(() => {
  const c = props.product.image_coverage
  if (!c || c.total === 0) return 'bg-gray-100 text-gray-600'
  const pct = c.has_alt / c.total
  if (pct >= 1) return 'bg-green-100 text-green-700'
  if (pct > 0) return 'bg-orange-100 text-orange-700'
  return 'bg-red-100 text-red-700'
})

const coverageBarClass = computed(() => {
  const c = props.product.image_coverage
  if (!c || c.total === 0) return 'bg-gray-300'
  const pct = c.has_alt / c.total
  if (pct >= 1) return 'bg-green-500'
  if (pct > 0) return 'bg-orange-500'
  return 'bg-red-500'
})
</script>
