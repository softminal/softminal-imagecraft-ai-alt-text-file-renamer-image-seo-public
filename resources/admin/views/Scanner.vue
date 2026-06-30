<template>
  <div class="scanner p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold mb-4 mt-0!">{{ $t('Image SEO') }}</h1>

      <!-- No API key configured: AI generation needs a key. -->
      <div v-if="!hasAiKey" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm text-blue-800">
            {{ $t('Add your API key to generate alt text, titles and captions.') }}
          </p>
        </div>
        <a href="#/settings/api" class="shrink-0 text-sm font-semibold text-blue-700 underline hover:text-blue-900">
          {{ $t('Add API key') }}
        </a>
      </div>

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

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
          <!-- All -->
          <button
            @click="filter = 'all'; debouncedLoadImages()"
            :class="[
              'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
              filter === 'all'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >
            {{ $t('All') }}
            <span :class="['ml-1 text-xs', filter === 'all' ? 'text-blue-200' : 'text-gray-500']">{{ scanStats.total_images || 0 }}</span>
          </button>

          <div class="w-px h-6 bg-gray-200"></div>

          <!-- Alt Text group -->
          <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $t('Alt Text') }}</span>
          <div class="flex items-center gap-1.5">
            <button
              @click="filter = 'missing'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'missing'
                  ? 'bg-red-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Missing') }}
              <span :class="['ml-1 text-xs', filter === 'missing' ? 'text-red-200' : 'text-gray-500']">{{ scanStats.missing_alt || 0 }}</span>
            </button>
            <button
              @click="filter = 'has_alt'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'has_alt'
                  ? 'bg-emerald-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Has') }}
              <span :class="['ml-1 text-xs', filter === 'has_alt' ? 'text-emerald-200' : 'text-gray-500']">{{ scanStats.has_alt || 0 }}</span>
            </button>
            <button
              @click="filter = 'generic'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'generic'
                  ? 'bg-amber-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Generic') }}
            </button>
            <button
              @click="filter = 'too_long'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'too_long'
                  ? 'bg-orange-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Long') }}<template v-if="scanStats.max_length"><span :class="['ml-0.5 text-xs', filter === 'too_long' ? 'text-orange-200' : 'text-gray-500']">&gt;{{ scanStats.max_length }}</span></template>
            </button>
          </div>

          <div class="w-px h-6 bg-gray-200"></div>

          <!-- Title group -->
          <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $t('Title') }}</span>
          <div class="flex items-center gap-1.5">
            <button
              @click="filter = 'missing_title'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'missing_title'
                  ? 'bg-red-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Missing') }}
              <span :class="['ml-1 text-xs', filter === 'missing_title' ? 'text-red-200' : 'text-gray-500']">{{ scanStats.missing_title || 0 }}</span>
            </button>
            <button
              @click="filter = 'has_title'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'has_title'
                  ? 'bg-emerald-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Has') }}
              <span :class="['ml-1 text-xs', filter === 'has_title' ? 'text-emerald-200' : 'text-gray-500']">{{ scanStats.has_title || 0 }}</span>
            </button>
            <button
              @click="filter = 'title_too_long'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'title_too_long'
                  ? 'bg-orange-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Long') }}<template v-if="scanStats.title_max_length"><span :class="['ml-0.5 text-xs', filter === 'title_too_long' ? 'text-orange-200' : 'text-gray-500']">&gt;{{ scanStats.title_max_length }}</span></template>
            </button>
          </div>

          <div class="w-px h-6 bg-gray-200"></div>

          <!-- Description group -->
          <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $t('Description') }}</span>
          <div class="flex items-center gap-1.5">
            <button
              @click="filter = 'missing_caption'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'missing_caption'
                  ? 'bg-red-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Missing') }}
              <span :class="['ml-1 text-xs', filter === 'missing_caption' ? 'text-red-200' : 'text-gray-500']">{{ scanStats.missing_caption || 0 }}</span>
            </button>
            <button
              @click="filter = 'has_caption'; debouncedLoadImages()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm transition-colors',
                filter === 'has_caption'
                  ? 'bg-emerald-500 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('Has') }}
              <span :class="['ml-1 text-xs', filter === 'has_caption' ? 'text-emerald-200' : 'text-gray-500']">{{ scanStats.has_caption || 0 }}</span>
            </button>
          </div>

          <!-- Clear filter -->
          <div v-if="filter !== 'all'" class="w-px h-6 bg-gray-200"></div>
          <button
            v-if="filter !== 'all'"
            @click="filter = 'all'; debouncedLoadImages()"
            class="px-3 py-1.5 rounded-full text-sm text-red-600 bg-red-50 hover:bg-red-100 transition-colors"
          >
            &times; {{ $t('Clear') }}
          </button>

        </div>

        <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-end gap-3">
          <div v-if="folderTree.length > 0" class="relative" ref="folderDropdownRef">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('FileBird Folder') }}</label>
            <button
              type="button"
              class="min-w-56 h-8 flex items-center justify-between gap-2 px-3 border border-gray-300 rounded-lg text-sm bg-white hover:border-gray-400 text-left"
              @click="folderDropdownOpen = !folderDropdownOpen"
            >
              <span class="truncate">{{ activeFolderName || $t('All folders') }}</span>
              <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="{ 'rotate-180': folderDropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div
              v-if="folderDropdownOpen"
              class="absolute z-50 mt-1 min-w-56 bg-white border border-gray-100 rounded-lg shadow-sm py-1"
            >
              <button
                type="button"
                class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50"
                :class="{ 'bg-blue-50 text-blue-700 font-medium': selectedFolderId === '0' }"
                @click="selectFolder('0')"
              >
                {{ $t('All folders') }}
              </button>
              <FolderCascadeMenu
                :folders="folderTree"
                @select="selectFolder(String($event))"
              />
            </div>
          </div>

          <!-- Date range + file size filters -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Upload Date From') }}</label>
            <input v-model="dateFrom" type="date" class="icais-input" />
          </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Upload Date To') }}</label>
              <input v-model="dateTo" type="date" class="icais-input" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Min File Size (KB)') }}</label>
              <input v-model.number="minSize" type="number" min="0" step="50" :placeholder="$t('e.g. 500')" class="icais-input w-28" />
            </div>
            <button
              @click="allPagesSelected = false; selectedImages = []; loadImages()"
              class="px-4 h-8 inline-flex items-center bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
            >
              {{ $t('Apply') }}
            </button>
            <button
              v-if="hasAdvancedFilters"
              @click="dateFrom = ''; dateTo = ''; minSize = 0; allPagesSelected = false; selectedImages = []; loadImages()"
              class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
            >
              {{ $t('Clear') }}
            </button>

          <!-- divider between filter controls and bulk actions -->

          <!-- Quick bulk select (same row) -->
          <div class="self-end mb-1">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Quick Bulk Select') }}</label>
           <div class="flex gap-2">
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
              v-if="minSize > 0"
              @click="bulkSelect('large')"
              :disabled="bulkSelecting"
              class="px-3 py-1.5 text-sm bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 disabled:opacity-50"
            >
              {{ bulkSelecting === 'large' ? $t('Selecting...') : $t('All Larger Than %s KB', minSize) }}
            </button>
           </div>
          </div>

          <!-- secondary info wraps to its own line -->
          <p v-if="activeFolderName" class="w-full text-sm text-gray-500">
            {{ $t('Showing images from') }} <span class="font-bold text-gray-900">{{ activeFolderName }}</span>
          </p>
          <span v-if="bulkSelectCount !== null" class="w-full text-xs text-gray-500">
            {{ $t('%s images selected across all pages', bulkSelectCount) }}
          </span>
        </div>
      </div>

    </div>

    <!-- Images Grid -->
    <div v-if="loading && images.length === 0" class="text-center py-12 text-gray-500">
      {{ $t('Loading images...') }}
    </div>

    <div v-else class="bg-white rounded-lg shadow border" :class="{ 'opacity-60 pointer-events-none': loading }">
      <div v-if="isAdmin" class="p-4 border-b flex items-center justify-between gap-4">
        <div class="flex items-center flex-wrap gap-2">
          <Checkbox :model-value="allSelected" @change="toggleSelectAll">
            <span>{{ $t('Select All') }}</span>
          </Checkbox>

          <div
            v-if="allSelected && pagination.total > images.length && !allPagesSelected"
            class="text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1 inline-flex items-center gap-1.5"
          >
            <span>{{ $t('Page selected.') }}</span>
            <button
              @click="selectAllPages"
              :disabled="bulkSelecting"
              class="font-semibold underline hover:text-blue-900"
            >
              {{ bulkSelecting ? $t('Selecting...') : $t('Select all %s', Math.min(pagination.total, 500)) }}
            </button>
          </div>
          <div
            v-if="allPagesSelected"
            class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-1 inline-flex items-center gap-1.5"
          >
            <span>{{ $t('All %s selected.', selectedImages.length) }}</span>
            <button
              @click="selectedImages = []; allPagesSelected = false"
              class="font-semibold underline hover:text-green-900"
            >
              {{ $t('Clear') }}
            </button>
          </div>
        </div>

        <transition name="icais-btn-pop">
          <div v-if="selectedImages.length > 0" class="flex items-center gap-4 shrink-0">
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
              :disabled="bulkFields.length === 0"
              @click="startGenerate"
              class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ $t('Generate (%s)', selectedImages.length) }}
            </button>
          </div>
        </transition>
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
        @per-page-change="perPage = $event; loadImages()"
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
import FolderCascadeMenu from '../components/Scanner/FolderCascadeMenu.vue'
import { Pagination, Checkbox } from '../@ui'

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
const dateFrom = ref('')
const dateTo = ref('')
const minSize = ref(0)
const perPage = ref(20)
const bulkSelecting = ref(false)
const bulkSelectCount = ref(null)
const allPagesSelected = ref(false)
const fileBirdFolders = ref([])
const selectedFolderId = ref('0')
const folderDropdownOpen = ref(false)
const folderDropdownRef = ref(null)
const autoRename = window.icais?.autoRename === '1' || window.icais?.autoRename === 1
// Bulk batch actions are admin-only (batch/* requires manage_options). Non-admin
// permitted roles generate per-image instead.
const isAdmin = window.icais?.isAdmin ?? false
// Bulk generate field picker (alt / title / caption). One combined AI call per
// image generates whichever fields are ticked. Defaults come from the Generate-All
// settings (icais_meta_include_*), falling back to all-on.
const defaultBulkFields = ['alt', 'title', 'caption'].filter(
  (f) => window.icais?.['metaInclude' + f.charAt(0).toUpperCase() + f.slice(1)] !== '0'
)
const bulkFields = ref(defaultBulkFields.length ? defaultBulkFields : ['alt', 'title', 'caption'])
// Show the "add API key" prompt only when there is no usable AI provider
// (undefined → assume configured, so existing setups never see a spurious nag).
const hasAiKey = window.icais?.hasAiKey ?? true

