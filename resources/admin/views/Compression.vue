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
        </div>
        <div v-if="isAdmin" class="flex gap-2">
          <button
            @click="toggleSelectAll"
            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 hover:bg-gray-50"
          >{{ allSelected ? $t('Clear') : $t('Select page') }}</button>
          <button
            :disabled="selected.length === 0"
            @click="compressSelected"
            class="px-4 py-1.5 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >{{ filter === 'compressed' ? $t('Re-compress Selected') : $t('Compress Selected') }}<template v-if="selected.length"> ({{ selected.length }})</template></button>
          <button
            v-if="selectedRestorable > 0"
            :disabled="restoringBulk"
            @click="restoreSelected"
            class="px-4 py-1.5 text-sm font-medium rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 disabled:opacity-50"
          >{{ restoringBulk ? $t('Restoring...') : $t('Restore Selected') }}</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-12 text-gray-500">{{ $t('Loading...') }}</div>

      <!-- Empty -->
      <div v-else-if="images.length === 0" class="text-center py-12 text-gray-500">
        {{ $t('No images found.') }}
      </div>

      <!-- Grid -->
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="img in images"
          :key="img.id"
          class="bg-white rounded-lg shadow border overflow-hidden flex flex-col"
        >
          <div class="relative">
            <img
              :src="img.thumbnail || img.url"
              :alt="img.filename"
              class="w-full h-32 object-cover bg-gray-100"
            />
            <Checkbox :value="img.id" v-model="selected" class="absolute top-2 left-2" />
            <span
              v-if="img.compressed"
              class="absolute top-2 right-2 bg-green-600 text-white text-xs px-2 py-0.5 rounded-full"
            >{{ $t('Compressed') }}</span>
          </div>
          <div class="p-3 flex-1 flex flex-col">
            <div class="text-xs text-gray-600 truncate" :title="img.filename">{{ img.filename }}</div>
            <div class="text-sm font-medium text-gray-800">{{ img.size_human }}</div>
            <div v-if="img.compressed && img.saved_percent > 0" class="text-xs text-green-600 mt-1">
              {{ $t('Saved %s (%s%)', img.saved_human, img.saved_percent) }}
            </div>
            <div class="mt-auto pt-2 flex gap-2">
              <button
                v-if="!img.compressed"
                :disabled="busyId === img.id"
                @click="compressOne(img)"
                class="flex-1 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 disabled:opacity-50"
              >{{ busyId === img.id ? $t('Compressing...') : $t('Compress') }}</button>
              <template v-else>
                <button
                  :disabled="busyId === img.id"
                  @click="compressOne(img, true)"
                  class="flex-1 text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200 disabled:opacity-50"
                >{{ $t('Re-compress') }}</button>
                <button
                  v-if="img.can_restore"
                  :disabled="busyId === img.id"
                  @click="restoreOne(img)"
                  class="flex-1 text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded hover:bg-amber-200 disabled:opacity-50"
                >{{ $t('Restore') }}</button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-6">
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
    const result = await Api.get(`/compressor/scan?filter=${filter.value}&page=${page}&per_page=${perPage.value}`)
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

const toggleSelectAll = () => {
  selected.value = allSelected.value ? [] : images.value.map((i) => i.id)
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
