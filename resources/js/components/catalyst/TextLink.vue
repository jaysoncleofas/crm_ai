<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  to: { type: [String, Object], default: null },
  href: { type: String, default: null },
})

const CLASSES =
  'text-zinc-950 underline decoration-zinc-950/50 underline-offset-2 hover:decoration-zinc-950 dark:text-white dark:decoration-white/50 dark:hover:decoration-white'
</script>

<template>
  <RouterLink v-if="to" :to="to" :class="CLASSES"><slot /></RouterLink>
  <a v-else :href="href" :class="CLASSES"><slot /></a>
</template>
