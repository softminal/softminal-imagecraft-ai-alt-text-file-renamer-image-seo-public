<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-1.5 font-medium rounded-ic transition-colors outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer',
      sizeClasses,
      variantClasses,
    ]"
    @click="$emit('click', $event)"
  >
    <svg
      v-if="loading"
      class="animate-spin h-3.5 w-3.5"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <slot name="icon" v-else />
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v),
  },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

defineEmits(['click'])

const sizeClasses = computed(() =>
  props.size === 'sm' ? 'px-3 py-1.5 text-[0.8125rem]' : 'px-4 py-2 text-sm'
)

const variantClasses = computed(() => ({
  primary: 'bg-brand text-white hover:bg-brand-strong',
  secondary: 'bg-ic-card text-ic-text border border-ic-border hover:bg-ic-muted',
  ghost: 'bg-transparent text-ic-soft hover:bg-ic-muted hover:text-ic-text',
  danger: 'bg-ic-danger text-white hover:opacity-90',
}[props.variant]))
</script>