// Sync filters to URL query params
const syncFiltersToUrl = () => {
  const query = {}
  if (filter.value !== 'all') query.filter = filter.value
  if (dateFrom.value) query.date_from = dateFrom.value
  if (dateTo.value) query.date_to = dateTo.value
  if (minSize.value > 0) query.min_size = String(minSize.value)
  if (selectedFolderId.value !== '0') query.folder_id = selectedFolderId.value
  if (perPage.value !== 20) query.per_page = String(perPage.value)
  router.replace({ query }).catch(() => {})
}

const restoreFiltersFromUrl = () => {
  const q = route.query
  if (q.filter && ['all', 'missing', 'has_alt', 'generic', 'too_long', 'missing_title', 'has_title', 'title_too_long', 'missing_caption', 'has_caption', 'caption_too_long'].includes(q.filter)) {
    filter.value = q.filter
  }
  if (q.date_from) dateFrom.value = q.date_from
  if (q.date_to) dateTo.value = q.date_to
  if (q.min_size) minSize.value = Number(q.min_size) || 0
  if (q.folder_id) selectedFolderId.value = String(q.folder_id)
  if (q.per_page) perPage.value = Number(q.per_page) || 20
}

// AbortController for cancelling stale requests
let loadAbortController = null

const hasAdvancedFilters = computed(() => {
  return Boolean(dateFrom.value || dateTo.value || minSize.value > 0)
})

