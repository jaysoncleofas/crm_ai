<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const TONES = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  info: 'border-slate-200 bg-white text-slate-900',
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end">
    <div
      v-for="toast in toasts"
      :key="toast.id"
      class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg"
      :class="TONES[toast.type] ?? TONES.info"
      role="status"
      aria-live="polite"
    >
      <p class="flex-1 text-sm">{{ toast.message }}</p>
      <button
        type="button"
        class="text-current/60 hover:text-current"
        aria-label="Dismiss notification"
        @click="dismiss(toast.id)"
      >
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path d="M6.3 6.3a1 1 0 011.4 0L10 8.6l2.3-2.3a1 1 0 111.4 1.4L11.4 10l2.3 2.3a1 1 0 01-1.4 1.4L10 11.4l-2.3 2.3a1 1 0 01-1.4-1.4L8.6 10 6.3 7.7a1 1 0 010-1.4z" />
        </svg>
      </button>
    </div>
  </div>
</template>
