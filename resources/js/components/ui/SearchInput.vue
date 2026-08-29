<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search…' },
  label: { type: String, default: 'Search' },
  delay: { type: Number, default: 300 },
})
const emit = defineEmits(['update:modelValue'])

const local = ref(props.modelValue)
let timer = null

watch(
  () => props.modelValue,
  (value) => {
    if (value !== local.value) local.value = value
  },
)

// Debounced so typing doesn't fire a request per keystroke.
watch(local, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => emit('update:modelValue', value), props.delay)
})

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="relative">
    <label class="sr-only" :for="`search-${label}`">{{ label }}</label>
    <svg
      class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
      viewBox="0 0 20 20"
      fill="currentColor"
      aria-hidden="true"
    >
      <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 103.4 9.8l3.15 3.15a1 1 0 001.4-1.4l-3.14-3.15A5.5 5.5 0 009 3.5zM5.5 9a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd" />
    </svg>
    <input
      :id="`search-${label}`"
      v-model="local"
      type="search"
      class="field-input pl-9"
      :placeholder="placeholder"
    />
  </div>
</template>
