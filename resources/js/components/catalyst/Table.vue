<script setup>
import { provide } from 'vue'

const props = defineProps({
  striped: { type: Boolean, default: false },
  dense: { type: Boolean, default: false },
})

provide('catalystTable', props)
</script>

<template>
  <div class="flow-root">
    <!-- Bleeds to the panel edges, then scrolls horizontally on small screens. -->
    <div class="-mx-(--gutter) overflow-x-auto whitespace-nowrap [--gutter:--spacing(6)] lg:[--gutter:--spacing(10)] scrollbar-thin">
      <div class="inline-block min-w-full align-middle sm:px-(--gutter)">
        <table class="min-w-full text-left text-sm/6 text-zinc-950 dark:text-white">
          <slot />
        </table>
      </div>
    </div>
  </div>
</template>
