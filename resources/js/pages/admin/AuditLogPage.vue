<script setup>
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { formatDateTime, humanize } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import { Badge, Button, Divider, EmptyState, Pagination, Select } from '@/components/catalyst'

const { state, query, rows, meta, isEmpty, setPage, resetFilters } = useResourceList('audit-log', {
  defaultSort: '-created_at',
  filters: { log_name: '', event: '', causer_id: '' },
  perPage: 40,
})

const { data: users } = useQuery({
  queryKey: ['users', 'options'],
  queryFn: async () => (await api.get('/users', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: 600_000,
})

const LOGS = ['contacts', 'companies', 'deals', 'activities', 'pipelines', 'pipeline_stages', 'tags', 'users', 'auth']
const EVENT_TONES = { created: 'emerald', updated: 'blue', deleted: 'red', restored: 'amber' }

/** Flatten {attributes, old} into per-field before/after rows. */
function diffRows(entry) {
  const next = entry.changes?.attributes ?? {}
  const prev = entry.changes?.old ?? {}
  return Object.keys(next).map((key) => ({ key, from: prev[key] ?? null, to: next[key] ?? null }))
}

function display(value) {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
</script>

<template>
  <div>
    <PageHeader title="Audit log" description="Every create, update, delete and restore across the CRM." />

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Select v-model="state.filters.log_name" aria-label="Filter by record type">
        <option value="">All record types</option>
        <option v-for="log in LOGS" :key="log" :value="log">{{ humanize(log) }}</option>
      </Select>

      <Select v-model="state.filters.event" aria-label="Filter by event">
        <option value="">All events</option>
        <option value="created">Created</option>
        <option value="updated">Updated</option>
        <option value="deleted">Deleted</option>
        <option value="restored">Restored</option>
      </Select>

      <Select v-model="state.filters.causer_id" aria-label="Filter by user">
        <option value="">Anyone</option>
        <option v-for="u in users ?? []" :key="u.id" :value="u.id">{{ u.name }}</option>
      </Select>

      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading audit log…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load the audit log"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState v-else-if="isEmpty" title="Nothing logged yet" message="Changes to CRM records will appear here." />

    <template v-else>
      <ul class="mt-2 divide-y divide-zinc-950/5 dark:divide-white/5">
        <li v-for="entry in rows" :key="entry.id" class="py-5">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :color="EVENT_TONES[entry.event] ?? 'zinc'">{{ humanize(entry.event) }}</Badge>
            <span class="text-sm/6 font-medium text-zinc-950 dark:text-white">{{ entry.description }}</span>
            <span v-if="entry.subject_type" class="text-xs/5 text-zinc-400 dark:text-zinc-500">
              {{ humanize(entry.subject_type) }} #{{ entry.subject_id }}
            </span>
          </div>

          <p class="mt-1 text-xs/5 text-zinc-500 dark:text-zinc-400">
            {{ entry.causer?.name ?? 'System' }} · {{ formatDateTime(entry.created_at) }}
          </p>

          <div v-if="diffRows(entry).length" class="mt-3 overflow-x-auto rounded-lg bg-zinc-950/2.5 p-3 dark:bg-white/5">
            <table class="min-w-full text-xs/5">
              <thead>
                <tr class="text-left text-zinc-500 dark:text-zinc-400">
                  <th scope="col" class="py-1 pr-4 font-medium">Field</th>
                  <th scope="col" class="py-1 pr-4 font-medium">From</th>
                  <th scope="col" class="py-1 font-medium">To</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in diffRows(entry)" :key="row.key" class="align-top">
                  <td class="py-0.5 pr-4 font-medium text-zinc-700 dark:text-zinc-300">{{ humanize(row.key) }}</td>
                  <td class="py-0.5 pr-4 text-zinc-400 line-through dark:text-zinc-500">{{ display(row.from) }}</td>
                  <td class="py-0.5 text-zinc-950 dark:text-white">{{ display(row.to) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </li>
      </ul>

      <Pagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
    </template>
  </div>
</template>
