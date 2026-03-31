<template>
  <div class="scanner p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold mb-4">{{ $t('Media Library Scanner') }}</h1>

      <!-- Interrupted batch banner -->
      <div v-if="interruptedBatchId" class="bg-amber-50 border border-amber-300 rounded-lg p-4 mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm text-amber-800">{{ $t('You have an interrupted batch job. Would you like to resume it?') }}</p>
        </div>
        <button
          @click="router.push('/batch')"
          class="bg-amber-600 text-white px-4 py-1.5 text-sm rounded-lg hover:bg-amber-700 shrink-0"
        >
          {{ $t('Resume') }}
        </button>
      </div>

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
            @click="filter = 'all'; debouncedLoadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('All Images') }}
          </button>
          <button
            @click="filter = 'missing'; debouncedLoadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'missing' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Missing Alt Text') }}
          </button>
          <button
            @click="filter = 'has_alt'; debouncedLoadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'has_alt' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Has Alt Text') }}
          </button>
          <button
            @click="filter = 'generic'; debouncedLoadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'generic' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Generic / Placeholder') }}
          </button>
          <button
            @click="filter = 'too_long'; debouncedLoadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'too_long' ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            {{ $t('Too Long') }}<template v-if="scanStats.max_length"> (&gt;{{ scanStats.max_length }})</template>
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
            {{ $t('Generate Alt Text for %s Selected', selectedImages.length) }}
          </button>
          <button
            v-if="selectedImages.length > 0"
            @click="startTitleBatch"
            class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700"
          >
            {{ $t('Generate Titles for %s Selected', selectedImages.length) }}
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
              @click="allPagesSelected = false; selectedImages = []; loadImages()"
              class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
            >
              {{ $t('Apply') }}
            </button>
            <button
              v-if="dateFrom || dateTo || minSize"
              @click="dateFrom = ''; dateTo = ''; minSize = 0; allPagesSelected = false; selectedImages = []; loadImages()"
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
    <div v-if="loading && images.length === 0" class="text-center py-12 text-gray-500">
      {{ $t('Loading images...') }}
    </div>

    <div v-else class="bg-white rounded-lg shadow border" :class="{ 'opacity-60 pointer-events-none': loading }">
      <div class="p-4 border-b flex items-center justify-between">
        <div class="flex items-center flex-wrap gap-2">
        <label class="inline-flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            :checked="allSelected"
            @change="toggleSelectAll"
            class="rounded"
          />
          <span>{{ $t('Select All') }}</span>
        </label>

        <div
          v-if="allSelected && pagination.total > images.length && !allPagesSelected"
          class="ml-2 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1 inline-flex items-center gap-2"
        >
          <span>{{ $t('All %s images on this page are selected.', images.length) }}</span>
          <button
            @click="selectAllPages"
            :disabled="bulkSelecting"
            class="font-semibold underline hover:text-blue-900"
          >
            {{ bulkSelecting ? $t('Selecting...') : $t('Select all %s matching images', Math.min(pagination.total, 500)) }}
          </button>
        </div>
        <div
          v-if="allPagesSelected"
          class="ml-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-1 inline-flex items-center gap-2"
        >
          <span>{{ $t('All %s matching images are selected.', selectedImages.length) }}</span>
          <button
            @click="selectedImages = []; allPagesSelected = false"
            class="font-semibold underline hover:text-green-900"
          >
            {{ $t('Clear selection') }}
          </button>
        </div>
        </div>
        <span v-if="selectedImages.length > 0" class="text-sm font-medium text-gray-700">
          {{ $t('%s selected', selectedImages.length) }}
        </span>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
        <ImageCard
          v-for="image in images"
          :key="image.id"
          :image="image"
          :selected="selectedImages.includes(image.id)"
          :renaming="renamingId === image.id"
          :alt-too-long="isAltTooLong(image)"
          :auto-rename="autoRename"
          @toggle-select="toggleSelect"
          @open-modal="openImageModal"
          @rename="renameFile"
        />
      </div>

      <!-- Pagination -->
      <div class="text-center">
        <Pagination
        :current-page="pagination.current_page || 1"
        :total-pages="pagination.total_pages || 1"
        :total="pagination.total || 0"
        :per-page="perPage"
        show-per-page
        @page-change="loadPage"
        :page-sizes="[10, 20, 50, 100]"
        :align="'right'"
        @per-page-change="perPage = $event; allPagesSelected = false; selectedImages = []; loadImages()"
      />
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'
import { debounce } from '../utils/debounce'
import ImageCard from '../components/Scanner/ImageCard.vue'
import { Pagination } from '../@ui'

const router = useRouter()
const route = useRoute()
const images = ref([])
const scanStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const interruptedBatchId = ref(null)
const selectedImages = ref([])
const renamingId = ref(null)
const showAdvancedFilters = ref(false)
const dateFrom = ref('')
const dateTo = ref('')
const minSize = ref(0)
const perPage = ref(20)
const bulkSelecting = ref(false)
const bulkSelectCount = ref(null)
const allPagesSelected = ref(false)
const autoRename = window.icais?.autoRename === '1' || window.icais?.autoRename === 1

// AbortController for cancelling stale requests
let loadAbortController = null

const allSelected = computed(() => {
  return images.value.length > 0 && images.value.every(img => selectedImages.value.includes(img.id))
})

const isAltTooLong = (image) => {
  return image.alt_text && scanStats.value.max_length && image.alt_text.length > scanStats.value.max_length
}

