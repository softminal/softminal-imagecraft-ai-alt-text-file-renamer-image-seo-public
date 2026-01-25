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
let intervalId = null

const loadJobStatus = async () => {
  if (!jobId.value) return
  
  try {
    const result = await Api.get(`/batch/status?job_id=${jobId.value}`)
    if (result.success) {
      job.value = result.job
      
      // If processing, trigger process chunk
      if (job.value.status === 'processing' || job.value.status === 'pending') {
        await processChunk()
      }
      
      // If completed or failed, stop polling
      if (job.value.status === 'completed' || job.value.status === 'failed') {
        stopPolling()
        if (job.value.status === 'completed') {
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
