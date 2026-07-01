<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 m-0!">{{ $t('Image Compression') }}</h1>
        <p class="text-gray-500 mt-2">{{ $t('Shrink image file sizes to speed up your site. Originals are always backed up.') }}</p>
      </div>
      <div v-if="!isPro" class="text-right">
        <div class="text-sm text-gray-600">
          {{ $t('Daily usage') }}: <strong>{{ usage.used }} / {{ usage.limit }}</strong>
        </div>
        <a
          :href="proUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="text-xs text-amber-600 font-semibold hover:underline"
        >{{ $t('Upgrade to Pro for unlimited') }}</a>
      </div>
    </div>

    <!-- Not enabled gate -->
    <div v-if="!enabled" class="bg-white rounded-lg shadow border p-8 text-center">
      <p class="text-gray-600 mb-4">{{ $t('Image compression is turned off.') }}</p>
      <router-link
        to="/settings/compression"
        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white! no-underline rounded-lg hover:bg-blue-700 hover:text-white!"
      >{{ $t('Enable in Settings') }}</router-link>
    </div>

    <template v-else>
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex gap-2">
          <button
            v-for="f in filters"
            :key="f.value"
            @click="setFilter(f.value)"
            :class="[
              'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
              filter === f.value ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >{{ $t(f.label) }}<template v-if="f.value === 'compressed'"> ({{ stats.total_compressed || 0 }})</template></button>
          <span class="w-px h-6 self-center bg-gray-200 mx-1"></span>
          <button
            @click="toggleSort"
            :title="$t('Show the biggest files first')"
            :class="[
              'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
              sort === 'largest' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9M3 12h5m4 8l4-4m0 0l4 4m-4-4V8" />
            </svg>
            {{ $t('Largest first') }}
          </button>
        </div>

        <!-- Size filter -->
        <div class="flex items-center gap-2">
          <label class="text-sm text-gray-600 shrink-0">{{ $t('Larger than (KB)') }}</label>
          <input
            v-model.number="minSize"
            type="number"
            min="0"
            step="10"
            :placeholder="$t('e.g. 60')"
            class="icais-input w-24"
            @keyup.enter="applyMinSize"
          />
          <button
            @click="applyMinSize"
            class="px-3 h-8 inline-flex items-center bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
          >{{ $t('Apply') }}</button>
          <button
            v-if="minSize > 0"
            @click="minSize = 0; applyMinSize()"
            class="px-3 h-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
          >{{ $t('Clear') }}</button>
        </div>
      </div>

      <!-- Card: select-all header + grid + pagination (matches Scanner/WooCommerce) -->
      <div v-if="loading && images.length === 0" class="text-center py-12 text-gray-500">
        {{ $t('Loading...') }}
      </div>

      <div v-else class="bg-white rounded-lg shadow border" :class="{ 'opacity-60 pointer-events-none': loading }">
        <div v-if="isAdmin" class="p-4 border-b flex items-center justify-between gap-4 min-h-16">
          <div class="flex items-center flex-wrap gap-2">
            <Checkbox size="sm" :model-value="allSelected" @change="toggleSelectAll">
              <span>{{ $t('Select All') }}</span>
            </Checkbox>
          </div>

          <transition name="icais-btn-pop">
            <div v-if="selected.length > 0" class="flex items-center gap-2 shrink-0">
              <button
                @click="compressSelected"
                class="px-4 py-1.5 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-colors"
              >{{ filter === 'compressed' ? $t('Re-compress Selected') : $t('Compress Selected') }} ({{ selected.length }})</button>
              <button
                v-if="selectedRestorable > 0"
                :disabled="restoringBulk"
                @click="restoreSelected"
                class="px-4 py-1.5 text-sm font-medium rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 disabled:opacity-50"
              >{{ restoringBulk ? $t('Restoring...') : $t('Restore Selected') }}</button>
            </div>
          </transition>
        </div>

        <!-- Empty -->
        <div v-if="images.length === 0" class="text-center py-12 text-gray-500">
          {{ $t('No images found.') }}
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 p-4">
          <CompressionCard
            v-for="img in images"
            :key="img.id"
            :image="img"
            :selected="selected.includes(img.id)"
            :busy="busyId === img.id"
            @toggle="toggleSelect"
            @compress="compressOne($event)"
            @recompress="compressOne($event, true)"
            @restore="restoreOne($event)"
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
            :page-sizes="[12, 24, 48, 96]"
            :align="'right'"
            @per-page-change="perPage = $event; loadImages()"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'
import { getSettings } from '../router'
import { Pagination, Checkbox } from '../@ui'
import CompressionCard from '../components/Compression/CompressionCard.vue'

const router = useRouter()

const isPro = window.icais?.isPro ?? false
// Bulk compress uses batch/* (manage_options). Non-admins compress per-image.
const isAdmin = window.icais?.isAdmin ?? false
const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'

const enabled = ref(true)
const loading = ref(false)
const images = ref([])
const selected = ref([])
const busyId = ref(null)
const restoringBulk = ref(false)
const filter = ref('all')
const sort = ref('date') // 'date' (newest) | 'largest' (biggest files first)
const minSize = ref(0) // KB; 0 = no size filter
const perPage = ref(24)
const pagination = ref({ current_page: 1, total_pages: 1, total: 0 })
const stats = ref({ total_saved_human: '0 B', total_compressed: 0 })
const usage = ref({ is_pro: isPro, used: 0, limit: 50, remaining: 50 })

