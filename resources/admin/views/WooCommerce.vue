<template>
  <div class="woocommerce p-6">
    <div class="mb-6">
      <div class="flex items-center gap-3 mb-4">
        <svg class="w-8 h-8 text-purple-600" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
        </svg>
        <h1 class="text-2xl font-bold">WooCommerce Products</h1>
      </div>

      <div v-if="!woocommerceActive" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
        <svg class="w-12 h-12 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h2 class="text-lg font-semibold text-yellow-800 mb-2">WooCommerce Not Detected</h2>
        <p class="text-yellow-700">Please install and activate WooCommerce to use this feature.</p>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-600">Total Products</div>
            <div class="text-2xl font-bold">{{ productStats.total_products || 0 }}</div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-600">Missing Alt Text</div>
            <div class="text-2xl font-bold text-orange-600">{{ productStats.missing_alt || 0 }}</div>
            <div class="text-xs text-gray-500">{{ productStats.missing_percentage || 0 }}%</div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-600">Has Alt Text</div>
            <div class="text-2xl font-bold text-green-600">{{ productStats.has_alt || 0 }}</div>
          </div>
          <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-600">Selected</div>
            <div class="text-2xl font-bold text-blue-600">{{ selectedProducts.length }}</div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
          <div class="flex gap-2">
            <button
              @click="filter = 'all'; loadProducts()"
              :class="['px-4 py-2 rounded-lg', filter === 'all' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700']"
            >
              All Products
            </button>
            <button
              @click="filter = 'missing'; loadProducts()"
              :class="['px-4 py-2 rounded-lg', filter === 'missing' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700']"
            >
              Missing Alt
            </button>
            <button
              @click="filter = 'has_alt'; loadProducts()"
              :class="['px-4 py-2 rounded-lg', filter === 'has_alt' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700']"
            >
              Has Alt
            </button>
          </div>

          <div class="flex gap-2">
            <button
              @click="loadProducts()"
              class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300"
            >
              Refresh
            </button>
            <button
              v-if="selectedProducts.length > 0"
              @click="startBatch"
              class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700"
            >
              Generate for {{ selectedProducts.length }} Selected
            </button>
          </div>
        </div>
      </template>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-500">
      Loading products...
    </div>

    <div v-else-if="woocommerceActive" class="bg-white rounded-lg shadow border">
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

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
        <div
          v-for="product in products"
          :key="product.id"
          @click="toggleSelect(product.id)"
          :class="['product-card p-4 rounded-lg border-2 cursor-pointer transition',
                   selectedProducts.includes(product.id) ? 'border-purple-600 bg-purple-50' : 'border-gray-200']"
        >
          <div class="flex gap-4">
            <div class="relative shrink-0">
              <img
                :src="product.thumbnail_url || 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23ddd\' width=\'100\' height=\'100\'/%3E%3Ctext x=\'50\' y=\'55\' text-anchor=\'middle\' fill=\'%23999\' font-size=\'12\'%3ENo Image%3C/text%3E%3C/svg%3E'"
                :alt="product.title"
                class="w-20 h-20 object-cover rounded"
              />
              <input
                type="checkbox"
                :checked="selectedProducts.includes(product.id)"
                @click.stop="toggleSelect(product.id)"
                class="absolute top-1 right-1"
              />
              <div
                v-if="product.has_alt"
                class="absolute bottom-1 right-1 bg-green-500 text-white text-xs px-1.5 py-0.5 rounded"
              >
                ✓
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-medium text-sm truncate" :title="product.title">{{ product.title }}</h3>
              <p v-if="product.sku" class="text-xs text-gray-500">SKU: {{ product.sku }}</p>
              <div class="text-sm text-gray-600 mt-1" v-html="product.price"></div>
              <div
                v-if="product.alt_text"
                class="text-xs mt-2 text-gray-600 line-clamp-2"
                :title="product.alt_text"
              >
                {{ product.alt_text }}
              </div>
              <div v-else class="text-xs mt-2 text-orange-600 italic">
                No alt text
              </div>
            </div>
          </div>
          <div class="flex gap-2 mt-3">
            <button
              @click.stop="openProductModal(product)"
              class="text-xs flex-1 bg-gray-100 text-gray-700 px-2 py-1.5 rounded hover:bg-gray-200"
            >
              View Details
            </button>
            <button
              @click.stop="generateForProduct(product.id)"
              :disabled="generatingId === product.id"
              class="text-xs flex-1 bg-purple-100 text-purple-700 px-2 py-1.5 rounded hover:bg-purple-200 disabled:opacity-50"
            >
              {{ generatingId === product.id ? '...' : 'Generate' }}
            </button>
          </div>
        </div>
      </div>

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

    <Teleport to="body">
      <div
        v-if="selectedProduct"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="closeProductModal"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
          <div class="p-4 border-b flex items-center justify-between shrink-0">
            <h2 class="text-lg font-bold">Product Details</h2>
            <button
              @click="closeProductModal"
              class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
              aria-label="Close"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-4 overflow-y-auto flex-1">
            <div class="flex gap-4 flex-col sm:flex-row">
              <div class="shrink-0">
                <img
                  :src="selectedProduct.full_url"
                  :alt="modalAltText || selectedProduct.title"
                  class="max-h-64 w-full object-contain rounded-lg border bg-gray-50"
                />
              </div>
              <div class="flex-1 min-w-0 space-y-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                  <p class="text-sm text-gray-600">{{ selectedProduct.title }}</p>
                </div>
                <div v-if="selectedProduct.sku">
                  <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                  <p class="text-sm text-gray-600">{{ selectedProduct.sku }}</p>
                </div>
                <div>
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <label class="block text-sm font-medium text-gray-700">Featured Image Alt Text</label>
                    <button
                      @click="generateForModal"
                      :disabled="generatingAlt"
                      class="text-sm px-3 py-1.5 rounded-lg text-purple-700 disabled:opacity-50"
                    >
                      {{ generatingAlt ? 'Generating...' : 'Generate with AI' }}
                    </button>
                  </div>
                  <textarea
                    v-model="modalAltText"
                    rows="3"
                    class="w-full text-sm border rounded-lg p-2 resize-y"
                    placeholder="Describe for accessibility..."
                  />
                </div>
                <div v-if="selectedProduct.gallery && selectedProduct.gallery.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Gallery Images ({{ selectedProduct.gallery.length }})</label>
                  <div class="flex gap-2 flex-wrap">
                    <img
                      v-for="(galleryImg, index) in selectedProduct.gallery.slice(0, 4)"
                      :key="galleryImg.id"
                      :src="galleryImg.thumb_url"
                      class="w-16 h-16 object-cover rounded border"
                    />
                    <div
                      v-if="selectedProduct.gallery.length > 4"
                      class="w-16 h-16 bg-gray-100 rounded border flex items-center justify-center text-sm text-gray-600"
                    >
                      +{{ selectedProduct.gallery.length - 4 }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="p-4 border-t flex justify-end gap-2 shrink-0">
            <button
              @click="closeProductModal"
              class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300"
            >
              Cancel
            </button>
            <button
              @click="saveProductAlt"
              :disabled="savingAlt"
              class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50"
            >
              {{ savingAlt ? 'Saving...' : 'Update' }}
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
const woocommerceActive = ref(true)
const products = ref([])
const productStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const selectedProducts = ref([])
const generatingId = ref(null)

const selectedProduct = ref(null)
const modalAltText = ref('')
const savingAlt = ref(false)
const generatingAlt = ref(false)

const allSelected = computed(() => {
  return products.value.length > 0 && selectedProducts.value.length === products.value.length
})

const loadProducts = async (page = 1) => {
  loading.value = true
  try {
    const result = await Api.get(`/woocommerce/products?filter=${filter.value}&page=${page}&per_page=18`)
    if (result.success) {
      woocommerceActive.value = true
      products.value = result.products
      productStats.value = result.stats
      pagination.value = result.pagination
    }
  } catch (error) {
    if (error.message && error.message.includes('WooCommerce')) {
      woocommerceActive.value = false
    } else {
      notification.error('Failed to load products')
    }
  } finally {
    loading.value = false
  }
}

const loadPage = (page) => {
  if (page >= 1 && page <= pagination.value.total_pages) {
    loadProducts(page)
  }
}

const toggleSelect = (productId) => {
  const index = selectedProducts.value.indexOf(productId)
  if (index > -1) {
    selectedProducts.value.splice(index, 1)
  } else {
    selectedProducts.value.push(productId)
  }
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedProducts.value = []
  } else {
    selectedProducts.value = products.value.map(p => p.id)
  }
}

