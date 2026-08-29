<script setup>
defineProps({
  clickable: { type: Boolean, default: false },
  muted: { type: Boolean, default: false },
})
</script>

<template>
  <tr
    :class="[
      clickable ? 'hover:bg-zinc-950/2.5 dark:hover:bg-white/2.5' : '',
      muted ? 'opacity-50' : '',
    ]"
  >
    <slot />
  </tr>
</template>
