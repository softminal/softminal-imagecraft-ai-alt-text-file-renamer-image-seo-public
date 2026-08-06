<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue ? 'true' : 'false'"
    :disabled="disabled"
    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-transparent focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
    :style="trackStyle"
    @click="toggle"
  >
    <span
      class="pointer-events-none absolute top-[1px] left-[1px] inline-block h-4 w-4 rounded-full border border-gray-300 bg-white shadow-xs"
      :style="thumbStyle"
    ></span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const trackStyle = computed(() => ({
  backgroundColor: props.modelValue ? 'var(--ic-brand, #2563eb)' : '#d1d5db',
  transition: 'background-color 150ms ease',
}))

const thumbStyle = computed(() => ({
  transform: props.modelValue ? 'translateX(16px)' : 'translateX(0)',
  transition: 'transform 150ms ease',
}))

const toggle = () => {
  if (props.disabled) return

  const nextValue = !props.modelValue
  emit('update:modelValue', nextValue)
  emit('change', nextValue)
}
</script>
