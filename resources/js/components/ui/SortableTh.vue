<script setup>
import { computed } from 'vue'

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

function toggle() {
  emit('sort', direction.value === 'asc' ? `-${props.field}` : props.field)
}
</script>

<template>
  <th scope="col" class="table-head" :aria-sort="ariaSort">
    <button
      type="button"
      class="inline-flex items-center gap-1 font-semibold uppercase tracking-wide hover:text-slate-800"
      @click="toggle"
    >
      {{ label }}
      <span aria-hidden="true" class="text-[10px]">
        {{ direction === 'asc' ? '▲' : direction === 'desc' ? '▼' : '↕' }}
      </span>
    </button>
  </th>
</template>
