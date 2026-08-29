<script setup>
import { provide, useId } from 'vue'

const props = defineProps({
  label: { type: String, default: null },
  description: { type: String, default: null },
  error: { type: String, default: null },
  required: { type: Boolean, default: false },
})

const id = useId()
const descriptionId = `${id}-description`
const errorId = `${id}-error`

// Children (Input/Select/Textarea) wire themselves up to the label and
// messages, so callers never hand-roll aria attributes.
provide('catalystField', {
  id,
  describedby: () => [props.description ? descriptionId : null, props.error ? errorId : null].filter(Boolean).join(' ') || undefined,
  invalid: () => Boolean(props.error),
})
</script>

<template>
  <div class="space-y-2">
    <!--
      The required marker sits outside <label> on purpose: anything inside it
      becomes part of the control's accessible name ("Password *"). The input's
      own `required` attribute is what assistive tech announces.
    -->
    <div v-if="label" class="flex items-center gap-1">
      <label :for="id" class="block text-base/6 font-medium text-zinc-950 sm:text-sm/6 dark:text-white">
        {{ label }}
      </label>
      <span v-if="required" class="text-base/6 text-red-600 sm:text-sm/6 dark:text-red-400" aria-hidden="true">*</span>
    </div>

    <p v-if="description && !error" :id="descriptionId" class="text-base/6 text-zinc-500 sm:text-sm/6 dark:text-zinc-400">
      {{ description }}
    </p>

    <slot :id="id" />

    <p v-if="error" :id="errorId" class="text-base/6 text-red-600 sm:text-sm/6 dark:text-red-500" role="alert">
      {{ error }}
    </p>
  </div>
</template>
