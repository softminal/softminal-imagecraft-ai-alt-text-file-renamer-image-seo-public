<template>
  <div class="batch p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $t('Batch Processing') }}</h1>

    <div v-if="job" class="bg-white rounded-lg shadow border p-6">
      <!-- Progress -->
      <div class="mb-6">
        <div class="flex justify-between items-center mb-2">
          <span class="font-semibold">{{ $t('Progress') }}</span>
          <span class="text-sm text-gray-600">{{ job.progress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div
            class="bg-blue-600 h-4 rounded-full transition-all duration-300"
            :style="{ width: job.progress + '%' }"
          ></div>
        </div>
        <div class="text-sm text-gray-600 mt-2">
          {{ $t('%s of %s images processed', job.processed_images, job.total_images) }}
          <span v-if="job.failed_images > 0" class="text-red-600">
            ({{ $t('%s failed', job.failed_images) }})
          </span>
        </div>
      </div>

      <!-- Status -->
      <div class="mb-6">
        <div class="flex items-center gap-2">
          <span class="font-semibold">{{ $t('Status:') }}</span>
          <span :class="{
            'text-blue-600': job.status === 'processing',
            'text-green-600': job.status === 'completed',
            'text-red-600': job.status === 'failed',
            'text-gray-600': job.status === 'pending'
          }">
            {{ job.status }}
          </span>
        </div>
      </div>

      <!-- Show Review button when completed and we have images to review -->
      <div v-if="job.status === 'completed' && !showReviewModal" class="mb-6">
        <button
          @click="openReviewModal"
          class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"
        >
          {{ $t('Review & Edit Alt Text') }}
        </button>
      </div>

      <!-- Error Troubleshooting -->
      <div v-if="job.status === 'failed' && job.failed_images > 0" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <h3 class="font-semibold text-red-800 mb-2">{{ $t('Troubleshooting Failed Images') }}</h3>
        <p class="text-sm text-red-700 mb-3">
          {{ $t('All %s image(s) failed to process. Common causes:', job.failed_images) }}
        </p>
        <ul class="list-disc list-inside text-sm text-red-700 space-y-1 mb-3">
          <li><strong>{{ $t('No API key configured:') }}</strong> {{ $t('Go to Settings and add your API key') }}</li>
          <li><strong>{{ $t('Invalid API key:') }}</strong> {{ $t('Verify your API key is correct') }}</li>
          <li><strong>{{ $t('API key permissions:') }}</strong> {{ $t('Ensure your API key has access') }}</li>
          <li><strong>{{ $t('Image URL not accessible:') }}</strong> {{ $t('Images must be publicly accessible') }}</li>
          <li><strong>{{ $t('API rate limit exceeded:') }}</strong> {{ $t('Wait a few minutes and try again') }}</li>
          <li><strong>{{ $t('Network/connection error:') }}</strong> {{ $t('Check your internet connection and firewall settings') }}</li>
        </ul>
        <div class="mt-3 pt-3 border-t border-red-200">
          <p class="text-xs text-red-600 mb-2">
            <strong>{{ $t('To find the exact error:') }}</strong>
          </p>
          <ol class="list-decimal list-inside text-xs text-red-600 space-y-1">
            <li>{{ $t('Enable WordPress debug logging in wp-config.php') }}</li>
            <li>{{ $t('Check wp-content/debug.log for error entries') }}</li>
            <li>{{ $t('The log will show the specific error message for each failed image') }}</li>
          </ol>
        </div>
        <div class="mt-3">
          <button
            @click="$router.push('/settings')"
            class="text-sm bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
          >
            {{ $t('Go to Settings') }}
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3">
        <button
          v-if="job.status === 'processing'"
          @click="cancelJob"
          class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700"
        >
          {{ $t('Cancel') }}
        </button>
        <button
          @click="$router.push('/scanner')"
          class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300"
        >
          {{ $t('Back to Scanner') }}
        </button>
      </div>
    </div>

    <div v-else class="text-center py-12 text-gray-500">
      {{ $t('No active batch job') }}
    </div>

    <!-- Review Alt Text Modal -->
    <Teleport to="body">
      <div
        v-if="showReviewModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="closeReviewModal"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
          <div class="p-4 border-b flex items-center justify-between shrink-0">
            <h2 class="text-xl font-bold">{{ $t('Review & Edit Alt Text') }}</h2>
            <button
              @click="closeReviewModal"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              :aria-label="$t('Close')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-4 overflow-y-auto flex-1">
            <div v-if="loadingResults" class="text-center py-8 text-gray-500">
              {{ $t('Loading images...') }}
            </div>
            <div v-else-if="reviewImages.length === 0" class="text-center py-8 text-gray-500">
              {{ $t('No generated images to review.') }}
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="item in reviewImages"
                :key="item.id"
                class="border rounded-lg p-3 flex flex-col gap-2"
              >
                <div class="flex gap-3">
                  <img
                    :src="item.thumbnail || item.url"
                    :alt="item.alt_text"
                    class="w-20 h-20 object-cover rounded shrink-0"
                  />
                  <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-500 truncate mb-1" :title="item.filename">{{ item.filename }}</p>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('Alt text') }}</label>
                    <textarea
                      v-model="item.alt_text"
                      rows="3"
                      class="w-full text-sm border rounded-lg p-2 resize-y focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      :placeholder="$t('Enter alt text...')"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="p-4 border-t flex justify-end gap-2 shrink-0">
            <button
              @click="closeReviewModal"
              class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
              {{ $t('Cancel') }}
            </button>
            <button
              @click="saveAllAltTexts"
              :disabled="savingAltTexts || reviewImages.length === 0"
              class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingAltTexts ? $t('Saving...') : $t('Save All') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'

const route = useRoute()
const router = useRouter()
const job = ref(null)
const jobId = ref(route.params.jobId)
const prevJobStatus = ref(null)
const showReviewModal = ref(false)
const reviewImages = ref([])
const loadingResults = ref(false)
const savingAltTexts = ref(false)
const reviewModalShownForJob = ref(false)
let intervalId = null
let processingChunk = false

const loadJobStatus = async () => {
  if (!jobId.value) return

  try {
    const result = await Api.get(`/batch/status?job_id=${jobId.value}`)
    if (result.success) {
      const wasProcessing = prevJobStatus.value === 'processing' || prevJobStatus.value === 'pending'
      prevJobStatus.value = result.job.status
      job.value = result.job

      // If processing, trigger process chunk (only if not already processing)
      if ((job.value.status === 'processing' || job.value.status === 'pending') && !processingChunk) {
        await processChunk()
      }

      // If completed or failed, stop polling
      if (job.value.status === 'completed' || job.value.status === 'failed') {
        stopPolling()
        if (job.value.status === 'completed') {
          // Auto-open review modal when generation just completed
          if (wasProcessing && !reviewModalShownForJob.value) {
            reviewModalShownForJob.value = true
            await openReviewModal()
          }
          // Show appropriate message based on results
          if (job.value.failed_images > 0) {
            notification.warning($t('Batch processing completed with %s successful and %s failed.', job.value.processed_images, job.value.failed_images))
          } else {
            notification.success($t('Batch processing completed successfully!'))
          }
        } else if (job.value.status === 'failed') {
          // Check if it was cancelled or all images failed
          if (job.value.processed_images === 0 && job.value.failed_images === job.value.total_images) {
            notification.error($t('Batch processing failed. All %s images failed to process.', job.value.total_images))
          } else {
            notification.error($t('Batch job was cancelled.'))
          }
        }
      }
    }
  } catch (error) {
    console.error('Failed to load job status:', error)
  }
}

const openReviewModal = async () => {
  showReviewModal.value = true
  reviewImages.value = []
  loadingResults.value = true
  try {
    const result = await Api.get(`/batch/results?job_id=${jobId.value}`)
    if (result.success && result.images && result.images.length > 0) {
      reviewImages.value = result.images.map(img => ({ ...img }))
    }
  } catch (error) {
    notification.error($t('Failed to load generated images'))
  } finally {
    loadingResults.value = false
  }
}

const closeReviewModal = () => {
  showReviewModal.value = false
}

const saveAllAltTexts = async () => {
  if (reviewImages.value.length === 0) return
  savingAltTexts.value = true
  try {
    const items = reviewImages.value.map(img => ({
      history_id: img.history_id,
      alt_text: img.alt_text || ''
    }))
    const result = await Api.post('/generator/accept-bulk', { items })
    if (result.success) {
      notification.success(result.message || $t('Alt texts saved successfully.'))
      closeReviewModal()
    } else {
      notification.error(result.message || $t('Failed to save alt texts'))
    }
  } catch (error) {
    notification.error(error.message || $t('Failed to save alt texts'))
  } finally {
    savingAltTexts.value = false
  }
}

const processChunk = async () => {
  if (!jobId.value || processingChunk) return
  processingChunk = true

  try {
    await Api.post('/batch/process', {
      job_id: jobId.value
    })
  } catch (error) {
    console.error('Failed to process chunk:', error)
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
    router.push('/scanner')
  } catch (error) {
    notification.error($t('Failed to cancel job'))
  }
}

const startPolling = () => {
  intervalId = setInterval(loadJobStatus, 2000)
}

const stopPolling = () => {
  if (intervalId) {
    clearInterval(intervalId)
    intervalId = null
  }
}

onMounted(() => {
  loadJobStatus()
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>
