<template>
  <div class="batch p-6">
    <h1 class="text-2xl font-bold mb-6">Batch Processing</h1>
    
    <div v-if="job" class="bg-white rounded-lg shadow border p-6">
      <!-- Progress -->
      <div class="mb-6">
        <div class="flex justify-between items-center mb-2">
          <span class="font-semibold">Progress</span>
          <span class="text-sm text-gray-600">{{ job.progress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div 
            class="bg-blue-600 h-4 rounded-full transition-all duration-300"
            :style="{ width: job.progress + '%' }"
          ></div>
        </div>
        <div class="text-sm text-gray-600 mt-2">
          {{ job.processed_images }} of {{ job.total_images }} images processed
          <span v-if="job.failed_images > 0" class="text-red-600">
            ({{ job.failed_images }} failed)
          </span>
        </div>
      </div>
      
      <!-- Status -->
      <div class="mb-6">
        <div class="flex items-center gap-2">
          <span class="font-semibold">Status:</span>
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
          Review & Edit Alt Text
        </button>
      </div>
      
      <!-- Error Troubleshooting -->
      <div v-if="job.status === 'failed' && job.failed_images > 0" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <h3 class="font-semibold text-red-800 mb-2">⚠️ Troubleshooting Failed Images</h3>
        <p class="text-sm text-red-700 mb-3">
          All {{ job.failed_images }} image(s) failed to process. Common causes:
        </p>
        <ul class="list-disc list-inside text-sm text-red-700 space-y-1 mb-3">
          <li><strong>No API key configured:</strong> Go to Settings → API Configuration and add your API key</li>
          <li><strong>Invalid API key:</strong> Verify your API key is correct. For Gemini, get your key from <a href="https://aistudio.google.com/apikey" target="_blank" class="underline">Google AI Studio</a></li>
          <li><strong>API key permissions:</strong> Ensure your API key has access to Gemini API</li>
          <li><strong>Image URL not accessible:</strong> Images must be publicly accessible (not behind authentication)</li>
          <li><strong>API rate limit exceeded:</strong> Wait a few minutes and try again</li>
          <li><strong>Network/connection error:</strong> Check your internet connection and firewall settings</li>
        </ul>
        <div class="mt-3 pt-3 border-t border-red-200">
          <p class="text-xs text-red-600 mb-2">
            <strong>To find the exact error:</strong>
          </p>
          <ol class="list-decimal list-inside text-xs text-red-600 space-y-1">
            <li>Enable WordPress debug logging in <code>wp-config.php</code>: <code>define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);</code></li>
            <li>Check <code>wp-content/debug.log</code> for entries starting with "WP AI Alt Text: Batch processing failed"</li>
            <li>The log will show the specific error message for each failed image</li>
          </ol>
        </div>
        <div class="mt-3">
          <button 
            @click="$router.push('/settings')"
            class="text-sm bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
          >
            Go to Settings
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
          Cancel
        </button>
        <button 
          @click="$router.push('/scanner')"
          class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300"
        >
          Back to Scanner
        </button>
      </div>
    </div>
    
    <div v-else class="text-center py-12 text-gray-500">
      No active batch job
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
            <h2 class="text-xl font-bold">Review & Edit Alt Text</h2>
            <button 
              @click="closeReviewModal"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              aria-label="Close"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-4 overflow-y-auto flex-1">
            <div v-if="loadingResults" class="text-center py-8 text-gray-500">
              Loading images...
            </div>
            <div v-else-if="reviewImages.length === 0" class="text-center py-8 text-gray-500">
              No generated images to review.
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alt text</label>
                    <textarea 
                      v-model="item.alt_text"
                      rows="3"
                      class="w-full text-sm border rounded-lg p-2 resize-y focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      placeholder="Enter alt text..."
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
              Cancel
            </button>
            <button 
              @click="saveAllAltTexts"
              :disabled="savingAltTexts || reviewImages.length === 0"
              class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingAltTexts ? 'Saving...' : 'Save All' }}
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

const loadJobStatus = async () => {
  if (!jobId.value) return
  
  try {
    const result = await Api.get(`/batch/status?job_id=${jobId.value}`)
    if (result.success) {
      const wasProcessing = prevJobStatus.value === 'processing' || prevJobStatus.value === 'pending'
      prevJobStatus.value = result.job.status
      job.value = result.job
      
      // If processing, trigger process chunk
      if (job.value.status === 'processing' || job.value.status === 'pending') {
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
            notification.warning(`Batch processing completed with ${job.value.processed_images} successful and ${job.value.failed_images} failed.`)
          } else {
            notification.success('Batch processing completed successfully!')
          }
        } else if (job.value.status === 'failed') {
          // Check if it was cancelled or all images failed
          if (job.value.processed_images === 0 && job.value.failed_images === job.value.total_images) {
            notification.error(`Batch processing failed. All ${job.value.total_images} images failed to process.`)
          } else {
            notification.error('Batch job was cancelled.')
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
    notification.error('Failed to load generated images')
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
      notification.success(result.message || 'Alt texts saved successfully.')
      closeReviewModal()
    } else {
      notification.error(result.message || 'Failed to save alt texts')
    }
  } catch (error) {
    notification.error(error.message || 'Failed to save alt texts')
  } finally {
    savingAltTexts.value = false
  }
}

const processChunk = async () => {
  if (!jobId.value) return
  
  try {
    await Api.post('/batch/process', {
      job_id: jobId.value
    })
    // Reload status after processing
    setTimeout(loadJobStatus, 1000)
  } catch (error) {
    console.error('Failed to process chunk:', error)
  }
}

const cancelJob = async () => {
  if (!jobId.value) return
  
  try {
    await Api.post('/batch/cancel', {
      job_id: jobId.value
    })
    notification.success('Batch job cancelled')
    router.push('/scanner')
  } catch (error) {
    notification.error('Failed to cancel job')
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
