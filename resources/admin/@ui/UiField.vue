<template>
  <div :class="{ 'opacity-60': lockedPro }">
    <label class="block">
      <span class="flex items-center gap-1.5 text-sm font-medium text-ic-text mb-1">
        {{ label }}
        <UiBadge v-if="lockedPro" tone="pro">Pro</UiBadge>
      </span>
      <slot />
    </label>
    <p v-if="help" class="mt-1 text-[0.8125rem] text-ic-soft">{{ help }}</p>
  </div>
</template>

<script setup>
import UiBadge from './UiBadge.vue'

defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
  // lockedPro: dims the field and shows the Pro badge (caller also disables the input)
  lockedPro: { type: Boolean, default: false },
})
</script>
