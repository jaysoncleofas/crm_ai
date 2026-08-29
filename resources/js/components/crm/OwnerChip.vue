<script setup>
defineProps({
  owner: { type: Object, default: null },
})
</script>

<template>
  <span v-if="owner" class="inline-flex items-center gap-2">
    <span
      class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700"
      aria-hidden="true"
    >
      {{ owner.initials }}
    </span>
    <span class="truncate text-sm text-slate-700">{{ owner.name }}</span>
  </span>
  <span v-else class="text-sm text-slate-400">Unassigned</span>
</template>