const folderTree = computed(() => {
  const folders = fileBirdFolders.value
  if (!folders.length) return []

  const map = {}
  folders.forEach(f => { map[f.id] = { ...f, children: [] } })

  const roots = []
  folders.forEach(f => {
    if (f.parent_id && map[f.parent_id]) {
      map[f.parent_id].children.push(map[f.id])
    } else {
      roots.push(map[f.id])
    }
  })

  return roots
})

const activeFolderName = computed(() => {
  const match = fileBirdFolders.value.find(folder => String(folder.id) === selectedFolderId.value)
  return match ? match.name : ''
})

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
  if (selectedFolderId.value !== '0') params += `&folder_id=${selectedFolderId.value}`
  return params
}

const loadImages = async (page = 1) => {
  // Abort any in-flight request before starting a new one
  if (loadAbortController) {
    loadAbortController.abort()
  }
  loadAbortController = new AbortController()

  loading.value = true
  syncFiltersToUrl()
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
  } catch {
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
  if (selectedFolderId.value !== '0') params += `&folder_id=${selectedFolderId.value}`
  if (quality) params += `&quality=${quality}`
  return params
}

const selectFolder = (id) => {
  selectedFolderId.value = id
  folderDropdownOpen.value = false
  allPagesSelected.value = false
  selectedImages.value = []
  bulkSelectCount.value = null
  loadImages()
}

