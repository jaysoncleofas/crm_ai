<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  sublabel: { type: String, default: null },
  tone: { type: String, default: 'slate' },
})

const TONES = {
  slate: 'text-slate-900',
  green: 'text-emerald-600',
  amber: 'text-amber-600',
  red: 'text-red-600',
  indigo: 'text-indigo-600',
}
</script>

<template>
  <div class="card p-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ label }}</p>
    <p class="mt-2 text-2xl font-semibold tabular-nums" :class="TONES[tone] ?? TONES.slate">{{ value }}</p>
    <p v-if="sublabel" class="mt-1 text-xs text-slate-500">{{ sublabel }}</p>
  </div>
</template>
