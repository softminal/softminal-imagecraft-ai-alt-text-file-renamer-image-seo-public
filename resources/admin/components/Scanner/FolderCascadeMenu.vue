<script setup>
import { ref } from 'vue'

defineProps({
  folders: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['select'])
const hoveredId = ref(null)
</script>

<template>
  <div
    v-for="folder in folders"
    :key="folder.id"
    class="relative"
    @mouseenter="hoveredId = folder.id"
    @mouseleave="hoveredId = null"
  >
    <button
      type="button"
      class="w-full flex items-center justify-between px-3 py-1.5 text-sm text-left hover:bg-gray-50 gap-2"
      @click="emit('select', folder.id)"
    >
      <span class="truncate">{{ folder.name }}</span>
      <span class="flex items-center gap-1.5 shrink-0">
        <span class="text-xs text-gray-400">{{ folder.count }}</span>
        <svg
          v-if="folder.children?.length"
          class="w-3 h-3 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </span>
    </button>

    <!-- Recursive submenu panel -->
    <div
      v-if="folder.children?.length && hoveredId === folder.id"
      class="absolute left-full top-0 z-50 bg-white border border-gray-100 rounded-lg shadow-sm py-1 min-w-40"
    >
      <FolderCascadeMenu
        :folders="folder.children"
        @select="emit('select', $event)"
      />
    </div>
  </div>
</template>
