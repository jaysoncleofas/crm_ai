<script setup>
import { formatDateTime } from '@/lib/format'
import { DescriptionDetails, DescriptionList, DescriptionTerm } from '@/components/catalyst'

defineProps({
  audit: { type: Object, required: true },
})
</script>

<template>
  <DescriptionList>
    <DescriptionTerm>Created</DescriptionTerm>
    <DescriptionDetails>
      {{ formatDateTime(audit.created_at) }}
      <span v-if="audit.creator" class="text-zinc-500 dark:text-zinc-400">· by {{ audit.creator.name }}</span>
    </DescriptionDetails>

    <DescriptionTerm>Last updated</DescriptionTerm>
    <DescriptionDetails>
      {{ formatDateTime(audit.updated_at) }}
      <span v-if="audit.updater" class="text-zinc-500 dark:text-zinc-400">· by {{ audit.updater.name }}</span>
    </DescriptionDetails>

    <template v-if="audit.is_deleted">
      <DescriptionTerm>Deleted</DescriptionTerm>
      <DescriptionDetails>
        <span class="text-red-600 dark:text-red-400">{{ formatDateTime(audit.deleted_at) }}</span>
        <span v-if="audit.deleter" class="text-zinc-500 dark:text-zinc-400">· by {{ audit.deleter.name }}</span>
      </DescriptionDetails>
    </template>
  </DescriptionList>
</template>
