<template>
  <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div class="min-w-0">
      <h1 class="text-[1.375rem] leading-7 font-bold text-ic-text">{{ title }}</h1>
      <p v-if="description" class="mt-1 text-sm text-ic-soft">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>
