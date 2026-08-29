<script setup>
import { computed } from 'vue'
import BaseButton from './BaseButton.vue'

const props = defineProps({
  meta: { type: Object, default: null },
  fetching: { type: Boolean, default: false },
})
const emit = defineEmits(['change'])

const from = computed(() => props.meta?.from ?? 0)
const to = computed(() => props.meta?.to ?? 0)
const total = computed(() => props.meta?.total ?? 0)
const current = computed(() => props.meta?.current_page ?? 1)
const last = computed(() => props.meta?.last_page ?? 1)
</script>

<template>
  <nav
    v-if="meta"
    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    aria-label="Pagination"
  >
    <p class="text-sm text-slate-600" aria-live="polite">
      <template v-if="total > 0">Showing {{ from }}–{{ to }} of {{ total }}</template>
      <template v-else>No results</template>
    </p>

    <div class="flex items-center gap-2">
      <BaseButton
        variant="secondary"
        size="sm"
        :disabled="current <= 1 || fetching"
        @click="emit('change', current - 1)"
      >
        Previous
      </BaseButton>
      <span class="text-sm text-slate-500">Page {{ current }} of {{ last }}</span>
      <BaseButton
        variant="secondary"
        size="sm"
        :disabled="current >= last || fetching"
        @click="emit('change', current + 1)"
      >
        Next
      </BaseButton>
    </div>
  </nav>
</template>
