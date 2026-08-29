<script setup>
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { formatDateTime, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import StateBlock from '@/components/ui/StateBlock.vue'

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
const EVENT_TONES = { created: 'green', updated: 'blue', deleted: 'red', restored: 'amber' }

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
  <div class="space-y-4">
    <header>
      <h1 class="text-xl font-semibold text-slate-900">Audit log</h1>
      <p class="text-sm text-slate-500">Every create, update, delete and restore across the CRM.</p>
    </header>

    <div class="card">
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <select v-model="state.filters.log_name" class="field-input" aria-label="Filter by record type">
          <option value="">All record types</option>
          <option v-for="log in LOGS" :key="log" :value="log">{{ humanize(log) }}</option>
        </select>

        <select v-model="state.filters.event" class="field-input" aria-label="Filter by event">
          <option value="">All events</option>
          <option value="created">Created</option>
          <option value="updated">Updated</option>
          <option value="deleted">Deleted</option>
          <option value="restored">Restored</option>
        </select>

        <select v-model="state.filters.causer_id" class="field-input" aria-label="Filter by user">
          <option value="">Anyone</option>
          <option v-for="u in users ?? []" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>

        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading audit log…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load the audit log"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock v-else-if="isEmpty" title="Nothing logged yet" message="Changes to CRM records will appear here." />

      <template v-else>
        <ul class="divide-y divide-slate-100">
          <li v-for="entry in rows" :key="entry.id" class="p-4">
            <div class="flex flex-wrap items-center gap-2">
              <BaseBadge :tone="EVENT_TONES[entry.event] ?? 'slate'">{{ humanize(entry.event) }}</BaseBadge>
              <span class="text-sm font-medium text-slate-900">{{ entry.description }}</span>
              <!-- Auth entries have no subject record, so the chip is omitted. -->
              <span v-if="entry.subject_type" class="text-xs text-slate-400">
                {{ humanize(entry.subject_type) }} #{{ entry.subject_id }}
              </span>
            </div>

            <p class="mt-1 text-xs text-slate-500">
              {{ entry.causer?.name ?? 'System' }} · {{ formatDateTime(entry.created_at) }}
            </p>

            <div v-if="diffRows(entry).length" class="mt-2 overflow-x-auto">
              <table class="min-w-full text-xs">
                <thead>
                  <tr class="text-left text-slate-400">
                    <th scope="col" class="py-1 pr-4 font-medium">Field</th>
                    <th scope="col" class="py-1 pr-4 font-medium">From</th>
                    <th scope="col" class="py-1 font-medium">To</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in diffRows(entry)" :key="row.key" class="align-top">
                    <td class="py-0.5 pr-4 font-medium text-slate-600">{{ humanize(row.key) }}</td>
                    <td class="py-0.5 pr-4 text-slate-400 line-through">{{ display(row.from) }}</td>
                    <td class="py-0.5 text-slate-800">{{ display(row.to) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </li>
        </ul>

        <DataPagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
      </template>
    </div>
  </div>
</template>
