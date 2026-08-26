<template>
  <div class="flex items-center justify-between gap-3 mb-3">
    <div class="min-w-0">
      <h2 class="text-base font-semibold text-ic-text flex items-center gap-1.5">
        {{ title }}
        <slot name="badge" />
      </h2>
      <p v-if="description" class="mt-0.5 text-[0.8125rem] text-ic-soft">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="shrink-0 flex items-center gap-2">
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
