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
  // Boolean for a single checkbox, or an Array for a checkbox group (with `value`).
  modelValue: { type: [Boolean, Array], default: false },
  // The value contributed to the array when `modelValue` is an Array.
  value: { type: [String, Number], default: undefined },
  disabled: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  size: { type: String, default: 'md' }, // 'sm' | 'md'
})

const emit = defineEmits(['update:modelValue', 'change'])

const inputEl = ref(null)

const isChecked = computed(() =>
  Array.isArray(props.modelValue)
    ? props.modelValue.includes(props.value)
    : !!props.modelValue
)

const boxSize = computed(() => (props.size === 'sm' ? 'w-5 h-5' : 'w-6 h-6'))
const iconSize = computed(() => (props.size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'))

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
    emit('update:modelValue', checked)
  }
  emit('change', checked)
}
</script>
