<script setup>
import { computed } from 'vue'

const props = defineProps({
  color: { type: String, default: 'zinc' },
  // A hex from the database (tag / pipeline stage colour) overrides the palette.
  hex: { type: String, default: null },
})

/** Catalyst's badges are a translucent wash of the hue, not a solid fill. */
const COLORS = {
  zinc: 'bg-zinc-600/10 text-zinc-700 dark:bg-white/5 dark:text-zinc-400',
  red: 'bg-red-500/15 text-red-700 dark:bg-red-500/10 dark:text-red-400',
  orange: 'bg-orange-500/15 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
  amber: 'bg-amber-400/20 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
  lime: 'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
  green: 'bg-green-500/15 text-green-700 dark:bg-green-500/10 dark:text-green-400',
  emerald: 'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
  teal: 'bg-teal-500/15 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300',
  cyan: 'bg-cyan-400/20 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300',
  sky: 'bg-sky-500/15 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
  blue: 'bg-blue-500/15 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
  indigo: 'bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
  violet: 'bg-violet-500/15 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
  purple: 'bg-purple-500/15 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',
  fuchsia: 'bg-fuchsia-400/15 text-fuchsia-700 dark:bg-fuchsia-400/10 dark:text-fuchsia-400',
  pink: 'bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400',
  rose: 'bg-rose-400/15 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400',
}

const palette = computed(() => COLORS[props.color] ?? COLORS.zinc)

// Mirror the palette's translucency so database colours sit in the same visual register.
const style = computed(() =>
  props.hex ? { backgroundColor: `${props.hex}26`, color: props.hex } : undefined,
)
</script>

<template>
  <span
    class="inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5"
    :class="hex ? '' : palette"
    :style="style"
  >
    <slot />
  </span>
</template>
