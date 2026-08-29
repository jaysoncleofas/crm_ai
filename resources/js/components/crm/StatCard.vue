<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  sublabel: { type: String, default: null },
  accent: { type: Boolean, default: false },
})
</script>

<template>
  <div class="rounded-xl border border-zinc-950/5 p-5 dark:border-white/10">
    <p class="text-sm/6 text-zinc-500 dark:text-zinc-400">{{ label }}</p>
    <p
      class="mt-2 text-2xl/8 font-semibold tabular-nums"
      :class="accent ? 'text-zinc-950 dark:text-white' : 'text-zinc-950 dark:text-white'"
    >
      {{ value }}
    </p>
    <p v-if="sublabel" class="mt-1 text-xs/5 text-zinc-500 dark:text-zinc-400">{{ sublabel }}</p>
  </div>
</template>
