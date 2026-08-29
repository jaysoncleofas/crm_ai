<script setup>
import Button from './Button.vue'
import Dialog from './Dialog.vue'

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  description: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  color: { type: String, default: 'red' },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Dialog :open="open" :title="title" :description="description" size="md" @close="emit('cancel')">
    <template #actions>
      <Button outline :disabled="loading" @click="emit('cancel')">Cancel</Button>
      <Button :color="color" :loading="loading" @click="emit('confirm')">{{ confirmLabel }}</Button>
    </template>
  </Dialog>
</template>
