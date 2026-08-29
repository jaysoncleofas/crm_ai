<script setup>
import { computed } from 'vue'
import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon } from '@heroicons/vue/16/solid'

const props = defineProps({
  field: { type: String, required: true },
  sort: { type: String, default: '' },
  label: { type: String, required: true },
})
const emit = defineEmits(['sort'])

const direction = computed(() => {
  if (props.sort === props.field) return 'asc'
  if (props.sort === `-${props.field}`) return 'desc'
  return null
})

const ariaSort = computed(() =>
  direction.value === 'asc' ? 'ascending' : direction.value === 'desc' ? 'descending' : 'none',
)

const glyph = computed(() =>
  direction.value === 'asc' ? ChevronUpIcon : direction.value === 'desc' ? ChevronDownIcon : ChevronUpDownIcon,
)
</script>

<template>
  <th
    scope="col"
    class="border-b border-b-zinc-950/10 px-4 py-2 font-medium first:pl-(--gutter,--spacing(2)) last:pr-(--gutter,--spacing(2)) dark:border-b-white/10"
    :aria-sort="ariaSort"
  >
    <button
      type="button"
      class="group -mx-1 inline-flex items-center gap-1 rounded px-1 hover:text-zinc-950 dark:hover:text-white"
      @click="emit('sort', direction === 'asc' ? `-${field}` : field)"
    >
      {{ label }}
      <component
        :is="glyph"
        class="size-3.5 shrink-0"
        :class="direction ? 'text-zinc-950 dark:text-white' : 'text-zinc-400 opacity-0 group-hover:opacity-100'"
        aria-hidden="true"
      />
    </button>
  </th>
</template>
