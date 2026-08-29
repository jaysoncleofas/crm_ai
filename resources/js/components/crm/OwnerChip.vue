<script setup>
import { Avatar } from '@/components/catalyst'
import { initials } from '@/lib/format'

defineProps({
  owner: { type: Object, default: null },
})
</script>

<template>
  <span v-if="owner" class="inline-flex items-center gap-2">
    <Avatar :initials="owner.initials ?? initials(owner.name)" :alt="owner.name" size="xs" />
    <span class="truncate text-sm/6 text-zinc-950 dark:text-white">{{ owner.name }}</span>
  </span>
  <span v-else class="text-sm/6 text-zinc-400 dark:text-zinc-500">Unassigned</span>
</template>
