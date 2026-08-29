<script setup>
defineProps({
  level: { type: Number, default: 2 },
})
</script>

<template>
  <component
    :is="`h${level}`"
    class="text-base/7 font-semibold text-zinc-950 sm:text-sm/6 dark:text-white"
  >
    <slot />
  </component>
</template>
