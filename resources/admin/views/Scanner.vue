<template>
  <div class="scanner p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold mb-4">Media Library Scanner</h1>
      
      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">Total Images</div>
          <div class="text-2xl font-bold">{{ scanStats.total_images || 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">Missing Alt Text</div>
          <div class="text-2xl font-bold text-orange-600">{{ scanStats.missing_alt || 0 }}</div>
          <div class="text-xs text-gray-500">{{ scanStats.missing_percentage || 0 }}%</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
          <div class="text-sm text-gray-600">Has Alt Text</div>
          <div class="text-2xl font-bold text-green-600">{{ scanStats.has_alt || 0 }}</div>
        </div>
      </div>
      
      <!-- Filters and Actions -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex gap-2">
          <button 
            @click="filter = 'all'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            All Images
          </button>
          <button 
            @click="filter = 'missing'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'missing' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            Missing Alt Text
          </button>
          <button 
            @click="filter = 'has_alt'; loadImages()"
            :class="['px-4 py-2 rounded-lg', filter === 'has_alt' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          >
            Has Alt Text
          </button>
        </div>
        
        <div class="flex gap-2">
          <button 
            @click="loadImages()"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"
          >
            Refresh
          </button>
          <button 
            v-if="selectedImages.length > 0"
            @click="startBatch"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            Generate for {{ selectedImages.length }} Selected
          </button>
        </div>
      </div>
    </div>
    
    <!-- Images Grid -->
    <div v-if="loading" class="text-center py-12 text-gray-500">
      Loading images...
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
          <span>Select All</span>
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
            <img 
              :src="image.thumbnail || image.url" 
              :alt="image.filename"
              class="w-full h-24 object-cover rounded"
            />
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
            {{ image.has_alt ? '✓ Has alt' : '✗ Missing' }}
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
          Previous
        </button>
        <span class="text-sm text-gray-600">
          Page {{ pagination.current_page }} of {{ pagination.total_pages }}
        </span>
        <button 
          @click="loadPage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.total_pages"
          class="px-4 py-2 bg-gray-200 rounded-lg disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'

const router = useRouter()
const images = ref([])
const scanStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const selectedImages = ref([])

const allSelected = computed(() => {
  return images.value.length > 0 && selectedImages.value.length === images.value.length
})

const loadImages = async (page = 1) => {
  loading.value = true
  try {
    const result = await Api.get(`/scanner/scan?filter=${filter.value}&page=${page}&per_page=48`)
    if (result.success) {
      images.value = result.images
      scanStats.value = result.stats
      pagination.value = result.pagination
    }
  } catch (error) {
    notification.error('Failed to load images')
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

const startBatch = async () => {
  if (selectedImages.value.length === 0) return
  
  try {
    const result = await Api.post('/batch/start', {
      image_ids: selectedImages.value
    })
    
    if (result.success) {
      notification.success(`Batch job started for ${selectedImages.value.length} images`)
      router.push(`/batch/${result.job_id}`)
    }
  } catch (error) {
    notification.error('Failed to start batch job')
  }
}

onMounted(() => {
  loadImages()
})
</script>
