<script setup>
import { CheckCircleIcon, ExclamationTriangleIcon, InformationCircleIcon } from '@heroicons/vue/16/solid'
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const TONES = {
  success: { ring: 'ring-emerald-600/20 dark:ring-emerald-400/20', icon: 'text-emerald-600 dark:text-emerald-400', glyph: CheckCircleIcon },
  error: { ring: 'ring-red-600/20 dark:ring-red-400/20', icon: 'text-red-600 dark:text-red-400', glyph: ExclamationTriangleIcon },
  info: { ring: 'ring-zinc-950/10 dark:ring-white/10', icon: 'text-zinc-500 dark:text-zinc-400', glyph: InformationCircleIcon },
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-0 z-60 flex flex-col items-center gap-2 p-4 sm:items-end">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-white/95 px-4 py-3 shadow-lg ring-1 backdrop-blur-xl dark:bg-zinc-800/95"
        :class="(TONES[toast.type] ?? TONES.info).ring"
        role="status"
        aria-live="polite"
      >
        <component
          :is="(TONES[toast.type] ?? TONES.info).glyph"
          class="mt-0.5 size-4 shrink-0"
          :class="(TONES[toast.type] ?? TONES.info).icon"
          aria-hidden="true"
        />
        <p class="flex-1 text-sm/6 text-zinc-950 dark:text-white">{{ toast.message }}</p>
        <button
          type="button"
          class="rounded text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
          aria-label="Dismiss notification"
          @click="dismiss(toast.id)"
        >
          <svg class="size-4 stroke-current" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M4 4l8 8M12 4l-8 8" stroke-width="1.5" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
