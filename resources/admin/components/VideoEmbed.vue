<template>
  <div class="relative w-full overflow-hidden rounded-ic bg-ic-muted" style="padding-bottom: 56.25%;">
    <!-- Nothing third-party loads until the user clicks (WP.org external-service
         disclosure + privacy). On click we swap in a youtube-nocookie iframe. -->
    <iframe
      v-if="activated"
      class="absolute inset-0 w-full h-full"
      :src="`https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1`"
      :title="title"
      frameborder="0"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
      allowfullscreen
    ></iframe>
    <button
      v-else
      type="button"
      class="absolute inset-0 w-full h-full flex flex-col items-center justify-center gap-3 cursor-pointer bg-gradient-to-br from-ic-muted to-ic-card border border-ic-border rounded-ic transition-colors hover:border-brand focus-visible:ring-2 focus-visible:ring-brand outline-hidden group"
      @click="activated = true"
    >
      <span class="w-14 h-14 rounded-full bg-brand text-white flex items-center justify-center shadow-card transition-transform group-hover:scale-105">
        <Play class="w-6 h-6 ml-0.5" fill="currentColor" aria-hidden="true" />
      </span>
      <span class="text-sm font-medium text-ic-text">{{ title }}</span>
      <span class="text-xs text-ic-soft">{{ $t('Click to load the video from YouTube') }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Play } from 'lucide-vue-next'
import { $t } from '../utils/i18n'

defineProps({
  videoId: { type: String, required: true },
  title: { type: String, default: '' },
})

const activated = ref(false)
</script>
