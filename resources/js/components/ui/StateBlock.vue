<script setup>
defineProps({
  variant: { type: String, default: 'empty' }, // empty | error | loading
  title: { type: String, default: '' },
  message: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
    <svg v-if="variant === 'loading'" class="size-7 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <div
      v-else
      class="flex size-10 items-center justify-center rounded-full"
      :class="variant === 'error' ? 'bg-red-100 text-red-600' : 'bg-slate-100 text-slate-400'"
      aria-hidden="true"
    >
      <span class="text-lg font-semibold">{{ variant === 'error' ? '!' : '·' }}</span>
    </div>

    <p v-if="title" class="text-sm font-semibold text-slate-900">{{ title }}</p>
    <p v-if="message" class="max-w-sm text-sm text-slate-500">{{ message }}</p>
    <slot />
  </div>
</template>
