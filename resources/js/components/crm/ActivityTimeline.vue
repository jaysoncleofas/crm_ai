<script setup>
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { formatDateTime, formatRelative, humanize } from '@/lib/format'

defineProps({
  activities: { type: Array, default: () => [] },
})

const ICONS = { call: '📞', email: '✉️', meeting: '📅', note: '📝', task: '✅' }
const TONES = { completed: 'green', planned: 'blue', canceled: 'slate' }
</script>

<template>
  <ol v-if="activities.length" class="space-y-4">
    <li v-for="activity in activities" :key="activity.id" class="flex gap-3">
      <span
        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm"
        aria-hidden="true"
      >
        {{ ICONS[activity.type] ?? '•' }}
      </span>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm font-medium text-slate-900">{{ activity.subject }}</p>
          <BaseBadge :tone="TONES[activity.status] ?? 'slate'">{{ humanize(activity.status) }}</BaseBadge>
          <BaseBadge v-if="activity.is_overdue" tone="red">Overdue</BaseBadge>
        </div>

        <p v-if="activity.body" class="mt-1 text-sm text-slate-600">{{ activity.body }}</p>

        <p class="mt-1 text-xs text-slate-500">
          {{ humanize(activity.type) }}
          <template v-if="activity.owner"> · {{ activity.owner.name }}</template>
          <template v-if="activity.due_at">
            · due {{ formatRelative(activity.due_at) }}
            <span class="text-slate-400">({{ formatDateTime(activity.due_at) }})</span>
          </template>
        </p>
      </div>
    </li>
  </ol>

  <p v-else class="py-6 text-center text-sm text-slate-500">No activity recorded yet.</p>
</template>
