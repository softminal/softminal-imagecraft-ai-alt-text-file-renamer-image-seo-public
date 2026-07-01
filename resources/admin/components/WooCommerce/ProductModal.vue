<template>
  <Teleport to="body">
    <div
      v-if="product"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
      @click.self="$emit('close')"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div class="p-4 border-b shrink-0">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold">{{ product.title }}</h2>
              <p v-if="product.sku" class="text-sm text-gray-500">{{ $t('SKU:') }} {{ product.sku }}</p>
            </div>
            <button
              @click="$emit('close')"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              :aria-label="$t('Close')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <!-- Select all row with generate buttons -->
          <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <Checkbox
                v-if="images.length > 1"
                size="sm"
                :model-value="allSelected"
                :indeterminate="someSelected && !allSelected"
                class="text-sm text-gray-600"
                @change="toggleSelectAll"
              >
                {{ $t('Select All') }}
              </Checkbox>
              <span v-if="someSelected" class="text-xs text-gray-500">
                {{ $t('%s selected', selectedIds.length) }}
              </span>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <!-- Pick which fields to generate, then one Generate button. -->
              <div class="flex items-center gap-3">
                <Checkbox size="sm" v-model="bulkFields" value="alt">
                  <span class="text-sm text-gray-700">{{ $t('Alt text') }}</span>
                </Checkbox>
                <Checkbox size="sm" v-model="bulkFields" value="title">
                  <span class="text-sm text-gray-700">{{ $t('Title') }}</span>
                </Checkbox>
                <Checkbox size="sm" v-model="bulkFields" value="caption">
                  <span class="text-sm text-gray-700">{{ $t('Description') }}</span>
                </Checkbox>
              </div>
              <button
                @click="generateAll"
                :disabled="generatingAll || bulkFields.length === 0"
                class="text-sm px-4 py-1.5 rounded-lg bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ generatingAll ? $t('Generating...') : (someSelected ? $t('Generate (%s)', selectedIds.length) : $t('Generate All')) }}
              </button>
            </div>
          </div>
        </div>

        <!-- Progress bar -->
        <div v-if="generatingAll && progress.total > 0" class="px-4 pt-3">
          <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
            <span>{{ $t('Generating %1$s/%2$s...', progress.current, progress.total) }}</span>
            <span>{{ Math.round((progress.current / progress.total) * 100) }}%</span>
          </div>
          <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div
              class="h-full bg-purple-500 rounded-full transition-all duration-300"
              :style="{ width: (progress.current / progress.total * 100) + '%' }"
            ></div>
          </div>
        </div>

        <!-- Modal Body: Images Grid -->
        <div class="p-4 overflow-y-auto flex-1">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="img in images"
              :key="img.id"
              :class="[
                'border rounded-lg p-3 space-y-2 transition',
                selectedIds.includes(img.id) ? 'border-purple-400 bg-purple-50/50' : 'border-gray-200'
              ]"
            >
              <div class="flex gap-3">
                <div class="relative shrink-0">
                  <img
                    :src="img.thumb_url || img.url"
                    class="w-24 h-24 object-cover rounded border"
                  />
                  <Checkbox
                    size="sm"
                    :model-value="selectedIds.includes(img.id)"
                    class="absolute top-1 right-1"
                    @change="toggleImageSelect(img.id)"
                  />
                  <span
                    v-if="img.is_featured"
                    class="absolute top-1 left-1 bg-purple-600 text-white text-[10px] px-1.5 py-0.5 rounded font-medium"
                  >
                    {{ $t('Featured') }}
                  </span>
                </div>
                <div class="flex-1 min-w-0 space-y-2">
                  <!-- Alt text -->
                  <div>
                    <div class="flex items-center justify-between gap-1 mb-1">
                      <label class="text-xs font-medium text-gray-600">{{ $t('Alt Text') }}</label>
                      <button
                        @click="generateForImage(img, 'alt')"
                        :disabled="img.generating_alt"
                        class="text-xs text-purple-600 underline hover:text-purple-800 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                      >
                        {{ img.generating_alt ? '...' : $t('Generate') }}
                      </button>
                    </div>
                    <textarea
                      v-model="img.alt_text"
                      rows="2"
                      class="w-full text-xs border rounded p-1.5 resize-y"
                      :placeholder="$t('Alt text...')"
                    />
                  </div>
                  <!-- Title -->
                  <div>
                    <div class="flex items-center justify-between gap-1 mb-1">
                      <label class="text-xs font-medium text-gray-600">{{ $t('Title') }}</label>
                      <button
                        @click="generateForImage(img, 'title')"
                        :disabled="img.generating_title"
                        class="text-xs text-indigo-600 underline hover:text-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                      >
                        {{ img.generating_title ? '...' : $t('Generate') }}
                      </button>
                    </div>
                    <input
                      v-model="img.title"
                      type="text"
                      class="w-full text-xs border rounded p-1.5"
                      :placeholder="$t('Image title...')"
                    />
                  </div>
                  <!-- Description -->
                  <div>
                    <div class="flex items-center justify-between gap-1 mb-1">
                      <label class="text-xs font-medium text-gray-600">{{ $t('Description') }}</label>
                      <button
                        @click="generateForImage(img, 'caption')"
                        :disabled="img.generating_caption"
                        class="text-xs text-emerald-600 underline hover:text-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                      >
                        {{ img.generating_caption ? '...' : $t('Generate') }}
                      </button>
                    </div>
                    <textarea
                      v-model="img.caption"
                      rows="2"
                      class="w-full text-xs border rounded p-1.5 resize-y"
                      :placeholder="$t('Description...')"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 border-t flex justify-end gap-2 shrink-0">
          <button
            @click="$emit('close')"
            class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300"
          >
            {{ $t('Cancel') }}
          </button>
          <button
            @click="saveAll"
            :disabled="saving"
            class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50"
          >
            {{ saving ? $t('Saving...') : (someSelected ? $t('Save Selected (%s)', selectedIds.length) : $t('Save All Changes')) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import Api from '../../services/Api'
import { notification } from '../../plugins/notification'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

const props = defineProps({
  product: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

// State
const images = ref([])
const selectedIds = ref([])
const bulkFields = ref(['alt', 'title', 'caption']) // which fields the bulk Generate touches
const generatingAll = ref(false)
const progress = reactive({ current: 0, total: 0 })
const saving = ref(false)

const allSelected = computed(() => {
  return images.value.length > 0 && selectedIds.value.length === images.value.length
})

const someSelected = computed(() => {
  return selectedIds.value.length > 0
})

// Target images for bulk ops: selected if any, otherwise all
const getTargetImages = (filterFn) => {
  const pool = someSelected.value
    ? images.value.filter(img => selectedIds.value.includes(img.id))
    : images.value
  return filterFn ? pool.filter(filterFn) : pool
}

// Selection
const toggleImageSelect = (id) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = images.value.map(img => img.id)
  }
}

// Load images when product changes
watch(() => props.product, async (product) => {
  images.value = []
  selectedIds.value = []
  generatingAll.value = false
  progress.current = 0
  progress.total = 0

  if (!product) return

  // Featured image first
  if (product.thumbnail_id) {
    images.value.push({
      id: product.thumbnail_id,
      url: product.full_url,
      thumb_url: product.thumbnail_url,
      alt_text: product.alt_text || '',
      title: product.image_title || '',
      caption: product.image_caption || '',
      is_featured: true,
      generating_alt: false,
      generating_title: false,
      generating_caption: false,
    })
  }

  // Load gallery
  try {
    const result = await Api.get(`/woocommerce/gallery?product_id=${product.id}`)
    if (result.success && result.gallery) {
      for (const gImg of result.gallery) {
        images.value.push({
          id: gImg.id,
          url: gImg.url,
          thumb_url: gImg.thumb_url,
          alt_text: gImg.alt_text || '',
          title: gImg.title || '',
          caption: gImg.caption || '',
          is_featured: false,
          generating_alt: false,
          generating_title: false,
          generating_caption: false,
        })
      }
    }
  } catch (error) {
    // Gallery loading failed silently
  }
}, { immediate: true })

// Per-image generation. `field` is one of 'alt' | 'title' | 'caption'. Uses the
// combined generate-meta endpoint (single AI call) restricted to the one field so
// alt, title and description all share the same code path and product context.
const FIELD_FLAG = { alt: 'generating_alt', title: 'generating_title', caption: 'generating_caption' }

const generateForImage = async (img, field = 'alt') => {
  if (!props.product) return

  const flag = FIELD_FLAG[field]
  img[flag] = true
  try {
    const result = await Api.post('/generator/generate-meta', {
      attachment_id: img.id,
      product_id: props.product.id,
      fields: [field],
    })
    if (result.success) {
      if (field === 'alt' && typeof result.alt_text === 'string') img.alt_text = result.alt_text
      if (field === 'title' && typeof result.title === 'string') img.title = result.title
      if (field === 'caption' && typeof result.caption === 'string') img.caption = result.caption
    } else {
      notification.error(result.error || result.message || $t('Failed to generate'))
    }
  } catch (error) {
    notification.error($t('Failed to generate'))
  } finally {
    img[flag] = false
  }
}

// Field accessor so bulk ops can read/skip whichever fields are selected.
const fieldValue = (img, field) =>
  field === 'alt' ? img.alt_text : field === 'title' ? img.title : img.caption

// Bulk generation for the fields ticked in the header (alt / title / description).
const generateAll = async () => {
  if (generatingAll.value || images.value.length === 0) return

  const fields = bulkFields.value.slice()
  if (fields.length === 0) return

  // Only (image, field) pairs that are still empty, so we don't overwrite edits.
  const pool = getTargetImages()
  const work = []
  for (const img of pool) {
    for (const field of fields) {
      if (!fieldValue(img, field)) work.push({ img, field })
    }
  }

  if (work.length === 0) {
    notification.info($t('Selected images already have the chosen fields'))
    return
  }

  generatingAll.value = true
  progress.current = 0
  progress.total = work.length

  for (const { img, field } of work) {
    await generateForImage(img, field)
    progress.current++
  }

  generatingAll.value = false
  notification.success($t('Generated metadata for %s images', pool.length))
}

// Save
const saveAll = async () => {
  if (!props.product || saving.value) return
  saving.value = true

  const toSave = getTargetImages()
  let successCount = 0
  let errorCount = 0

  for (const img of toSave) {
    try {
      const result = await Api.post('/generator/update-alt', {
        attachment_id: img.id,
        alt_text: img.alt_text || '',
        title: img.title || '',
        caption: img.caption || ''
      })
      if (result.success) {
        successCount++
      } else {
        errorCount++
      }
    } catch (error) {
      errorCount++
    }
  }

  if (errorCount > 0) {
    notification.warning($t('Saved %1$s/%2$s images', successCount, toSave.length))
  } else {
    notification.success($t('All changes saved'))
  }

  saving.value = false
  emit('saved')
}
</script>
