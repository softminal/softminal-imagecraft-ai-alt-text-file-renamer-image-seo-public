<template>
  <div class="flex flex-col items-center justify-center text-center px-6 py-10">
    <div
      v-if="icon || $slots.icon"
      class="flex items-center justify-center w-11 h-11 rounded-full bg-ic-muted text-ic-faint mb-3"
    >
      <slot name="icon">
        <component :is="icon" :size="22" :stroke-width="2" />
      </slot>
    </div>
    <h3 class="text-sm font-semibold text-ic-text">{{ title }}</h3>
    <p v-if="description" class="mt-1 text-[0.8125rem] text-ic-soft max-w-sm">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  // Lucide icon component (optional; #icon slot overrides)
  icon: { type: [Object, Function], default: null },
})
</script>