const onClickOutsideFolderDropdown = (e) => {
  if (folderDropdownRef.value && !folderDropdownRef.value.contains(e.target)) {
    folderDropdownOpen.value = false
  }
}

const loadFileBirdFolders = async () => {
  try {
    const result = await Api.getFileBirdFolders()
    if (result.success) {
      fileBirdFolders.value = result.folders || []
      if (selectedFolderId.value !== '0' && !fileBirdFolders.value.some(folder => String(folder.id) === selectedFolderId.value)) {
        selectedFolderId.value = '0'
      }
    }
  } catch {
    fileBirdFolders.value = []
  }
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
  } catch {
    notification.error($t('Failed to select images'))
  } finally {
    bulkSelecting.value = false
  }
}

// Unified bulk generate: one combined AI call per image for the ticked fields
// (alt / title / caption). Replaces the separate Generate Alt/Title/All buttons.
const startGenerate = async () => {
  if (selectedImages.value.length === 0 || bulkFields.value.length === 0) return

  if (selectedImages.value.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }

  try {
    const result = await Api.post('/batch/start', {
      image_ids: selectedImages.value,
      type: 'meta',
      fields: bulkFields.value
    })

    if (result.success) {
      notification.success($t('Generation started for %s images', selectedImages.value.length))
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
  } catch {
    notification.error($t('Failed to rename file'))
  } finally {
    renamingId.value = null
  }
}

onMounted(async () => {
  restoreFiltersFromUrl()
  await loadFileBirdFolders()
  loadImages()
  document.addEventListener('click', onClickOutsideFolderDropdown)

  // Check for interrupted batch job (non-blocking, admin-only — batch/* is manage_options)
  // Only show banner if job is interrupted (no heartbeat), not if actively running
  if (isAdmin) {
    Api.get('/batch/active').then(result => {
      if (result?.success && result.has_active && !result.is_running) {
        interruptedBatchId.value = result.job.id
      }
    }).catch(() => {})
  }
})

onUnmounted(() => {
  if (loadAbortController) {
    loadAbortController.abort()
  }
  document.removeEventListener('click', onClickOutsideFolderDropdown)
})
</script>

<style scoped>
/* Smoothly fade + slide the bulk action buttons in/out as selection changes. */
.icais-btn-pop-enter-active,
.icais-btn-pop-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.icais-btn-pop-enter-from,
.icais-btn-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.96);
}
</style>
