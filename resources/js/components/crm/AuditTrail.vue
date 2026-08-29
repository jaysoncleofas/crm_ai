<script setup>
import { formatDateTime } from '@/lib/format'

defineProps({
  audit: { type: Object, required: true },
})
</script>

<template>
  <dl class="grid gap-3 text-xs text-slate-500 sm:grid-cols-3">
    <div>
      <dt class="font-semibold uppercase tracking-wide text-slate-400">Created</dt>
      <dd class="mt-0.5 text-slate-700">{{ formatDateTime(audit.created_at) }}</dd>
      <dd v-if="audit.creator" class="text-slate-500">by {{ audit.creator.name }}</dd>
    </div>
    <div>
      <dt class="font-semibold uppercase tracking-wide text-slate-400">Last updated</dt>
      <dd class="mt-0.5 text-slate-700">{{ formatDateTime(audit.updated_at) }}</dd>
      <dd v-if="audit.updater" class="text-slate-500">by {{ audit.updater.name }}</dd>
    </div>
    <div v-if="audit.is_deleted">
      <dt class="font-semibold uppercase tracking-wide text-red-400">Deleted</dt>
      <dd class="mt-0.5 text-red-700">{{ formatDateTime(audit.deleted_at) }}</dd>
      <dd v-if="audit.deleter" class="text-red-500">by {{ audit.deleter.name }}</dd>
    </div>
  </dl>
</template>
