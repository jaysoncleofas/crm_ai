<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/16/solid'

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
  <span
    class="relative block w-full before:absolute before:inset-px before:rounded-[calc(var(--radius-lg)-1px)] before:bg-white before:shadow-sm after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:ring-inset after:ring-transparent has-[:focus]:after:ring-2 has-[:focus]:after:ring-blue-500 dark:before:hidden"
  >
    <MagnifyingGlassIcon
      class="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400"
      aria-hidden="true"
    />
    <input
      v-model="local"
      type="search"
      :aria-label="label"
      :placeholder="placeholder"
      class="relative block w-full appearance-none rounded-lg border border-zinc-950/10 bg-transparent py-2.5 pr-3.5 pl-9 text-base/6 text-zinc-950 placeholder:text-zinc-500 hover:border-zinc-950/20 focus:outline-none sm:py-1.5 sm:text-sm/6 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:border-white/20"
    />
  </span>
</template>
