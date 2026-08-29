<script setup>
defineProps({
  level: { type: Number, default: 1 },
})
</script>

<template>
  <component
    :is="`h${level}`"
    class="text-2xl/8 font-semibold text-zinc-950 sm:text-xl/8 dark:text-white"
  >
    <slot />
  </component>
</template>
