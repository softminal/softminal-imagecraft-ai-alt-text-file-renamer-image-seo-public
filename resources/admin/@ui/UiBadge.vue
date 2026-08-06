<template>
  <span
    :class="[
      'inline-flex items-center gap-1 px-2 py-0.5 rounded-pill text-xs font-medium whitespace-nowrap',
      toneClasses,
    ]"
  >
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  tone: {
    type: String,
    default: 'neutral',
    validator: (v) =>
      ['neutral', 'success', 'warning', 'danger', 'info', 'pro', 'seo', 'perf'].includes(v),
  },
})

const toneClasses = computed(() => ({
  neutral: 'bg-ic-muted text-ic-soft',
  success: 'bg-ic-success-soft text-ic-success',
  warning: 'bg-ic-warning-soft text-ic-warning',
  danger: 'bg-ic-danger-soft text-ic-danger',
  info: 'bg-ic-info-soft text-ic-info',
  pro: 'bg-ic-pro-soft text-ic-pro',
  seo: 'bg-pillar-seo-soft text-brand-soft-text',
  perf: 'bg-pillar-perf-soft text-pillar-perf',
}[props.tone]))
</script>
