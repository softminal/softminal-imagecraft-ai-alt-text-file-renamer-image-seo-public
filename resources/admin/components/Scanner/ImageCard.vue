<template>
  <div
    @click="$emit('toggle-select', image.id)"
    :class="['image-card p-2 rounded-lg border-2 cursor-pointer transition',
             selected ? 'border-blue-600 bg-blue-50'
             : altTooLong ? 'border-orange-400'
             : 'border-gray-200']"
  >
    <div class="relative mb-2">
      <div
        class="cursor-pointer"
        @click.stop="$emit('open-modal', image)"
      >
        <img
          :src="image.thumbnail || image.url"
          :alt="image.alt_text || image.filename"
          class="w-full h-24 object-cover rounded"
        />
      </div>
      <input
        type="checkbox"
        :checked="selected"
        @click.stop="$emit('toggle-select', image.id)"
        class="absolute top-1 right-1"
      />
    </div>
    <div class="text-xs truncate" :title="image.filename">
      {{ image.filename }}
    </div>
    <div class="text-xs mt-1" :class="image.has_alt ? 'text-green-600' : 'text-orange-600'">
      {{ image.has_alt ? $t('Has alt') : $t('Missing') }}
    </div>
    <div
      v-if="image.alt_text"
      class="text-xs mt-1 text-gray-600 line-clamp-2 break-words"
      :title="image.alt_text"
    >
      {{ image.alt_text }}
    </div>
    <div v-else class="text-xs mt-1 text-gray-400 italic">
      {{ $t('No alt text') }}
    </div>
    <div v-if="image.has_alt && !renaming && renameMode === 'title_and_filename'" class="flex justify-between mt-2">
      <button
        @click.stop="$emit('rename', image.id)"
        class="text-xs flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
      >
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
        </svg>
        {{ $t('Rename File') }}
      </button>
      <div v-if="altTooLong" class="bg-orange-100 py-1 px-2 rounded-md text-orange-400">
         {{$t("Too long")}}
      </div>
    </div>
    <div v-if="renaming" class="mt-2">
      <span class="text-xs text-gray-500 flex items-center gap-1">
        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        {{ $t('Renaming...') }}
      </span>
    </div>
    <div v-if="image.new_filename" class="mt-1">
      <span class="text-xs text-green-600">
        ✓ {{ image.new_filename }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { $t } from '../../utils/i18n'

defineProps({
  image: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  renaming: { type: Boolean, default: false },
  altTooLong: { type: Boolean, default: false },
  renameMode: { type: String, default: 'title_only' },
})

defineEmits(['toggle-select', 'open-modal', 'rename'])
</script>
