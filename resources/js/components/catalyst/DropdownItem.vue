<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  to: { type: [String, Object], default: null },
})

const CLASSES =
  'group flex w-full cursor-pointer items-center gap-3 rounded-lg px-3.5 py-2.5 text-left text-base/6 text-zinc-950 hover:bg-blue-500 hover:text-white focus:bg-blue-500 focus:text-white focus:outline-none sm:px-3 sm:py-1.5 sm:text-sm/6 dark:text-white'
</script>

<template>
  <RouterLink v-if="to" :to="to" :class="CLASSES" role="menuitem"><slot /></RouterLink>
  <button v-else type="button" :class="CLASSES" role="menuitem"><slot /></button>
</template>
