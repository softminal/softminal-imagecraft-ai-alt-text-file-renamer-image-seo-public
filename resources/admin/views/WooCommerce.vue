<template>
  <div class="woocommerce p-6">
    <div class="mb-6">
      <div class="flex items-center gap-3 mb-4">
        <svg class="w-8 h-8 text-purple-600" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
        </svg>
        <h1 class="text-2xl font-bold">{{ $t('WooCommerce Products') }}</h1>
      </div>

      <div v-if="!woocommerceActive" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
        <svg class="w-12 h-12 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h2 class="text-lg font-semibold text-yellow-800 mb-2">{{ $t('WooCommerce Not Detected') }}</h2>
        <p class="text-yellow-700">{{ $t('Please install and activate WooCommerce to use this feature.') }}</p>
      </div>

      <template v-else>
        <!-- Filter Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
          <div class="flex flex-wrap items-center gap-3">
            <!-- All -->
            <button
              @click="filter = 'all'; debouncedLoadProducts()"
              :class="[
                'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                filter === 'all'
                  ? 'bg-purple-600 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ $t('All') }}
              <span :class="['ml-1 text-xs', filter === 'all' ? 'text-purple-200' : 'text-gray-500']">{{ productStats.total_products || 0 }}</span>
            </button>

            <div class="w-px h-6 bg-gray-200"></div>

            <!-- Alt Text group -->
            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $t('Alt Text') }}</span>
            <div class="flex items-center gap-1.5">
              <button
                @click="filter = 'missing'; debouncedLoadProducts()"
                :class="[
                  'px-3 py-1.5 rounded-full text-sm transition-colors',
                  filter === 'missing'
                    ? 'bg-red-500 text-white shadow-sm'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                ]"
              >
                {{ $t('Missing') }}
                <span :class="['ml-1 text-xs', filter === 'missing' ? 'text-red-200' : 'text-gray-500']">{{ productStats.missing_alt || 0 }}</span>
              </button>
              <button
                @click="filter = 'has_alt'; debouncedLoadProducts()"
                :class="[
                  'px-3 py-1.5 rounded-full text-sm transition-colors',
                  filter === 'has_alt'
                    ? 'bg-emerald-500 text-white shadow-sm'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                ]"
              >
                {{ $t('Has') }}
                <span :class="['ml-1 text-xs', filter === 'has_alt' ? 'text-emerald-200' : 'text-gray-500']">{{ productStats.has_alt || 0 }}</span>
              </button>
              <button
                @click="filter = 'generic'; debouncedLoadProducts()"
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
                @click="filter = 'too_long'; debouncedLoadProducts()"
                :class="[
                  'px-3 py-1.5 rounded-full text-sm transition-colors',
                  filter === 'too_long'
                    ? 'bg-orange-500 text-white shadow-sm'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                ]"
              >
                {{ $t('Long') }}<template v-if="productStats.max_length"><span :class="['ml-0.5 text-xs', filter === 'too_long' ? 'text-orange-200' : 'text-gray-500']">&gt;{{ productStats.max_length }}</span></template>
              </button>
            </div>

            <!-- Category dropdown inline -->
            <template v-if="categories.length > 0">
              <div class="w-px h-6 bg-gray-200"></div>
              <select
                v-model="selectedCategory"
                @change="loadProducts()"
                class="px-3 py-1.5 rounded-full border border-gray-200 bg-gray-100 text-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
              >
                <option :value="0">{{ $t('All Categories') }}</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.parent > 0 ? '— ' : '' }}{{ cat.name }} ({{ cat.count }})
                </option>
              </select>
            </template>

            <!-- Clear filter -->
            <template v-if="filter !== 'all' || selectedCategory > 0 || hasAdvancedFilters">
              <div class="w-px h-6 bg-gray-200"></div>
              <button
                @click="clearAllFilters"
                class="px-3 py-1.5 rounded-full text-sm text-red-600 bg-red-50 hover:bg-red-100 transition-colors"
              >
                &times; {{ $t('Clear') }}
              </button>
            </template>

            <!-- Advanced toggle -->
            <button
              @click="showAdvancedFilters = !showAdvancedFilters"
              :class="[
                'ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm transition-colors',
                showAdvancedFilters || hasAdvancedFilters
                  ? 'bg-purple-50 text-purple-600'
                  : 'text-gray-500 hover:bg-gray-100'
              ]"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
              {{ $t('Advanced') }}
              <span v-if="hasAdvancedFilters" class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
            </button>
          </div>

          <!-- Advanced Filters (smooth expand) -->
          <div
            class="grid transition-all duration-300 ease-in-out"
            :class="showAdvancedFilters ? 'grid-rows-[1fr] opacity-100 mt-4' : 'grid-rows-[0fr] opacity-0 mt-0'"
          >
            <div class="overflow-hidden">
              <div class="pt-4 border-t border-gray-100 space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Created From') }}</label>
                    <input
                      v-model="dateFrom"
                      type="date"
                      class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Created To') }}</label>
                    <input
                      v-model="dateTo"
                      type="date"
                      class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Min Price') }}</label>
                    <input
                      v-model.number="minPrice"
                      type="number"
                      min="0"
                      step="1"
                      :placeholder="$t('e.g. 10')"
                      class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $t('Max Price') }}</label>
                    <input
                      v-model.number="maxPrice"
                      type="number"
                      min="0"
                      step="1"
                      :placeholder="$t('e.g. 100')"
                      class="w-28 px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
                    />
                  </div>
                  <button
                    @click="selectedProducts = []; loadProducts()"
                    class="px-4 py-1.5 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700"
                  >
                    {{ $t('Apply') }}
                  </button>
                  <button
                    v-if="hasAdvancedFilters"
                    @click="clearAdvancedFilters"
                    class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900"
                  >
                    {{ $t('Clear') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <div v-if="loading && products.length === 0" class="text-center py-12 text-gray-500">
      {{ $t('Loading products...') }}
    </div>

    <div v-else-if="woocommerceActive" class="bg-white rounded-lg shadow border" :class="{ 'opacity-60 pointer-events-none': loading }">
      <!-- Select All Row -->
      <div class="p-4 border-b flex items-center justify-between gap-4">
        <div class="flex items-center flex-wrap gap-2">
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              :checked="allSelected"
              @change="toggleSelectAll"
              class="rounded"
            />
            <span>{{ $t('Select All') }}</span>
          </label>

          <div
            v-if="allSelected && pagination.total > products.length"
            class="text-sm text-purple-700 bg-purple-50 border border-purple-200 rounded-lg px-3 py-1 inline-flex items-center gap-1.5"
          >
            <span>{{ $t('Page selected.') }}</span>
            <span class="font-semibold">{{ $t('Select all %s', pagination.total) }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            v-if="selectedProducts.length > 0"
            @click="startBatch"
            class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-purple-700 shadow-sm transition-colors"
          >
            {{ $t('Generate Alt (%s)', selectedProducts.length) }}
          </button>
          <button
            v-if="selectedProducts.length > 0"
            @click="startTitleBatch"
            class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-indigo-700 shadow-sm transition-colors"
          >
            {{ $t('Generate Title (%s)', selectedProducts.length) }}
          </button>
        </div>
      </div>

      <!-- Product Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
        <ProductCard
          v-for="product in products"
          :key="product.id"
          :product="product"
          :selected="selectedProducts.includes(product.id)"
          :generating="generatingProgress[product.id] || null"
          @toggle-select="toggleSelect"
          @open-modal="openProductModal"
          @generate-all="generateAllForProduct"
        />
      </div>

      <Pagination
        :current-page="pagination.current_page || 1"
        :total-pages="pagination.total_pages || 1"
        :total="pagination.total || 0"
        @page-change="loadPage"
      />
    </div>

    <!-- Product Modal -->
    <ProductModal
      :product="selectedProduct"
      @close="closeProductModal"
      @saved="onModalSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { notification } from '../plugins/notification'
import { $t } from '../utils/i18n'
import { debounce } from '../utils/debounce'
import { Pagination } from '../@ui'
import ProductCard from '../components/WooCommerce/ProductCard.vue'
import ProductModal from '../components/WooCommerce/ProductModal.vue'

const router = useRouter()
const woocommerceActive = ref(true)
const products = ref([])
const productStats = ref({})
const pagination = ref({})
const loading = ref(false)
const filter = ref('all')
const selectedCategory = ref(0)
const categories = ref([])
const selectedProducts = ref([])
const generatingProgress = reactive({})
const showAdvancedFilters = ref(false)
const dateFrom = ref('')
const dateTo = ref('')
const minPrice = ref(0)
const maxPrice = ref(0)

// Modal
const selectedProduct = ref(null)

const allSelected = computed(() => {
  return products.value.length > 0 && selectedProducts.value.length === products.value.length
})

const hasAdvancedFilters = computed(() => {
  return Boolean(dateFrom.value || dateTo.value || minPrice.value > 0 || maxPrice.value > 0)
})

const clearAdvancedFilters = () => {
  dateFrom.value = ''
  dateTo.value = ''
  minPrice.value = 0
  maxPrice.value = 0
  selectedProducts.value = []
  loadProducts()
}

const clearAllFilters = () => {
  filter.value = 'all'
  selectedCategory.value = 0
  dateFrom.value = ''
  dateTo.value = ''
  minPrice.value = 0
  maxPrice.value = 0
  debouncedLoadProducts()
}

// Data loading
const loadCategories = async () => {
  try {
    const result = await Api.get('/woocommerce/categories')
    if (result.success) {
      categories.value = result.categories
    }
  } catch (error) {
    // Categories are optional — silently fail
  }
}

const loadProducts = async (page = 1) => {
  loading.value = true
  try {
    let url = `/woocommerce/products?filter=${filter.value}&page=${page}&per_page=18`
    if (selectedCategory.value > 0) url += `&category=${selectedCategory.value}`
    if (dateFrom.value) url += `&date_from=${dateFrom.value}`
    if (dateTo.value) url += `&date_to=${dateTo.value}`
    if (minPrice.value > 0) url += `&min_price=${minPrice.value}`
    if (maxPrice.value > 0) url += `&max_price=${maxPrice.value}`
    const result = await Api.get(url)
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
      notification.error($t('Failed to load products'))
    }
  } finally {
    loading.value = false
  }
}

const debouncedLoadProducts = debounce(() => loadProducts(), 300)

const loadPage = (page) => {
  if (page >= 1 && page <= pagination.value.total_pages) {
    loadProducts(page)
  }
}

// Selection
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

// Collect ALL image IDs (featured + gallery) from selected products, deduplicated
const collectAllImageIds = () => {
  const idSet = new Set()
  for (const productId of selectedProducts.value) {
    const product = products.value.find(p => p.id === productId)
    if (!product) continue
    if (product.thumbnail_id) idSet.add(product.thumbnail_id)
    if (product.gallery_ids) {
      for (const gId of product.gallery_ids) idSet.add(gId)
    }
  }
  return Array.from(idSet)
}

// Batch operations
const startBatch = async () => {
  if (selectedProducts.value.length === 0) return
  const imageIds = collectAllImageIds()
  if (imageIds.length === 0) {
    notification.error($t('Selected products have no images'))
    return
  }
  if (imageIds.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }
  try {
    const result = await Api.post('/batch/start', { image_ids: imageIds })
    if (result.success) {
      notification.success($t('Batch job started for %s images', imageIds.length))
      router.push(`/batch/${result.job_id}`)
    }
  } catch (error) {
    if (error.response?.job_id) {
      notification.warning($t('A batch job is already in progress.'))
      router.push(`/batch/${error.response.job_id}`)
    } else {
      notification.error(error.message || $t('Failed to start batch job'))
    }
  }
}

const startTitleBatch = async () => {
  if (selectedProducts.value.length === 0) return
  const imageIds = collectAllImageIds()
  if (imageIds.length === 0) {
    notification.error($t('Selected products have no images'))
    return
  }
  if (imageIds.length > 500) {
    notification.error($t('Maximum 500 images per batch job. Please reduce your selection.'))
    return
  }
  try {
    const result = await Api.post('/batch/start', { image_ids: imageIds, type: 'title' })
    if (result.success) {
      notification.success($t('Title generation started for %s images', imageIds.length))
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

// Card-level generate all
const generateAllForProduct = async (product) => {
  if (generatingProgress[product.id]) return

  const allIds = []
  if (product.thumbnail_id) allIds.push(product.thumbnail_id)
  if (product.gallery_ids) allIds.push(...product.gallery_ids)

  if (allIds.length === 0) {
    notification.error($t('Product has no images'))
    return
  }

  generatingProgress[product.id] = { current: 0, total: allIds.length }

  try {
    if (product.thumbnail_id) {
      try { await Api.post('/woocommerce/generate', { product_id: product.id }) } catch (e) { /* continue */ }
      generatingProgress[product.id].current++
    }
    if (product.gallery_ids && product.gallery_ids.length > 0) {
      try {
        const result = await Api.post('/woocommerce/generate-gallery', { product_id: product.id })
        if (result.success) generatingProgress[product.id].current = allIds.length
      } catch (e) { /* continue */ }
    }
    notification.success($t('Generated alt text for all images'))
    loadProducts(pagination.value.current_page || 1)
  } catch (error) {
    notification.error($t('Failed to generate alt text'))
  } finally {
    delete generatingProgress[product.id]
  }
}

// Modal
const openProductModal = (product) => {
  selectedProduct.value = { ...product }
}

const closeProductModal = () => {
  selectedProduct.value = null
}

const onModalSaved = () => {
  loadProducts(pagination.value.current_page || 1)
  closeProductModal()
}

onMounted(() => {
  loadCategories()
  loadProducts()
})
</script>
