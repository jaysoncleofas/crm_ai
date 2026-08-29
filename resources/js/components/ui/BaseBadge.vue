<script setup>
import { computed } from 'vue'

const props = defineProps({
  tone: { type: String, default: 'slate' },
  color: { type: String, default: null },
})

const TONES = {
  slate: 'bg-slate-100 text-slate-700',
  green: 'bg-emerald-100 text-emerald-800',
  red: 'bg-red-100 text-red-800',
  amber: 'bg-amber-100 text-amber-800',
  blue: 'bg-blue-100 text-blue-800',
  indigo: 'bg-indigo-100 text-indigo-800',
}

const classes = computed(() => TONES[props.tone] ?? TONES.slate)
// A tag's own colour wins over the tone palette.
const style = computed(() =>
  props.color ? { backgroundColor: `${props.color}1a`, color: props.color } : undefined,
)
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
    :class="color ? '' : classes"
    :style="style"
  >
    <slot />
  </span>
</template>
