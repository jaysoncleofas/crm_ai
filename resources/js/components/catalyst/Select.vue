<script setup>
import { computed, inject } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: '' },
  invalid: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const field = inject('catalystField', null)
const describedby = computed(() => field?.describedby())
const isInvalid = computed(() => props.invalid || Boolean(field?.invalid()))
</script>

<template>
  <span
    class="relative block w-full before:absolute before:inset-px before:rounded-[calc(var(--radius-lg)-1px)] before:bg-white before:shadow-sm after:pointer-events-none after:absolute after:inset-0 after:rounded-lg after:ring-inset after:ring-transparent has-[:focus]:after:ring-2 has-[:focus]:after:ring-blue-500 dark:before:hidden"
  >
    <select
      :id="field?.id"
      :value="modelValue"
      :aria-invalid="isInvalid || undefined"
      :aria-describedby="describedby"
      class="relative block w-full appearance-none rounded-lg border py-2.5 pr-10 pl-3.5 text-base/6 text-zinc-950 focus:outline-none sm:py-1.5 sm:pr-9 sm:pl-3 sm:text-sm/6 disabled:cursor-not-allowed disabled:opacity-50 dark:*:bg-zinc-800 dark:text-white"
      :class="
        isInvalid
          ? 'border-red-500 bg-transparent dark:border-red-500 dark:bg-white/5'
          : 'border-zinc-950/10 bg-transparent hover:border-zinc-950/20 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20'
      "
      v-bind="$attrs"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <slot />
    </select>

    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
      <svg class="size-5 stroke-zinc-500 sm:size-4 dark:stroke-zinc-400" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M5.75 10.75 8 13l2.25-2.25M5.75 5.25 8 3l2.25 2.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </span>
  </span>
</template>

<script>
export default { inheritAttrs: false }
</script>
