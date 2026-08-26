<template>
  <div class="animate-pulse" aria-hidden="true">
    <div
      v-for="n in lines"
      :key="n"
      class="bg-ic-muted rounded-ic"
      :class="n > 1 ? 'mt-2' : ''"
      :style="{ height, width: n === lines && lines > 1 ? '60%' : '100%' }"
    ></div>
  </div>
</template>

<script setup>
defineProps({
  lines: { type: Number, default: 1 },
  height: { type: String, default: '0.875rem' },
})
</script>
