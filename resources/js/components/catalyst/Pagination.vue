<script setup>
import { computed } from 'vue'
import Button from './Button.vue'

const props = defineProps({
  meta: { type: Object, default: null },
  fetching: { type: Boolean, default: false },
})
const emit = defineEmits(['change'])

const current = computed(() => props.meta?.current_page ?? 1)
const last = computed(() => props.meta?.last_page ?? 1)
const total = computed(() => props.meta?.total ?? 0)

/** A compact window of pages with ellipses, the way Catalyst renders it. */
const pages = computed(() => {
  const out = []
  const span = 1

  for (let page = 1; page <= last.value; page++) {
    const inWindow = page === 1 || page === last.value || Math.abs(page - current.value) <= span

    if (inWindow) {
      out.push(page)
    } else if (out.at(-1) !== '…') {
      out.push('…')
    }
  }

  return out
})
</script>

<template>
  <nav v-if="meta" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 pt-6">
    <p class="text-sm/6 text-zinc-500 dark:text-zinc-400" aria-live="polite">
      <template v-if="total > 0">
        Showing <span class="font-medium text-zinc-950 dark:text-white">{{ meta.from }}–{{ meta.to }}</span>
        of <span class="font-medium text-zinc-950 dark:text-white">{{ total }}</span>
      </template>
      <template v-else>No results</template>
    </p>

    <div class="flex items-center gap-1">
      <Button plain size="sm" :disabled="current <= 1 || fetching" @click="emit('change', current - 1)">
        <svg class="size-4 stroke-current" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M2.75 8H13.25M2.75 8L6.25 4.75M2.75 8L6.25 11.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Previous
      </Button>

      <span class="hidden items-center gap-1 sm:flex">
        <template v-for="(page, index) in pages" :key="`${page}-${index}`">
          <span v-if="page === '…'" class="px-1 text-sm text-zinc-400" aria-hidden="true">…</span>
          <Button
            v-else
            :plain="page !== current"
            size="sm"
            class="min-w-9 tabular-nums"
            :aria-current="page === current ? 'page' : undefined"
            :aria-label="`Page ${page}`"
            :disabled="fetching"
            @click="emit('change', page)"
          >
            {{ page }}
          </Button>
        </template>
      </span>

      <Button plain size="sm" :disabled="current >= last || fetching" @click="emit('change', current + 1)">
        Next
        <svg class="size-4 stroke-current" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M13.25 8H2.75M13.25 8L9.75 4.75M13.25 8L9.75 11.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </Button>
    </div>
  </nav>
</template>
