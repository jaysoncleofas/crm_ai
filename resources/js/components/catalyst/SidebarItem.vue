<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  to: { type: [String, Object], default: null },
  current: { type: Boolean, default: false },
})

const BASE =
  'relative flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left text-base/6 font-medium text-zinc-950 sm:py-2 sm:text-sm/5 dark:text-white'
const HOVER = 'hover:bg-zinc-950/5 dark:hover:bg-white/5'
const CURRENT = 'bg-zinc-950/5 dark:bg-white/5'
</script>

<template>
  <span class="relative">
    <!-- The current item gets a short accent bar on the leading edge. -->
    <span
      v-if="current"
      class="absolute inset-y-2 -left-4 w-0.5 rounded-full bg-zinc-950 dark:bg-white"
      aria-hidden="true"
    />
    <RouterLink
      v-if="to"
      :to="to"
      :class="[BASE, HOVER, current ? CURRENT : '']"
      :aria-current="current ? 'page' : undefined"
    >
      <slot />
    </RouterLink>
    <button v-else type="button" :class="[BASE, HOVER, current ? CURRENT : '']">
      <slot />
    </button>
  </span>
</template>