const buildFilterParams = () => {
  let params = `filter=${filter.value}`
  if (dateFrom.value) params += `&date_from=${dateFrom.value}`
  if (dateTo.value) params += `&date_to=${dateTo.value}`
  if (minSize.value > 0) params += `&min_size=${minSize.value}`
  return params
}

const loadImages = async (page = 1) => {
  // Abort any in-flight request before starting a new one
  if (loadAbortController) {
    loadAbortController.abort()
  }
  loadAbortController = new AbortController()

  loading.value = true
  try {
    const result = await Api.get(
      `/scanner/scan?${buildFilterParams()}&page=${page}&per_page=${perPage.value}`,
      { signal: loadAbortController.signal }
    )
    if (result.success) {
      images.value = result.images
      scanStats.value = result.stats
      pagination.value = result.pagination
    }
  } catch (error) {
    if (error.name === 'AbortError') return
    notification.error($t('Failed to load images'))
  } finally {
    loading.value = false
  }
}

// Debounced version for rapid filter clicks — resets cross-page selection since criteria changed
const debouncedLoadImages = debounce(() => {
  allPagesSelected.value = false
  selectedImages.value = []
  loadImages()
}, 300)

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
  const currentPageIds = images.value.map(img => img.id)
  if (allSelected.value) {
    // Uncheck: remove only current page's images from selection
    selectedImages.value = selectedImages.value.filter(id => !currentPageIds.includes(id))
    allPagesSelected.value = false
  } else {
    // Check: add current page's images to existing selection (no duplicates)
    const existing = new Set(selectedImages.value)
    for (const id of currentPageIds) {
      if (!existing.has(id)) {
        selectedImages.value.push(id)
      }
    }
  }
}

const selectAllPages = async () => {
  bulkSelecting.value = 'all'
  try {
    const params = buildFilterParams()
    const result = await Api.get(`/scanner/select-all?${params}`)
    if (result.success) {
      // Cap at 500 to match the batch job limit
      const ids = result.image_ids.slice(0, 500)
      selectedImages.value = ids
      allPagesSelected.value = true
      bulkSelectCount.value = ids.length
      notification.success($t('%s images selected', ids.length))
    }
  } catch (error) {
    notification.error($t('Failed to select images'))
  } finally {
    bulkSelecting.value = false
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
      const ids = result.image_ids.slice(0, 500)
      selectedImages.value = ids
      bulkSelectCount.value = ids.length
      allPagesSelected.value = ids.length > images.value.length
      if (ids.length === 0) {
        notification.info($t('No images match this filter'))
      } else {
        notification.success($t('%s images selected', ids.length))
      }
    }
  } catch (error) {
    notification.error($t('Failed to select images'))
  } finally {
    bulkSelecting.value = false
  }
}

const startTitleBatch = async () => {
  if (selectedImages.value.length === 0) return

  if (selectedImages.value.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }

  try {
    const result = await Api.post('/batch/start', {
      image_ids: selectedImages.value,
      type: 'title'
    })

    if (result.success) {
      notification.success($t('Title generation started for %s images', selectedImages.value.length))
      router.push(`/batch/${result.job_id}`)
    }
  } catch (error) {
    if (error.response?.job_id) {
      notification.warning($t('A batch job is already in progress.'))
      router.push(`/batch/${error.response.job_id}`)
    } else {
      notification.error(error.message || $t('Failed to start batch'))
    }
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
    // If there's an active job, offer to resume it
    if (error.response?.job_id) {
      notification.warning($t('A batch job is already in progress.'))
      router.push(`/batch/${error.response.job_id}`)
    } else {
      notification.error(error.message || $t('Failed to start batch job'))
    }
  }
}

function openImageModal(image) {
  if (typeof wp === 'undefined' || !wp.media) return

  var frame = wp.media({
    frame: 'select',
    title: 'Attachment Details',
    library: { type: 'image' },
    button: { text: 'Close' },
    multiple: false
  })

  frame.on('open', function () {
    var selection = frame.state().get('selection')
    var attachment = wp.media.attachment(image.id)
    attachment.fetch()
    selection.add(attachment)
  })

  frame.on('close', function () {
    // Refresh images + stats when modal closes (user may have edited alt text)
    loadImages(pagination.value.current_page)
  })

  frame.open()
}

const renameFile = async (imageId) => {
  renamingId.value = imageId
  try {
    const result = await Api.post('/generator/generate', {
      attachment_id: imageId,
      filename_only: true
    })
    if (result.success) {
      const img = images.value.find(i => i.id === imageId)
      if (img) {
        img.new_filename = result.new_filename
      }
      notification.success($t('Renamed to: %s', result.new_filename))
    } else {
      notification.error(result.message || $t('Failed to rename file'))
    }
  } catch (error) {
    notification.error($t('Failed to rename file'))
  } finally {
    renamingId.value = null
  }
}

onMounted(() => {
  const queryFilter = route.query.filter
  if (queryFilter && ['missing', 'has_alt', 'generic', 'too_long'].includes(queryFilter)) {
    filter.value = queryFilter
  }
  loadImages()

  // Check for interrupted batch job (non-blocking)
  // Only show banner if job is interrupted (no heartbeat), not if actively running
  Api.get('/batch/active').then(result => {
    if (result?.success && result.has_active && !result.is_running) {
      interruptedBatchId.value = result.job.id
    }
  }).catch(() => {})
})

onUnmounted(() => {
  if (loadAbortController) {
    loadAbortController.abort()
  }
})
</script>
