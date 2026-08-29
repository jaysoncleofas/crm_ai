<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: null },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <label class="flex items-center gap-3">
    <span class="relative inline-flex">
      <input
        type="checkbox"
        :checked="modelValue"
        class="peer size-4.5 appearance-none rounded-[0.3125rem] border border-zinc-950/15 bg-white shadow-sm checked:border-transparent checked:bg-zinc-900 focus:outline-2 focus:outline-offset-2 focus:outline-blue-500 sm:size-4 dark:border-white/15 dark:bg-white/5 dark:checked:bg-white"
        @change="emit('update:modelValue', $event.target.checked)"
      />
      <svg
        class="pointer-events-none absolute inset-0 size-4.5 stroke-white opacity-0 peer-checked:opacity-100 sm:size-4 dark:stroke-zinc-900"
        viewBox="0 0 14 14"
        fill="none"
        aria-hidden="true"
      >
        <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </span>
    <span v-if="label" class="text-base/6 text-zinc-950 select-none sm:text-sm/6 dark:text-white">{{ label }}</span>
    <slot />
  </label>
</template>
