<script setup>
import { computed, inject } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  rows: { type: Number, default: 3 },
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
    <textarea
      :id="field?.id"
      :rows="rows"
      :value="modelValue"
      :aria-invalid="isInvalid || undefined"
      :aria-describedby="describedby"
      class="relative block w-full appearance-none rounded-lg border px-3.5 py-2.5 text-base/6 text-zinc-950 placeholder:text-zinc-500 focus:outline-none sm:px-3 sm:py-1.5 sm:text-sm/6 dark:text-white"
      :class="
        isInvalid
          ? 'border-red-500 bg-transparent dark:border-red-500 dark:bg-white/5'
          : 'border-zinc-950/10 bg-transparent hover:border-zinc-950/20 dark:border-white/10 dark:bg-white/5 dark:hover:border-white/20'
      "
      v-bind="$attrs"
      @input="emit('update:modelValue', $event.target.value)"
    ></textarea>
  </span>
</template>

<script>
export default { inheritAttrs: false }
</script>