const startBatch = async () => {
  if (selectedProducts.value.length === 0) return

  try {
    const results = []
    for (const productId of selectedProducts.value) {
      const result = await Api.post('/woocommerce/generate', {
        product_id: productId
      })
      results.push({ productId, ...result })
    }

    const successCount = results.filter(r => r.success).length
    notification.success(`Generated alt text for ${successCount}/${selectedProducts.value.length} products`)
    selectedProducts.value = []
    loadProducts()
  } catch (error) {
    notification.error('Batch generation failed')
  }
}

const generateForProduct = async (productId) => {
  generatingId.value = productId
  try {
    const result = await Api.post('/woocommerce/generate', {
      product_id: productId
    })
    if (result.success) {
      notification.success('Alt text generated successfully')
      loadProducts()
    } else {
      notification.error(result.error || 'Failed to generate alt text')
    }
  } catch (error) {
    notification.error('Failed to generate alt text')
  } finally {
    generatingId.value = null
  }
}

const openProductModal = async (product) => {
  selectedProduct.value = { ...product, gallery: [] }
  modalAltText.value = product.alt_text || ''

  try {
    const result = await Api.get(`/woocommerce/gallery?product_id=${product.id}`)
    if (result.success) {
      selectedProduct.value.gallery = result.gallery
    }
  } catch (error) {
    console.log('Failed to load gallery')
  }
}

const closeProductModal = () => {
  selectedProduct.value = null
}

const generateForModal = async () => {
  if (!selectedProduct.value) return
  generatingAlt.value = true
  try {
    const result = await Api.post('/woocommerce/generate', {
      product_id: selectedProduct.value.id
    })
    if (result.success) {
      modalAltText.value = result.alt_text
      notification.success('Alt text generated')
    } else {
      notification.error(result.error || 'Failed to generate')
    }
  } catch (error) {
    notification.error('Failed to generate alt text')
  } finally {
    generatingAlt.value = false
  }
}

const saveProductAlt = async () => {
  if (!selectedProduct.value) return
  savingAlt.value = true
  try {
    const product = products.value.find(p => p.id === selectedProduct.value.id)
    if (product) {
      product.alt_text = modalAltText.value
      product.has_alt = !!modalAltText.value
    }
    notification.success('Alt text updated')
    closeProductModal()
  } catch (error) {
    notification.error('Failed to save')
  } finally {
    savingAlt.value = false
  }
}

onMounted(() => {
  loadProducts()
})
</script>
