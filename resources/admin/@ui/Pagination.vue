<template>
  <div v-if="totalPages > 1 || showPerPage" :class="['icais-pagination flex flex-wrap items-center gap-2 p-4 border-t text-sm', alignClass]">
    <!-- Total -->
    <span v-if="total > 0" class="text-gray-500 mr-1">
      {{ $t('Total %s', total) }}
    </span>

    <!-- Per page sizes -->
    <select
      v-if="showPerPage"
      :value="perPage"
      @change="$emit('per-page-change', Number($event.target.value))"
      class="h-8 px-2 border border-gray-300 rounded text-sm text-gray-700 bg-white hover:border-blue-400 focus:outline-hidden focus:border-blue-500"
    >
      <option v-for="size in pageSizes" :key="size" :value="size">{{ size }} / {{ $t('page') }}</option>
    </select>

    <!-- Prev button -->
    <button
      :disabled="currentPage <= 1"
      @click="changePage(currentPage - 1)"
      class="icais-pager-btn"
      :title="$t('Previous')"
    >
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
    </button>

    <!-- Page numbers -->
    <template v-for="(page, i) in visiblePages" :key="i">
      <!-- Quick jump back (ellipsis) -->
      <button
        v-if="page === 'prev-more'"
        class="icais-pager-btn group"
        :title="$t('Previous 5 pages')"
        @click="changePage(Math.max(1, currentPage - 5))"
      >
        <span class="group-hover:hidden">&hellip;</span>
        <svg class="w-3.5 h-3.5 hidden group-hover:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>

      <!-- Quick jump forward (ellipsis) -->
      <button
        v-else-if="page === 'next-more'"
        class="icais-pager-btn group"
        :title="$t('Next 5 pages')"
        @click="changePage(Math.min(totalPages, currentPage + 5))"
      >
        <span class="group-hover:hidden">&hellip;</span>
        <svg class="w-3.5 h-3.5 hidden group-hover:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7m-8-14l7 7-7 7" />
        </svg>
      </button>

      <!-- Page number -->
      <button
        v-else
        :class="['icais-pager-btn', page === currentPage && 'active']"
        @click="changePage(page)"
      >
        {{ page }}
      </button>
    </template>

    <!-- Next button -->
    <button
      :disabled="currentPage >= totalPages"
      @click="changePage(currentPage + 1)"
      class="icais-pager-btn"
      :title="$t('Next')"
    >
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
      </svg>
    </button>

    <!-- Jumper -->
    <div v-if="showJumper && totalPages > 5" class="flex items-center gap-1 ml-1 text-gray-500">
      {{ $t('Go to') }}
      <input
        :value="jumperValue"
        @input="jumperValue = $event.target.value"
        @keydown.enter="jumpToPage"
        @blur="jumpToPage"
        type="number"
        min="1"
        :max="totalPages"
        class="w-12 h-8 px-1.5 text-center border border-gray-300 rounded text-sm text-gray-700 bg-white hover:border-blue-400 focus:outline-hidden focus:border-blue-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { $t } from '../utils/i18n'

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  total: { type: Number, default: 0 },
  perPage: { type: Number, default: 0 },
  pageSizes: { type: Array, default: () => [10, 20, 50, 100] },
  showPerPage: { type: Boolean, default: false },
  showJumper: { type: Boolean, default: true },
  pagerCount: { type: Number, default: 7 },
  align: { type: String, default: 'center', validator: v => ['left', 'center', 'right'].includes(v) }
})

const emit = defineEmits(['page-change', 'per-page-change'])

const alignClass = computed(() => ({
  left: 'justify-start',
  center: 'justify-center',
  right: 'justify-end'
})[props.align])

const jumperValue = ref('')

watch(() => props.currentPage, () => {
  jumperValue.value = ''
})

const changePage = (page) => {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('page-change', page)
  }
}

const jumpToPage = () => {
  const page = parseInt(jumperValue.value, 10)
  if (!isNaN(page)) {
    changePage(Math.max(1, Math.min(props.totalPages, page)))
  }
  jumperValue.value = ''
}

const visiblePages = computed(() => {
  const total = props.totalPages
  const current = props.currentPage
  const count = props.pagerCount

  if (total <= count) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  const halfCount = Math.floor(count / 2)
  const showPrevMore = current > halfCount + 1
  const showNextMore = current < total - halfCount

  const pages = []

  // Always page 1
  pages.push(1)

  if (showPrevMore) {
    pages.push('prev-more')
  }

  // Calculate middle range
  let start, end
  if (!showPrevMore && showNextMore) {
    start = 2
    end = count - 1
  } else if (showPrevMore && !showNextMore) {
    start = total - count + 2
    end = total - 1
  } else {
    const offset = Math.floor((count - 3) / 2)
    start = current - offset
    end = current + offset
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  if (showNextMore) {
    pages.push('next-more')
  }

  // Always last page
  pages.push(total)

  return pages
})
</script>

<style scoped>
@reference "tailwindcss";
.icais-pager-btn {
  @apply min-w-[32px] h-8 px-1.5 flex items-center justify-center rounded text-gray-600 bg-white border border-transparent cursor-pointer transition-colors duration-150;
}
.icais-pager-btn:hover:not(:disabled):not(.active) {
  @apply text-blue-600;
}
.icais-pager-btn:disabled {
  @apply text-gray-300 cursor-not-allowed;
}
.icais-pager-btn.active {
  @apply text-blue-600 font-semibold border-blue-500;
}
</style>
