<template>
  <div class="bg-white rounded-lg shadow border overflow-hidden flex flex-col">
    <div class="relative">
      <img
        :src="image.thumbnail || image.url"
        :alt="image.filename"
        class="w-full h-32 object-cover bg-gray-100"
      />
      <Checkbox
        :model-value="selected"
        size="lg"
        class="absolute top-2 left-2"
        @change="$emit('toggle', image.id)"
      />
      <span
        v-if="image.compressed"
        class="absolute top-2 right-2 bg-green-600 text-white text-xs px-2 py-0.5 rounded-full"
      >{{ $t('Compressed') }}</span>
    </div>
    <div class="p-3 flex-1 flex flex-col">
      <div class="text-xs text-gray-600 truncate" :title="image.filename">{{ image.filename }}</div>
      <div class="text-sm font-medium text-gray-800">{{ image.size_human }}</div>
      <div v-if="image.compressed && image.saved_percent > 0" class="text-xs text-green-600 mt-1">
        {{ $t('Saved %s (%s%)', image.saved_human, image.saved_percent) }}
      </div>
      <div class="mt-auto pt-2 flex gap-2">
        <button
          v-if="!image.compressed"
          :disabled="busy"
          @click="$emit('compress', image)"
          class="flex-1 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 disabled:opacity-50"
        >{{ busy ? $t('Compressing...') : $t('Compress') }}</button>
        <template v-else>
          <button
            :disabled="busy"
            @click="$emit('recompress', image)"
            class="flex-1 text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200 disabled:opacity-50"
          >{{ $t('Re-compress') }}</button>
          <button
            v-if="image.can_restore"
            :disabled="busy"
            @click="$emit('restore', image)"
            class="flex-1 text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded hover:bg-amber-200 disabled:opacity-50"
          >{{ $t('Restore') }}</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

defineProps({
  image: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

defineEmits(['toggle', 'compress', 'recompress', 'restore'])
</script>
