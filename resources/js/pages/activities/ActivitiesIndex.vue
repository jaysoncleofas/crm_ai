<script setup>
import { ref } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatDateTime, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import SortableTh from '@/components/ui/SortableTh.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ActivityFormModal from './ActivityFormModal.vue'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('activities', {
  defaultSort: '-created_at',
  filters: { type: '', status: '', trashed: '' },
})

const TYPES = ['call', 'email', 'meeting', 'note', 'task']
const STATUS_TONES = { completed: 'green', planned: 'blue', canceled: 'slate' }

const formOpen = ref(false)
const editing = ref(null)
const pendingDelete = ref(null)

const remove = useMutation({
  mutationFn: (activity) => api.delete(`/activities/${activity.id}`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['activities'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Activity moved to trash.')
    pendingDelete.value = null
  },
})

const complete = useMutation({
  mutationFn: (activity) => api.patch(`/activities/${activity.id}`, { status: 'completed' }),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['activities'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Marked complete.')
  },
})
</script>

<template>
  <div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Activities</h1>
        <p class="text-sm text-slate-500">Calls, emails, meetings, notes and tasks.</p>
      </div>
      <BaseButton v-if="can('activities.create')" @click="editing = null; formOpen = true">Log activity</BaseButton>
    </header>

    <div class="card">
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput v-model="state.search" label="activities" placeholder="Search subject or notes…" />

        <select v-model="state.filters.type" class="field-input" aria-label="Filter by type">
          <option value="">All types</option>
          <option v-for="t in TYPES" :key="t" :value="t">{{ humanize(t) }}</option>
        </select>

        <select v-model="state.filters.status" class="field-input" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="planned">Planned</option>
          <option value="completed">Completed</option>
          <option value="canceled">Canceled</option>
        </select>

        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading activities…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load activities"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock v-else-if="isEmpty" title="No activities found" message="Log a call, email or meeting to get started.">
        <BaseButton v-if="can('activities.create')" size="sm" @click="editing = null; formOpen = true">Log activity</BaseButton>
      </StateBlock>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th scope="col" class="table-head">Subject</th>
                <SortableTh field="type" label="Type" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Status</th>
                <th scope="col" class="table-head">Related to</th>
                <th scope="col" class="table-head">Owner</th>
                <SortableTh field="due_at" label="Due" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="activity in rows" :key="activity.id" class="hover:bg-slate-50" :class="activity.audit.is_deleted ? 'opacity-60' : ''">
                <td class="table-cell">
                  <p class="font-medium text-slate-900">{{ activity.subject }}</p>
                  <p v-if="activity.body" class="max-w-md truncate text-xs text-slate-500">{{ activity.body }}</p>
                </td>
                <td class="table-cell">{{ humanize(activity.type) }}</td>
                <td class="table-cell">
                  <BaseBadge :tone="STATUS_TONES[activity.status] ?? 'slate'">{{ humanize(activity.status) }}</BaseBadge>
                  <BaseBadge v-if="activity.is_overdue" tone="red" class="ml-1">Overdue</BaseBadge>
                </td>
                <td class="table-cell">
                  <span v-if="activity.related" class="text-slate-700">{{ activity.related.label ?? humanize(activity.related_type) }}</span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="table-cell"><OwnerChip :owner="activity.owner" /></td>
                <td class="table-cell text-slate-500">{{ formatDateTime(activity.due_at) }}</td>
                <td class="table-cell text-right">
                  <div class="flex justify-end gap-1">
                    <BaseButton
                      v-if="activity.status === 'planned' && can('activities.update') && !activity.audit.is_deleted"
                      variant="ghost"
                      size="sm"
                      @click="complete.mutate(activity)"
                    >
                      Complete
                    </BaseButton>
                    <BaseButton
                      v-if="can('activities.update') && !activity.audit.is_deleted"
                      variant="ghost"
                      size="sm"
                      @click="editing = activity; formOpen = true"
                    >
                      Edit
                    </BaseButton>
                    <BaseButton
                      v-if="can('activities.delete') && !activity.audit.is_deleted"
                      variant="ghost"
                      size="sm"
                      @click="pendingDelete = activity"
                    >
                      Delete
                    </BaseButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DataPagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
      </template>
    </div>

    <ActivityFormModal :open="formOpen" :activity="editing" @close="formOpen = false" />

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Move activity to trash?"
      :message="`“${pendingDelete?.subject}” will be soft deleted.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
