<script setup>
import { computed } from 'vue'

const props = defineProps({
  initials: { type: String, default: null },
  src: { type: String, default: null },
  alt: { type: String, default: '' },
  square: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})

const SIZES = { xs: 'size-6 text-[0.6rem]', sm: 'size-7 text-xs', md: 'size-8 text-xs', lg: 'size-10 text-sm' }

const shape = computed(() => (props.square ? 'rounded-[20%]' : 'rounded-full'))
</script>

<template>
  <span
    class="inline-grid shrink-0 place-items-center bg-zinc-900 align-middle font-medium text-white outline -outline-offset-1 outline-black/10 dark:bg-white dark:text-zinc-900 dark:outline-white/10"
    :class="[SIZES[size] ?? SIZES.md, shape]"
  >
    <img v-if="src" :src="src" :alt="alt" class="size-full object-cover" :class="shape" />
    <span v-else aria-hidden="true" class="select-none">{{ initials }}</span>
    <span v-if="alt" class="sr-only">{{ alt }}</span>
  </span>
</template>
