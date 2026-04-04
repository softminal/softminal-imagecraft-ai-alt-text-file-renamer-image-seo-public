<template>
  <div class="batch p-6">
    <h1 class="text-2xl font-bold mb-6">
      {{ $t('Batch Processing') }}
      <span v-if="batchTypeLabel" class="text-base font-medium text-gray-500">— {{ batchTypeLabel }}</span>
    </h1>

    <!-- Resume Banner -->
    <div v-if="!jobId && interruptedJob" class="bg-amber-50 border border-amber-300 rounded-lg shadow p-6 mb-6">
      <div class="flex items-start gap-4">
        <div class="shrink-0 mt-0.5">
          <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-amber-800 mb-1">{{ $t('Interrupted Batch Job Found') }}</h2>
          <p class="text-sm text-amber-700 mb-3">
            {{ $t('A previous batch job was interrupted before completing.') }}
          </p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-lg p-3 border border-amber-200">
              <p class="text-xs text-gray-500">{{ $t('Total') }}</p>
              <p class="text-lg font-bold text-gray-800">{{ interruptedJob.total_images }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-amber-200">
              <p class="text-xs text-gray-500">{{ $t('Processed') }}</p>
              <p class="text-lg font-bold text-green-600">{{ interruptedJob.processed_images }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-amber-200">
              <p class="text-xs text-gray-500">{{ $t('Failed') }}</p>
              <p class="text-lg font-bold text-red-600">{{ interruptedJob.failed_images }}</p>
            </div>
            <div class="bg-white rounded-lg p-3 border border-amber-200">
              <p class="text-xs text-gray-500">{{ $t('Remaining') }}</p>
              <p class="text-lg font-bold text-blue-600">{{ interruptedJob.remaining }}</p>
            </div>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
            <div
              class="bg-amber-500 h-2 rounded-full transition-all"
              :style="{ width: interruptedJob.progress + '%' }"
            ></div>
          </div>
          <div class="flex gap-3">
            <button
              @click="resumeJob"
              :disabled="resuming"
              class="bg-amber-600 text-white px-6 py-2 rounded-lg hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ resuming ? $t('Resuming...') : $t('Resume') }}
            </button>
            <button
              @click="discardJob"
              :disabled="resuming"
              class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 disabled:opacity-50"
            >
              {{ $t('Discard') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Active job -->
    <div v-if="job" class="bg-white rounded-lg shadow border p-6">
      <!-- Progress bar -->
      <div class="mb-6">
        <div class="flex justify-between items-center mb-2">
          <div class="flex items-center gap-2">
            <span class="font-semibold">{{ $t('Progress') }}</span>
            <svg v-if="isProcessing" class="w-4 h-4 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </div>
          <span class="text-sm font-medium text-gray-700">{{ progressPercent }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
          <div
            class="h-4 rounded-full transition-all duration-500 ease-out"
            :class="[progressBarColor, { 'animate-pulse': isProcessing }]"
            :style="{ width: Math.max(progressPercent, isProcessing ? 2 : 0) + '%' }"
          ></div>
        </div>
        <div class="flex justify-between items-center mt-1">
          <p v-if="isProcessing" class="text-xs text-blue-600 animate-pulse">
            {{ $t('Processing images... please wait') }}
          </p>
          <p v-else class="text-xs"></p>
          <p class="text-sm font-medium text-gray-600">
            {{ completedCount }}/{{ totalImages }}
          </p>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-gray-50 rounded-lg p-3 border">
          <p class="text-xs text-gray-500">{{ $t('Total') }}</p>
          <p class="text-xl font-bold text-gray-800">{{ totalImages }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-3 border border-green-200">
          <p class="text-xs text-gray-500">{{ $t('Completed') }}</p>
          <p class="text-xl font-bold text-green-600">{{ processedImages }}</p>
        </div>
        <div class="bg-red-50 rounded-lg p-3 border border-red-200">
          <p class="text-xs text-gray-500">{{ $t('Failed') }}</p>
          <p class="text-xl font-bold text-red-600">{{ failedImages_count }}</p>
        </div>
        <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
          <p class="text-xs text-gray-500">{{ $t('Remaining') }}</p>
          <p class="text-xl font-bold text-blue-600">{{ remainingImages }}</p>
        </div>
      </div>

      <!-- Status -->
      <div class="mb-6 flex items-center gap-2">
        <span class="font-semibold">{{ $t('Status:') }}</span>
        <span
          class="px-2.5 py-0.5 rounded-full text-sm font-medium"
          :class="statusBadgeClass"
        >
          {{ statusLabel }}
        </span>
      </div>

      <!-- Completion summary -->
      <div v-if="job.status === 'completed'" class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center gap-2 mb-2">
          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h3 class="font-semibold text-green-800">{{ $t('Batch Complete') }}</h3>
        </div>
        <p class="text-sm text-green-700">
          {{ $t('%1$s images processed successfully', job.processed_images) }}
          <span v-if="job.failed_images > 0" class="text-red-600">
            {{ $t(', %s failed', job.failed_images) }}
          </span>
        </p>
      </div>

      <!-- Failed images list -->
      <div v-if="(job.status === 'completed' || job.status === 'failed') && parseInt(job.failed_images) > 0" class="mb-6">
        <div class="flex items-center gap-4">
        <button
          @click="toggleFailedImages"
          class="flex items-center gap-2 text-sm font-medium text-red-700 hover:text-red-800"
        >
          <svg
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-90': showFailedImages }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          {{ showFailedImages ? $t('Hide Failed Images') : $t('View Failed Images (%s)', job.failed_images) }}
        </button>
        <button
          @click="retryAllFailed"
          :disabled="retryingFailed"
          class="text-sm font-medium text-white bg-red-600 hover:bg-red-700 px-3 py-1 rounded disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ retryingFailed ? $t('Starting...') : $t('Retry All Failed') }}
        </button>
        </div>

        <div v-if="showFailedImages" class="mt-3">
          <div v-if="loadingFailures" class="text-sm text-gray-500 py-4 text-center">
            {{ $t('Loading...') }}
          </div>
          <div v-else-if="failedImages.length === 0 && !loadingFailures" class="text-sm text-gray-500 py-4 text-center bg-gray-50 rounded-lg border">
            {{ $t('Failure details are no longer available.') }}
          </div>
          <div v-else>
            <div class="border rounded-lg overflow-hidden">
              <div
                v-for="img in failedImages"
                :key="img.id"
                class="flex items-center gap-3 p-3 border-b last:border-b-0 bg-white hover:bg-gray-50"
              >
                <img
                  v-if="img.thumbnail"
                  :src="img.thumbnail"
                  :alt="img.filename"
                  class="w-10 h-10 rounded object-cover shrink-0"
                />
                <div v-else class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-800 truncate">{{ img.filename }}</p>
                  <p class="text-xs text-red-600 truncate">{{ img.reason }}</p>
                </div>
              </div>
            </div>
            <div v-if="failuresPagination.total_pages > 1" class="mt-3">
              <Pagination
                :current-page="failuresPagination.current_page"
                :total-pages="failuresPagination.total_pages"
                :total="failuresPagination.total"
                :per-page="failuresPagination.per_page"
                @page-change="loadFailures"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Error troubleshooting -->
      <div v-if="job.status === 'failed' && job.failed_images > 0" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <h3 class="font-semibold text-red-800 mb-2">{{ $t('Troubleshooting') }}</h3>
        <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
          <li>{{ $t('Check your API key is valid in Settings') }}</li>
          <li>{{ $t('API rate limit may have been exceeded — wait and retry') }}</li>
          <li>{{ $t('Images must be accessible (not behind authentication)') }}</li>
        </ul>
        <button
          @click="$router.push('/settings/api')"
          class="mt-3 text-sm bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
        >
          {{ $t('Go to Settings') }}
        </button>
      </div>

      <!-- Actions -->
      <div class="flex gap-3">
        <button
          v-if="job.status === 'processing' || job.status === 'pending'"
          @click="cancelJob"
          class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700"
        >
          {{ $t('Cancel') }}
        </button>
        <button
          @click="$router.back()"
          class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300"
        >
          {{ $t('Back to Scanner') }}
        </button>
      </div>
    </div>

    <div v-else-if="!interruptedJob && !checkingActive && !resuming" class="text-center py-12 text-gray-500">
      {{ $t('No active batch job') }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'
import { Pagination } from '../@ui'

const route = useRoute()
const router = useRouter()
const job = ref(null)
const jobId = ref(route.params.jobId)
const prevJobStatus = ref(null)
let pollTimeoutId = null
let pollCount = 0
let currentPollInterval = 2000
const MIN_POLL_INTERVAL = 2000
const MAX_POLL_INTERVAL = 10000
const RAMP_AFTER_POLLS = 10
let processingChunk = false

const clientId = Math.random().toString(36).substring(2) + Date.now().toString(36)
const isProcessor = ref(true)
const interruptedJob = ref(null)
const checkingActive = ref(false)
const resuming = ref(false)

const showFailedImages = ref(false)
const failedImages = ref([])
const loadingFailures = ref(false)
const failuresPagination = ref({ current_page: 1, total_pages: 1, total: 0, per_page: 20 })
const retryingFailed = ref(false)

const isProcessing = computed(() => {
  return job.value && (job.value.status === 'processing' || job.value.status === 'pending')
})

const batchTypeLabel = computed(() => {
  const type = job.value?.batch_type || interruptedJob.value?.batch_type
  if (type === 'title') return $t('Title Generation')
  if (type === 'alt') return $t('Alt Text Generation')
  return ''
})

// Track highest seen values to prevent progress bar and stats flickering
const peakProcessed = ref(0)
const peakFailed = ref(0)

const processedImages = computed(() => {
  if (!job.value) return peakProcessed.value
  const current = parseInt(job.value.processed_images) || 0
  if (current > peakProcessed.value) {
    peakProcessed.value = current
  }
  return peakProcessed.value
})

const failedImages_count = computed(() => {
  if (!job.value) return peakFailed.value
  const current = parseInt(job.value.failed_images) || 0
  if (current > peakFailed.value) {
    peakFailed.value = current
  }
  return peakFailed.value
})

const completedCount = computed(() => {
  return processedImages.value + failedImages_count.value
})

const totalImages = computed(() => {
  if (!job.value) return 0
  return parseInt(job.value.total_images) || 0
})

const remainingImages = computed(() => {
  return Math.max(0, totalImages.value - completedCount.value)
})

const progressPercent = computed(() => {
  if (totalImages.value === 0) return 0
  return Math.round((completedCount.value / totalImages.value) * 100)
})

const progressBarColor = computed(() => {
  if (!job.value) return 'bg-blue-600'
  if (job.value.status === 'completed') return 'bg-green-500'
  if (job.value.status === 'failed') return 'bg-red-500'
  return 'bg-blue-600'
})

const statusBadgeClass = computed(() => {
  if (!job.value) return ''
  const map = {
    pending: 'bg-gray-100 text-gray-700',
    processing: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
  }
  return map[job.value.status] || 'bg-gray-100 text-gray-700'
})

const statusLabel = computed(() => {
  if (!job.value) return ''
  const map = {
    pending: $t('Pending'),
    processing: $t('Processing'),
    completed: $t('Completed'),
    failed: $t('Failed'),
  }
  return map[job.value.status] || job.value.status
})

const checkForInterruptedJob = async () => {
  checkingActive.value = true
  try {
    const result = await Api.get('/batch/active')
    if (result.success && result.has_active && !result.is_running) {
      interruptedJob.value = result.job
    } else if (result.success && result.has_active && result.is_running) {
      isProcessor.value = false
      jobId.value = result.job.id
      await loadJobStatus()
      startPolling()
    }
  } catch (error) {
    // Silently fail
  } finally {
    checkingActive.value = false
  }
}

const resumeJob = async () => {
  if (!interruptedJob.value) return
  resuming.value = true

  try {
    const result = await Api.post('/batch/resume', {
      job_id: interruptedJob.value.id
    })

    if (result.success) {
      notification.success($t('Resuming batch job...'))
      jobId.value = result.job_id
      interruptedJob.value = null
      await loadJobStatus()
      startPolling()
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to resume batch job'))
  } finally {
    resuming.value = false
  }
}

const discardJob = async () => {
  if (!interruptedJob.value) return

  try {
    await Api.post('/batch/cancel', {
      job_id: interruptedJob.value.id,
      discard: true
    })
    notification.success($t('Batch job discarded'))
    interruptedJob.value = null
  } catch (error) {
    notification.error(error.message || $t('Failed to discard job'))
  }
}

const loadJobStatus = async () => {
  if (!jobId.value) return

  try {
    const clientParam = isProcessor.value ? `&client_id=${clientId}` : ''
    const result = await Api.get(`/batch/status?job_id=${jobId.value}${clientParam}`)
    if (result.success) {
      if (prevJobStatus.value !== null && prevJobStatus.value !== result.job.status) {
        resetPollInterval()
      }
      prevJobStatus.value = result.job.status
      job.value = result.job

      if (isProcessor.value && (job.value.status === 'processing' || job.value.status === 'pending') && !processingChunk) {
        await processChunk()
      }

      if (job.value.status === 'completed' || job.value.status === 'failed') {
        stopPolling()
        if (job.value.status === 'completed') {
          if (job.value.failed_images > 0) {
            notification.warning($t('%1$s completed, %2$s failed', job.value.processed_images, job.value.failed_images))
          } else {
            notification.success($t('All %s images processed successfully!', job.value.processed_images))
          }
        }
      }
    }
  } catch (error) {
    console.error('Failed to load job status:', error)
  }
}

const processChunk = async () => {
  if (!jobId.value || processingChunk || !isProcessor.value) return
  processingChunk = true

  try {
    const result = await Api.post('/batch/process', {
      job_id: jobId.value,
      client_id: clientId
    })
    // Update job from process response immediately (no wait for next poll)
    if (result.success && result.job) {
      job.value = result.job
      prevJobStatus.value = result.job.status
    }
  } catch (error) {
    if (error.status === 409) {
      isProcessor.value = false
    }
  } finally {
    processingChunk = false
  }
}

const cancelJob = async () => {
  if (!jobId.value) return

  try {
    await Api.post('/batch/cancel', {
      job_id: jobId.value
    })
    notification.success($t('Batch job cancelled'))
    router.back()
  } catch (error) {
    notification.error($t('Failed to cancel job'))
  }
}

const scheduleNextPoll = () => {
  pollCount++
  if (pollCount > RAMP_AFTER_POLLS) {
    currentPollInterval = Math.min(currentPollInterval + 1000, MAX_POLL_INTERVAL)
  }
  pollTimeoutId = setTimeout(async () => {
    await loadJobStatus()
    if (pollTimeoutId !== null) {
      scheduleNextPoll()
    }
  }, currentPollInterval)
}

const resetPollInterval = () => {
  pollCount = 0
  currentPollInterval = MIN_POLL_INTERVAL
}

const startPolling = () => {
  resetPollInterval()
  scheduleNextPoll()
}

const stopPolling = () => {
  if (pollTimeoutId !== null) {
    clearTimeout(pollTimeoutId)
    pollTimeoutId = null
  }
}

const toggleFailedImages = () => {
  showFailedImages.value = !showFailedImages.value
  if (showFailedImages.value && failedImages.value.length === 0) {
    loadFailures(1)
  }
}

const loadFailures = async (page) => {
  if (!jobId.value) return
  loadingFailures.value = true
  try {
    const result = await Api.get(`/batch/failures?job_id=${jobId.value}&page=${page}&per_page=20`)
    if (result.success) {
      failedImages.value = result.images
      failuresPagination.value = result.pagination
    }
  } catch (error) {
    console.error('Failed to load failures:', error)
  } finally {
    loadingFailures.value = false
  }
}

const retryAllFailed = async () => {
  if (!jobId.value || retryingFailed.value) return
  retryingFailed.value = true
  try {
    const result = await Api.post('/batch/retry-failed', {
      job_id: jobId.value
    })
    if (result.success) {
      notification.success($t('Retrying %s failed images...', result.total_images))
      // Reset state and navigate to new batch job
      job.value = null
      showFailedImages.value = false
      failedImages.value = []
      peakProcessed.value = 0
      peakFailed.value = 0
      jobId.value = result.job_id
      await loadJobStatus()
      startPolling()
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to retry'))
  } finally {
    retryingFailed.value = false
  }
}

onMounted(async () => {
  if (jobId.value) {
    await loadJobStatus()
    startPolling()
  } else {
    await checkForInterruptedJob()
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>
