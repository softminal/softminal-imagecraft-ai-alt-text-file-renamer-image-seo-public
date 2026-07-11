<template>
  <div class="flex items-center gap-3">
    <img
      v-if="previewUrl"
      :src="previewUrl"
      alt=""
      :class="compact ? 'w-10 h-10' : 'w-16 h-16'"
      class="rounded object-cover bg-gray-100 border shrink-0"
    />
    <div
      v-else
      :class="compact ? 'w-10 h-10' : 'w-16 h-16'"
      class="rounded bg-gray-100 border border-dashed flex items-center justify-center shrink-0"
    >
      <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <div class="flex items-center gap-2">
      <button
        type="button"
        @click="openPicker"
        class="bg-white border text-gray-700 px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition"
      >
        {{ attachmentId > 0 ? $t('Change image') : $t('Choose image') }}
      </button>
      <button
        v-if="attachmentId > 0"
        type="button"
        @click="clear"
        class="text-sm text-red-500 hover:text-red-700"
      >
        {{ $t('Remove') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { $t } from '../../utils/i18n'

const props = defineProps({
  attachmentId: { type: Number, default: 0 },
  compact: { type: Boolean, default: false },
})
const emit = defineEmits(['pick'])

const previewUrl = ref('')
let frame = null

// Resolve a preview thumbnail for an already-saved attachment ID.
const loadPreview = (id) => {
  previewUrl.value = ''
  if (!id || !window.wp?.media?.attachment) return
  const attachment = window.wp.media.attachment(id)
  attachment.fetch().then(() => {
    const sizes = attachment.get('sizes')
    previewUrl.value = sizes?.thumbnail?.url || attachment.get('url') || ''
  }).catch(() => {})
}

watch(() => props.attachmentId, loadPreview, { immediate: true })

const openPicker = () => {
  if (!window.wp?.media) return
  if (!frame) {
    frame = window.wp.media({
      title: $t('Select fallback image'),
      button: { text: $t('Use this image') },
      library: { type: 'image' },
      multiple: false,
    })
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first()?.toJSON()
      if (attachment?.id) {
        previewUrl.value = attachment.sizes?.thumbnail?.url || attachment.url || ''
        emit('pick', attachment.id)
      }
    })
  }
  frame.open()
}

const clear = () => {
  previewUrl.value = ''
  emit('pick', 0)
}
</script>
