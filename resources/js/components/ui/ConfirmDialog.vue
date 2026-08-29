<script setup>
import BaseButton from './BaseButton.vue'
import BaseModal from './BaseModal.vue'

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  variant: { type: String, default: 'danger' },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <BaseModal :open="open" :title="title" size="sm" @close="emit('cancel')">
    <p class="text-sm text-slate-600">{{ message }}</p>

    <template #footer>
      <BaseButton variant="secondary" :disabled="loading" @click="emit('cancel')">Cancel</BaseButton>
      <BaseButton :variant="variant" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</BaseButton>
    </template>
  </BaseModal>
</template>