const filters = [
  { value: 'all', label: 'All' },
  { value: 'uncompressed', label: 'Uncompressed' },
  { value: 'compressed', label: 'Compressed' },
]

const allSelected = computed(() => images.value.length > 0 && selected.value.length === images.value.length)

// Count of currently-selected images that have a backup available to restore.
const selectedRestorable = computed(
  () => images.value.filter((i) => selected.value.includes(i.id) && i.can_restore).length
)

const loadImages = async (page = 1) => {
  loading.value = true
  try {
    const result = await Api.get(`/compressor/scan?filter=${filter.value}&sort=${sort.value}&min_size=${minSize.value || 0}&page=${page}&per_page=${perPage.value}`)
    if (result.success) {
      images.value = result.images || []
      pagination.value = result.pagination || pagination.value
      stats.value = result.stats || stats.value
      selected.value = []
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to load images'))
  } finally {
    loading.value = false
  }
}

const loadPage = (page) => loadImages(page)

const setFilter = (value) => {
  filter.value = value
  loadImages(1)
}

// Sort by file size (largest first) to surface the heaviest images to compress.
const toggleSort = () => {
  sort.value = sort.value === 'largest' ? 'date' : 'largest'
  loadImages(1)
}

const applyMinSize = () => {
  selected.value = []
  loadImages(1)
}

const toggleSelectAll = () => {
  selected.value = allSelected.value ? [] : images.value.map((i) => i.id)
}

const toggleSelect = (id) => {
  const i = selected.value.indexOf(id)
  if (i > -1) {
    selected.value.splice(i, 1)
  } else {
    selected.value.push(id)
  }
}

const refreshStats = async () => {
  try {
    const result = await Api.get('/compressor/stats')
    if (result.success) {
      stats.value = result.stats || stats.value
      usage.value = result.usage || usage.value
    }
  } catch {
    // non-fatal
  }
}

const compressOne = async (img, force = false) => {
  busyId.value = img.id
  try {
    const result = await Api.post('/compressor/compress', { attachment_id: img.id, force })
    if (result.success) {
      if (result.skipped) {
        notification.info($t('Skipped (%s)', result.reason || ''))
      } else {
        notification.success($t('Compressed — saved %s', result.saved_human || '0 B'))
      }
      if (result.image) {
        const idx = images.value.findIndex((i) => i.id === img.id)
        if (idx !== -1) images.value[idx] = result.image
      }
      if (result.usage) usage.value = result.usage
      refreshStats()
    } else {
      notification.error(result.message || $t('Compression failed'))
    }
  } catch (error) {
    if (error.status === 429) {
      notification.warning(error.message || $t('Daily compression limit reached.'))
    } else {
      notification.error(error.message || $t('Compression failed'))
    }
  } finally {
    busyId.value = null
  }
}

const restoreOne = async (img) => {
  busyId.value = img.id
  try {
    const result = await Api.post('/compressor/restore', { attachment_id: img.id })
    if (result.success) {
      notification.success($t('Original restored'))
      if (result.image) {
        const idx = images.value.findIndex((i) => i.id === img.id)
        if (idx !== -1) images.value[idx] = result.image
      }
      refreshStats()
    } else {
      notification.error(result.message || $t('Restore failed'))
    }
  } catch (error) {
    notification.error(error.message || $t('Restore failed'))
  } finally {
    busyId.value = null
  }
}

const restoreSelected = async () => {
  // Restore is per-image (no batch endpoint); run the restorable selection sequentially.
  const targets = images.value.filter((i) => selected.value.includes(i.id) && i.can_restore)
  if (targets.length === 0) return
  restoringBulk.value = true
  let restored = 0
  let failed = 0
  try {
    for (const img of targets) {
      try {
        const result = await Api.post('/compressor/restore', { attachment_id: img.id })
        if (result.success) {
          restored++
          if (result.image) {
            const idx = images.value.findIndex((i) => i.id === img.id)
            if (idx !== -1) images.value[idx] = result.image
          }
        } else {
          failed++
        }
      } catch {
        failed++
      }
    }
    if (restored > 0) notification.success($t('Restored %s images', restored))
    if (failed > 0) notification.error($t('%s images could not be restored', failed))
    refreshStats()
  } finally {
    restoringBulk.value = false
  }
}

const compressSelected = async () => {
  if (selected.value.length === 0) return
  if (selected.value.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }
  try {
    const result = await Api.post('/batch/start', {
      image_ids: selected.value,
      type: 'compress',
      // On the Compressed filter this is a "Re-compress Selected" action.
      force: filter.value === 'compressed',
    })
    if (result.success) {
      notification.success($t('Compression started for %s images', selected.value.length))
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

onMounted(async () => {
  try {
    const settings = await getSettings()
    enabled.value = settings.icais_compression_enabled === '1'
      || settings.settings?.icais_compression_enabled === '1'
  } catch {
    enabled.value = true
  }
  if (enabled.value) {
    await loadImages(1)
    await refreshStats()
  }
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
