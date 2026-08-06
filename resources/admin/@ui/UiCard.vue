<template>
  <div class="bg-ic-card border border-ic-border rounded-card shadow-card">
    <div
      v-if="title || $slots.header || $slots.meta"
      class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ic-border-soft"
    >
      <slot name="header">
        <div class="min-w-0">
          <h2 class="text-base font-semibold text-ic-text truncate">{{ title }}</h2>
          <p v-if="description" class="mt-0.5 text-[0.8125rem] text-ic-soft">{{ description }}</p>
        </div>
      </slot>
      <div v-if="$slots.meta" class="shrink-0">
        <slot name="meta" />
      </div>
    </div>
    <div :class="flush ? '' : 'p-5'">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  // flush: no body padding (tables / media grids handle their own)
  flush: { type: Boolean, default: false },
})
</script>
