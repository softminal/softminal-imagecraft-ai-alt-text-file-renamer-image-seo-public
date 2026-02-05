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
          <button
            v-if="selectedImages.length > 0"
            @click="renameSelectedImages"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
          >
            Rename {{ selectedImages.length }} Selected
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
            {{ image.has_alt ? '✓ Has alt' : '✗ Missing' }}
          </div>
          <div
            v-if="image.alt_text"
            class="text-xs mt-1 text-gray-600 line-clamp-2 break-words"
            :title="image.alt_text"
          >
            {{ image.alt_text }}
          </div>
          <div v-else class="text-xs mt-1 text-gray-400 italic">
            No alt text
          </div>
          <div v-if="image.has_alt && renamingId !== image.id" class="mt-2">
            <button
              @click.stop="renameFile(image.id)"
              class="text-xs flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
              Rename
            </button>
          </div>
          <div v-if="renamingId === image.id" class="mt-2">
            <span class="text-xs text-gray-500 flex items-center gap-1">
              <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Renaming...
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

    <!-- Single image modal (WP Media–style view) -->
    <Teleport to="body">
      <div 
        v-if="selectedImage" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="closeImageModal"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
          <div class="p-4 border-b flex items-center justify-between shrink-0">
            <h2 class="text-lg font-bold">Attachment details</h2>
            <button 
              @click="closeImageModal"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              aria-label="Close"
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
                  <label class="block text-sm font-medium text-gray-700 mb-1">File name</label>
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
                    Rename to SEO
                  </button>
                </div>
                <div>
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <label class="block text-sm font-medium text-gray-700">Alt text</label>
                    <button 
                      type="button"
                      @click="generateAltForImage"
                      :disabled="generatingAlt"
                      class="text-sm px-3 py-1.5 rounded-lg text-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ generatingAlt ? 'Generating...' : 'Generate with AI' }}
                    </button>
                  </div>
                  <textarea 
                    v-model="modalAltText"
                    rows="3"
                    class="w-full text-sm border rounded-lg p-2 resize-y focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Describe the image for accessibility..."
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    Alternative text helps describe the image for screen readers and SEO.
                  </p>
                </div>
                <a 
                  v-if="editAttachmentUrl"
                  :href="editAttachmentUrl"
                  target="_blank"
                  rel="noopener"
                  class="text-sm text-blue-600 hover:underline"
                >
                  Open in WordPress Media Library →
                </a>
              </div>
            </div>
          </div>
          <div class="p-4 border-t flex justify-end gap-2 shrink-0">
            <button 
              @click="closeImageModal"
              class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
              Cancel
            </button>
            <button 
              @click="saveImageAlt"
              :disabled="savingImageAlt"
              class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingImageAlt ? 'Saving...' : 'Update' }}
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

const router = useRouter()
const images = ref([])
const scanStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const selectedImages = ref([])
const renamingId = ref(null)

// Single image modal (WP Media–style view)
const selectedImage = ref(null)
const modalAltText = ref('')
const savingImageAlt = ref(false)
const generatingAlt = ref(false)
const pendingHistoryId = ref(null)

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
      notification.success('Alt text generated. Review and click Update to save.')
    } else {
      notification.error(result.message || 'Failed to generate alt text')
    }
  } catch (error) {
    notification.error(error.message || 'Failed to generate alt text')
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
      notification.success('Alt text updated.')
      closeImageModal()
    } else {
      notification.error(result.message || 'Failed to update alt text')
    }
  } catch (error) {
    notification.error(error.message || 'Failed to update alt text')
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
      notification.success(`Renamed to: ${result.new_filename}`)
    } else {
      notification.error(result.error || 'Failed to rename file')
    }
  } catch (error) {
    notification.error('Failed to rename file')
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
    notification.error('Selected images must have alt text to rename')
    return
  }

  try {
    const result = await Api.post('/renamer/rename-bulk', {
      attachment_ids: imagesWithAlt
    })
    if (result.success || result.error_count === 0) {
      notification.success(`Renamed ${result.success_count} files`)
      loadImages()
      selectedImages.value = []
    } else {
      notification.error(`Renamed ${result.success_count}/${selectedImages.value.length} files`)
    }
  } catch (error) {
    notification.error('Bulk rename failed')
  }
}

onMounted(() => {
  loadImages()
})
</script>
