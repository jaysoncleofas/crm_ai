<script setup>
import {
  ChatBubbleLeftEllipsisIcon,
  CheckCircleIcon,
  EnvelopeIcon,
  PhoneIcon,
  UsersIcon,
} from '@heroicons/vue/16/solid'
import { Badge, Text } from '@/components/catalyst'
import { formatDateTime, formatRelative, humanize } from '@/lib/format'

defineProps({
  activities: { type: Array, default: () => [] },
})

const ICONS = {
  call: PhoneIcon,
  email: EnvelopeIcon,
  meeting: UsersIcon,
  note: ChatBubbleLeftEllipsisIcon,
  task: CheckCircleIcon,
}

const TONES = { completed: 'green', planned: 'blue', canceled: 'zinc' }
</script>

<template>
  <ol v-if="activities.length" class="space-y-5">
    <li v-for="activity in activities" :key="activity.id" class="flex gap-3">
      <span
        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-950/5 text-zinc-500 dark:bg-white/5 dark:text-zinc-400"
        aria-hidden="true"
      >
        <component :is="ICONS[activity.type] ?? ChatBubbleLeftEllipsisIcon" class="size-4" />
      </span>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm/6 font-medium text-zinc-950 dark:text-white">{{ activity.subject }}</p>
          <Badge :color="TONES[activity.status] ?? 'zinc'">{{ humanize(activity.status) }}</Badge>
          <Badge v-if="activity.is_overdue" color="red">Overdue</Badge>
        </div>

        <Text v-if="activity.body" class="mt-1 line-clamp-2">{{ activity.body }}</Text>

        <p class="mt-1 text-xs/5 text-zinc-500 dark:text-zinc-400">
          {{ humanize(activity.type) }}
          <template v-if="activity.owner"> · {{ activity.owner.name }}</template>
          <template v-if="activity.due_at">
            · due {{ formatRelative(activity.due_at) }}
            <span class="text-zinc-400 dark:text-zinc-500">({{ formatDateTime(activity.due_at) }})</span>
          </template>
        </p>
      </div>
    </li>
  </ol>

  <p v-else class="py-6 text-center text-sm/6 text-zinc-500 dark:text-zinc-400">No activity recorded yet.</p>
</template>
