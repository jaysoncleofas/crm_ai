<script setup>
import { Heading, Text } from '@/components/catalyst'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: null },
})
</script>

<template>
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
      <Heading>{{ title }}</Heading>
      <Text v-if="description" class="mt-1">{{ description }}</Text>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 gap-2"><slot name="actions" /></div>
  </div>
</template>
