<template>
  <label
    :class="[
      'inline-flex items-center',
      $slots.default ? 'gap-2' : '',
      disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
    ]"
  >
    <input
      ref="inputEl"
      type="checkbox"
      class="sr-only"
      :checked="isChecked"
      :disabled="disabled"
      @change="onChange"
    />
    <span
      :class="[
        'flex items-center justify-center shrink-0 rounded-md border shadow-sm backdrop-blur-sm transition-colors',
        boxSize,
        isChecked || indeterminate
          ? 'bg-blue-600 border-blue-600'
          : 'bg-white/90 border-gray-300',
        !disabled && !(isChecked || indeterminate) ? 'hover:border-blue-400' : ''
      ]"
    >
      <svg
        v-show="isChecked || indeterminate"
        :class="['text-white', iconSize]"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path v-if="indeterminate && !isChecked" d="M6 12h12" />
        <path v-else d="M5 13l4 4L19 7" />
      </svg>
    </span>
    <span v-if="$slots.default"><slot /></span>
  </label>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'

const props = defineProps({
  // Boolean (or trueValue/falseValue) for a single checkbox, or an Array for a group (with `value`).
  modelValue: { type: [Boolean, Array, String, Number], default: false },
  // The value contributed to the array when `modelValue` is an Array.
  value: { type: [String, Number], default: undefined },
  // Native-style on/off values for single checkboxes (e.g. '1'/'0' for settings).
  trueValue: { type: [Boolean, String, Number], default: true },
  falseValue: { type: [Boolean, String, Number], default: false },
  disabled: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  // Size: 'sm'/'small', 'md'/'medium' (default), 'lg'/'large'.
  size: { type: String, default: 'md' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const inputEl = ref(null)

const isChecked = computed(() => {
  if (Array.isArray(props.modelValue)) {
    return props.modelValue.includes(props.value)
  }
  // Default boolean mode: any truthy is checked (backwards-compatible).
  if (props.trueValue === true && props.falseValue === false) {
    return !!props.modelValue
  }
  // Explicit true/false values (e.g. '1'/'0'): strict match against trueValue.
  return props.modelValue === props.trueValue
})

// Normalize size aliases (small/medium/large → sm/md/lg), defaulting to medium.
const sizeKey = computed(() => {
  const s = props.size
  if (s === 'sm' || s === 'small') return 'sm'
  if (s === 'lg' || s === 'large') return 'lg'
  return 'md'
})
const boxSize = computed(() => ({ sm: 'w-3.5 h-3.5', md: 'w-4 h-4', lg: 'w-5 h-5' }[sizeKey.value]))
const iconSize = computed(() => ({ sm: 'w-2.5 h-2.5', md: 'w-3 h-3', lg: 'w-3.5 h-3.5' }[sizeKey.value]))

// Mirror the indeterminate prop onto the (hidden) native input for accessibility.
const syncIndeterminate = () => {
  if (inputEl.value) inputEl.value.indeterminate = props.indeterminate
}
onMounted(syncIndeterminate)
watch(() => props.indeterminate, syncIndeterminate)

const onChange = (e) => {
  const checked = e.target.checked
  if (Array.isArray(props.modelValue)) {
    const next = props.modelValue.slice()
    const idx = next.indexOf(props.value)
    if (checked && idx === -1) next.push(props.value)
    if (!checked && idx !== -1) next.splice(idx, 1)
    emit('update:modelValue', next)
  } else {
    // Emit the configured on/off value (true/false by default, '1'/'0' for settings).
    emit('update:modelValue', checked ? props.trueValue : props.falseValue)
  }
  emit('change', checked)
}
</script>
