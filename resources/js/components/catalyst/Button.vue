<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  color: { type: String, default: 'dark/zinc' },
  outline: { type: Boolean, default: false },
  plain: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
  type: { type: String, default: 'button' },
  to: { type: [String, Object], default: null },
  href: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const BASE = [
  'relative isolate inline-flex items-center justify-center gap-x-2 rounded-lg border font-semibold',
  'focus:outline-2 focus:outline-offset-2 focus:outline-blue-500',
  'disabled:opacity-50 disabled:cursor-not-allowed',
  'cursor-pointer transition-colors',
]

const SIZES = {
  sm: 'px-2.5 py-1.5 text-sm/5',
  md: 'px-3.5 py-2.5 text-base/6 sm:px-3 sm:py-1.5 sm:text-sm/6',
}

/**
 * Solid buttons are layered the way Catalyst does it: the border colour sits on
 * the element, a `before` pseudo paints the fill and casts the shadow, and an
 * `after` pseudo lays a 1px white inset highlight over the top. That is what
 * gives them depth instead of looking like flat rectangles.
 */
const SOLID = [
  'border-transparent text-white',
  'before:absolute before:inset-0 before:-z-10 before:rounded-[calc(var(--radius-lg)-1px)] before:shadow-sm',
  'after:absolute after:inset-0 after:-z-10 after:rounded-[calc(var(--radius-lg)-1px)]',
  'after:shadow-[inset_0_1px_theme(colors.white/15%)]',
  'dark:before:hidden dark:after:-inset-px dark:after:rounded-lg',
]

const SOLID_COLORS = {
  'dark/zinc': [
    'bg-zinc-950/90 before:bg-zinc-900 hover:after:bg-white/10',
    'dark:bg-zinc-600 dark:hover:after:bg-white/5',
  ],
  red: ['bg-red-700/90 before:bg-red-600 hover:after:bg-white/10', 'dark:bg-red-600'],
  blue: ['bg-blue-700/90 before:bg-blue-600 hover:after:bg-white/10', 'dark:bg-blue-600'],
  emerald: ['bg-emerald-700/90 before:bg-emerald-600 hover:after:bg-white/10', 'dark:bg-emerald-600'],
}

const OUTLINE = [
  'border-zinc-950/10 text-zinc-950 hover:bg-zinc-950/2.5',
  'dark:border-white/15 dark:text-white dark:hover:bg-white/5',
]

const PLAIN = [
  'border-transparent text-zinc-950 hover:bg-zinc-950/5',
  'dark:text-white dark:hover:bg-white/10',
]

const classes = computed(() => {
  const variant = props.outline ? OUTLINE : props.plain ? PLAIN : [...SOLID, ...(SOLID_COLORS[props.color] ?? SOLID_COLORS['dark/zinc'])]
  return [...BASE, SIZES[props.size] ?? SIZES.md, ...variant]
})

const isDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <RouterLink v-if="to && !isDisabled" :to="to" :class="classes">
    <slot />
  </RouterLink>

  <a v-else-if="href && !isDisabled" :href="href" :class="classes">
    <slot />
  </a>

  <button v-else :type="type" :class="classes" :disabled="isDisabled">
    <svg v-if="loading" class="size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <slot />
  </button>
</template>
