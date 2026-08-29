<script setup>
defineProps({
  variant: { type: String, default: 'empty' }, // empty | error | loading
  title: { type: String, default: '' },
  message: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
    <svg
      v-if="variant === 'loading'"
      class="size-6 animate-spin text-zinc-400"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>

    <span
      v-else
      class="flex size-10 items-center justify-center rounded-full"
      :class="
        variant === 'error'
          ? 'bg-red-500/10 text-red-600 dark:text-red-400'
          : 'bg-zinc-950/5 text-zinc-400 dark:bg-white/5'
      "
      aria-hidden="true"
    >
      <svg v-if="variant === 'error'" class="size-5 stroke-current" viewBox="0 0 20 20" fill="none">
        <path d="M10 6.5v4M10 13.5h.01" stroke-width="1.75" stroke-linecap="round" />
        <circle cx="10" cy="10" r="7.25" stroke-width="1.5" />
      </svg>
      <svg v-else class="size-5 stroke-current" viewBox="0 0 20 20" fill="none">
        <path d="M3.5 5.75h13M3.5 10h13M3.5 14.25h8" stroke-width="1.5" stroke-linecap="round" />
      </svg>
    </span>

    <p v-if="title" class="text-sm/6 font-semibold text-zinc-950 dark:text-white">{{ title }}</p>
    <p v-if="message" class="max-w-sm text-sm/6 text-zinc-500 dark:text-zinc-400">{{ message }}</p>
    <div v-if="$slots.default" class="mt-2"><slot /></div>
  </div>
</template>
