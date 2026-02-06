<template>
  <div class="scanner p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold mb-4">{{ $t('Media Library Scanner') }}</h1>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">{{ $t('Total Images') }}</div>
          <div class="text-2xl font-bold">{{ scanStats.total_images || 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">{{ $t('Missing Alt Text') }}</div>
          <div class="text-2xl font-bold text-orange-600">{{ scanStats.missing_alt || 0 }}</div>
          <div class="text-xs text-gray-500">{{ scanStats.missing_percentage || 0 }}%</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">{{ $t('Has Alt Text') }}</div>
          <div class="text-2xl font-bold text-green-600">{{ scanStats.has_alt || 0 }}</div>
        </div>
      </div>

      <!-- Filters and Actions -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex gap-2">
          <button
            @click="filter = 'all'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('All Images') }}
          </button>
          <button
            @click="filter = 'missing'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'missing' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Missing Alt Text') }}
          </button>
          <button
            @click="filter = 'has_alt'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'has_alt' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Has Alt Text') }}
          </button>
        </div>

        <div class="flex gap-2">
          <button
            @click="loadImages()"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"
          >
            {{ $t('Refresh') }}
          </button>
          <button
            v-if="selectedImages.length > 0"
            @click="startBatch"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            {{ $t('Generate for %s Selected', selectedImages.length) }}
          </button>
          <button
            v-if="selectedImages.length > 0"
            @click="renameSelectedImages"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
          >
            {{ $t('Rename %s Selected', selectedImages.length) }}
          </button>
        </div>
      </div>

      <!-- Advanced Filters -->
      <div class="bg-gray-50 border rounded-lg p-4 mb-6">
        <button
          @click="showAdvancedFilters = !showAdvancedFilters"
          class="flex items-center gap-2 text-sm font-medium text-gray-700"
        >
          <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-90': showAdvancedFilters }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          {{ $t('Advanced Filters & Bulk Select') }}
        </button>

        <div v-if="showAdvancedFilters" class="mt-4 space-y-4">
          <!-- Date Range & File Size -->
          <div class="flex flex-wrap items-end gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Upload Date From') }}</label>
              <input
                v-model="dateFrom"
                type="date"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Upload Date To') }}</label>
              <input
                v-model="dateTo"
                type="date"
                class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Min File Size (KB)') }}</label>
              <input
                v-model.number="minSize"
                type="number"
                min="0"
                step="50"
                :placeholder="$t('e.g. 500')"
                class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <button
              @click="loadImages()"
              class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
            >
              {{ $t('Apply') }}
            </button>
            <button
              v-if="dateFrom || dateTo || minSize"
              @click="dateFrom = ''; dateTo = ''; minSize = 0; loadImages()"
              class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
            >
              {{ $t('Clear Filters') }}
            </button>
          </div>

          <!-- Bulk Select Buttons -->
          <div>
            <div class="text-xs font-medium text-gray-600 mb-2">{{ $t('Quick Bulk Select (across all pages)') }}</div>
            <div class="flex flex-wrap gap-2">
              <button
                @click="bulkSelect('missing')"
                :disabled="bulkSelecting"
                class="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 disabled:opacity-50"
              >
                {{ bulkSelecting === 'missing' ? $t('Selecting...') : $t('All Missing Alt Text') }}
              </button>
              <button
                @click="bulkSelect('generic')"
                :disabled="bulkSelecting"
                class="px-3 py-1.5 text-sm bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 disabled:opacity-50"
              >
                {{ bulkSelecting === 'generic' ? $t('Selecting...') : $t('All Generic / Placeholder') }}
              </button>
              <button
                @click="bulkSelect('too_long')"
                :disabled="bulkSelecting"
                class="px-3 py-1.5 text-sm bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 disabled:opacity-50"
              >
                {{ bulkSelecting === 'too_long' ? $t('Selecting...') : $t('All Too Long') }}
              </button>
              <button
                v-if="minSize > 0"
                @click="bulkSelect('large')"
                :disabled="bulkSelecting"
                class="px-3 py-1.5 text-sm bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 disabled:opacity-50"
              >
                {{ bulkSelecting === 'large' ? $t('Selecting...') : $t('All Larger Than %s KB', minSize) }}
              </button>
              <button
                v-if="selectedImages.length > 0"
                @click="selectedImages = []"
                class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
              >
                {{ $t('Clear Selection (%s)', selectedImages.length) }}
              </button>
            </div>
            <p v-if="bulkSelectCount !== null" class="text-xs text-gray-500 mt-2">
              {{ $t('%s images selected across all pages', bulkSelectCount) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Images Grid -->
    <div v-if="loading" class="text-center py-12 text-gray-500">
      {{ $t('Loading images...') }}
    </div>

    <div v-else class="bg-white rounded-lg shadow border">
      <div class="p-4 border-b">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            :checked="allSelected"
            @change="toggleSelectAll"
            class="rounded"
          />
          <span>{{ $t('Select All') }}</span>
        </label>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
        <div
          v-for="image in images"
          :key="image.id"
          @click="toggleSelect(image.id)"
          :class="['image-card p-2 rounded-lg border-2 cursor-pointer transition',
                   selectedImages.includes(image.id) ? 'border-blue-600 bg-blue-50' : 'border-gray-200']"
        >
          <div class="relative mb-2">
            <div
              class="cursor-pointer"
              @click.stop="openImageModal(image)"
            >
              <img
                :src="image.thumbnail || image.url"
                :alt="image.alt_text || image.filename"
                class="w-full h-24 object-cover rounded"
              />
            </div>
            <input
              type="checkbox"
              :checked="selectedImages.includes(image.id)"
              @click.stop="toggleSelect(image.id)"
              class="absolute top-1 right-1"
            />
          </div>
          <div class="text-xs truncate" :title="image.filename">
            {{ image.filename }}
          </div>
          <div class="text-xs mt-1" :class="image.has_alt ? 'text-green-600' : 'text-orange-600'">
            {{ image.has_alt ? $t('Has alt') : $t('Missing') }}
          </div>
          <div
            v-if="image.alt_text"
            class="text-xs mt-1 text-gray-600 line-clamp-2 break-words"
            :title="image.alt_text"
          >
            {{ image.alt_text }}
          </div>
          <div v-else class="text-xs mt-1 text-gray-400 italic">
            {{ $t('No alt text') }}
          </div>
          <div v-if="image.has_alt && renamingId !== image.id" class="mt-2">
            <button
              @click.stop="renameFile(image.id)"
              class="text-xs flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
              {{ $t('Rename') }}
            </button>
          </div>
          <div v-if="renamingId === image.id" class="mt-2">
            <span class="text-xs text-gray-500 flex items-center gap-1">
              <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              {{ $t('Renaming...') }}
            </span>
          </div>
          <div v-if="image.new_filename" class="mt-1">
            <span class="text-xs text-green-600">
              ✓ {{ image.new_filename }}
            </span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total_pages > 1" class="p-4 border-t flex justify-between items-center">
        <button
          @click="loadPage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="px-4 py-2 bg-gray-200 rounded-lg disabled:opacity-50"
        >
          {{ $t('Previous') }}
        </button>
        <span class="text-sm text-gray-600">
          {{ $t('Page %s of %s', pagination.current_page, pagination.total_pages) }}
        </span>
        <button
          @click="loadPage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.total_pages"
          class="px-4 py-2 bg-gray-200 rounded-lg disabled:opacity-50"
        >
          {{ $t('Next') }}
        </button>
      </div>
    </div>

    <!-- Single image modal (WP Media-style view) -->
    <Teleport to="body">
      <div
        v-if="selectedImage"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="closeImageModal"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
          <div class="p-4 border-b flex items-center justify-between shrink-0">
            <h2 class="text-lg font-bold">{{ $t('Attachment details') }}</h2>
            <button
              @click="closeImageModal"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              :aria-label="$t('Close')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-4 overflow-y-auto flex-1">
            <div class="flex gap-4 flex-col sm:flex-row">
              <div class="shrink-0">
                <img
                  :src="selectedImage.url"
                  :alt="modalAltText || selectedImage.filename"
                  class="max-h-64 w-full object-contain rounded-lg border bg-gray-50"
                />
              </div>
              <div class="flex-1 min-w-0 space-y-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('File name') }}</label>
                  <p class="text-sm text-gray-600 truncate" :title="selectedImage.filename">
                    {{ selectedImage.filename }}
                  </p>
                  <button
                    v-if="selectedImage.has_alt && !renamingId"
                    @click="renameFile(selectedImage.id)"
                    :disabled="renamingId === selectedImage.id"
                    class="mt-2 text-xs flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    {{ $t('Rename to SEO') }}
                  </button>
                </div>
                <div>
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <label class="block text-sm font-medium text-gray-700">{{ $t('Alt text') }}</label>
                    <button
                      type="button"
                      @click="generateAltForImage"
                      :disabled="generatingAlt"
                      class="text-sm px-3 py-1.5 rounded-lg text-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ generatingAlt ? $t('Generating...') : $t('Generate with AI') }}
                    </button>
                  </div>
                  <textarea
                    v-model="modalAltText"
                    rows="3"
                    class="w-full text-sm border rounded-lg p-2 resize-y focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    :placeholder="$t('Describe the image for accessibility...')"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    {{ $t('Alternative text helps describe the image for screen readers and SEO.') }}
                  </p>
                </div>
                <a
                  v-if="editAttachmentUrl"
                  :href="editAttachmentUrl"
                  target="_blank"
                  rel="noopener"
                  class="text-sm text-blue-600 hover:underline"
                >
                  {{ $t('Open in WordPress Media Library') }} →
                </a>
              </div>
            </div>
          </div>
          <div class="p-4 border-t flex justify-end gap-2 shrink-0">
            <button
              @click="closeImageModal"
              class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
              {{ $t('Cancel') }}
            </button>
            <button
              @click="saveImageAlt"
              :disabled="savingImageAlt"
              class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingImageAlt ? $t('Saving...') : $t('Update') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'

const router = useRouter()
const images = ref([])
const scanStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const selectedImages = ref([])
const renamingId = ref(null)
const showAdvancedFilters = ref(false)
const dateFrom = ref('')
const dateTo = ref('')
const minSize = ref(0)
const bulkSelecting = ref(false)
const bulkSelectCount = ref(null)

// Single image modal (WP Media-style view)
const selectedImage = ref(null)
const modalAltText = ref('')
const savingImageAlt = ref(false)
const generatingAlt = ref(false)
const pendingHistoryId = ref(null)

const allSelected = computed(() => {
  return images.value.length > 0 && selectedImages.value.length === images.value.length
})

const buildFilterParams = () => {
  let params = `filter=${filter.value}`
  if (dateFrom.value) params += `&date_from=${dateFrom.value}`
  if (dateTo.value) params += `&date_to=${dateTo.value}`
  return params
}

const loadImages = async (page = 1) => {
  loading.value = true
  try {
    const result = await Api.get(`/scanner/scan?${buildFilterParams()}&page=${page}&per_page=48`)
    if (result.success) {
      images.value = result.images
      scanStats.value = result.stats
      pagination.value = result.pagination
    }
  } catch (error) {
    notification.error($t('Failed to load images'))
  } finally {
    loading.value = false
  }
}

const loadPage = (page) => {
  if (page >= 1 && page <= pagination.value.total_pages) {
    loadImages(page)
  }
}

const toggleSelect = (imageId) => {
  const index = selectedImages.value.indexOf(imageId)
  if (index > -1) {
    selectedImages.value.splice(index, 1)
  } else {
    selectedImages.value.push(imageId)
  }
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedImages.value = []
  } else {
    selectedImages.value = images.value.map(img => img.id)
  }
}

const buildSelectParams = (filterOverride, quality) => {
  let params = `filter=${filterOverride}`
  if (dateFrom.value) params += `&date_from=${dateFrom.value}`
  if (dateTo.value) params += `&date_to=${dateTo.value}`
  if (minSize.value > 0) params += `&min_size=${minSize.value}`
  if (quality) params += `&quality=${quality}`
  return params
}

const bulkSelect = async (quality) => {
  bulkSelecting.value = quality
  bulkSelectCount.value = null
  try {
    let params
    if (quality === 'missing') {
      params = buildSelectParams('missing', '')
    } else if (quality === 'large') {
      params = buildSelectParams(filter.value, '')
    } else {
      params = buildSelectParams('has_alt', quality)
    }
    const result = await Api.get(`/scanner/select-all?${params}`)
    if (result.success) {
      selectedImages.value = result.image_ids
      bulkSelectCount.value = result.total
      if (result.total === 0) {
        notification.info($t('No images match this filter'))
      } else {
        notification.success($t('%s images selected', result.total))
      }
    }
  } catch (error) {
    notification.error($t('Failed to select images'))
  } finally {
    bulkSelecting.value = false
  }
}

const startBatch = async () => {
  if (selectedImages.value.length === 0) return

  if (selectedImages.value.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }

  try {
    const result = await Api.post('/batch/start', {
      image_ids: selectedImages.value
    })

    if (result.success) {
      notification.success($t('Batch job started for %s images', selectedImages.value.length))
      router.push(`/batch/${result.job_id}`)
    }
  } catch (error) {
    notification.error($t('Failed to start batch job'))
  }
}

function openImageModal(image) {
  selectedImage.value = image
  modalAltText.value = image.alt_text || ''
  pendingHistoryId.value = null
}

function closeImageModal() {
  selectedImage.value = null
  pendingHistoryId.value = null
}

const generateAltForImage = async () => {
  if (!selectedImage.value) return
  generatingAlt.value = true
  try {
    const result = await Api.post('/generator/generate', {
      attachment_id: selectedImage.value.id,
      tone: 'professional'
    })
    if (result.success && result.alt_text) {
      modalAltText.value = result.alt_text
      if (result.history_id) {
        pendingHistoryId.value = result.history_id
      } else {
        pendingHistoryId.value = null
      }
      notification.success($t('Alt text generated. Review and click Update to save.'))
    } else {
      notification.error(result.message || $t('Failed to generate alt text'))
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to generate alt text'))
  } finally {
    generatingAlt.value = false
  }
}

const editAttachmentUrl = computed(() => {
  if (!selectedImage.value) return ''
  const adminUrl = typeof window !== 'undefined' && window.wpAiAltText ? window.wpAiAltText.adminUrl : ''
  return adminUrl ? `${adminUrl}post.php?post=${selectedImage.value.id}&action=edit` : ''
})

const saveImageAlt = async () => {
  if (!selectedImage.value) return
  savingImageAlt.value = true
  try {
    let result
    if (pendingHistoryId.value) {
      result = await Api.post('/generator/accept', {
        history_id: pendingHistoryId.value,
        alt_text: modalAltText.value || ''
      })
    } else {
      result = await Api.post('/generator/update-alt', {
        attachment_id: selectedImage.value.id,
        alt_text: modalAltText.value || ''
      })
    }
    if (result.success) {
      const img = images.value.find(i => i.id === selectedImage.value.id)
      if (img) {
        img.alt_text = modalAltText.value || ''
        img.has_alt = !!modalAltText.value
      }
      notification.success($t('Alt text updated.'))
      closeImageModal()
    } else {
      notification.error(result.message || $t('Failed to update alt text'))
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to update alt text'))
  } finally {
    savingImageAlt.value = false
  }
}

const renameFile = async (imageId) => {
  renamingId.value = imageId
  try {
    const result = await Api.post('/renamer/rename', {
      attachment_id: imageId
    })
    if (result.success) {
      const img = images.value.find(i => i.id === imageId)
      if (img) {
        img.new_filename = result.new_filename
      }
      notification.success($t('Renamed to: %s', result.new_filename))
    } else {
      notification.error(result.error || $t('Failed to rename file'))
    }
  } catch (error) {
    notification.error($t('Failed to rename file'))
  } finally {
    renamingId.value = null
  }
}

const renameSelectedImages = async () => {
  const imagesWithAlt = selectedImages.value.filter(id => {
    const img = images.value.find(i => i.id === id)
    return img && img.has_alt
  })

  if (imagesWithAlt.length === 0) {
    notification.error($t('Selected images must have alt text to rename'))
    return
  }

  try {
    const result = await Api.post('/renamer/rename-bulk', {
      attachment_ids: imagesWithAlt
    })
    if (result.success || result.error_count === 0) {
      notification.success($t('Renamed %s files', result.success_count))
      loadImages()
      selectedImages.value = []
    } else {
      notification.error($t('Renamed %s/%s files', result.success_count, selectedImages.value.length))
    }
  } catch (error) {
    notification.error($t('Bulk rename failed'))
  }
}

onMounted(() => {
  loadImages()
})
</script>
