<script setup>
import { useId } from 'vue'

defineProps({
  label: { type: String, required: true },
  error: { type: String, default: null },
  hint: { type: String, default: null },
  required: { type: Boolean, default: false },
})

const id = useId()
</script>

<template>
  <div>
    <label :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-red-500" aria-hidden="true">*</span>
    </label>

    <slot :id="id" :invalid="Boolean(error)" :describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined" />

    <p v-if="hint && !error" :id="`${id}-hint`" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" class="field-error" role="alert">{{ error }}</p>
  </div>
</template>
