<script setup>
import { ref } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { PlusIcon } from '@heroicons/vue/16/solid'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatDateTime, humanize } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import SearchField from '@/components/crm/SearchField.vue'
import SortHeader from '@/components/crm/SortHeader.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ActivityFormModal from './ActivityFormModal.vue'
import {
  Alert, Badge, Button, Divider, EmptyState, Pagination, Select,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/catalyst'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('activities', {
  defaultSort: '-created_at',
  filters: { type: '', status: '', trashed: '' },
})

const TYPES = ['call', 'email', 'meeting', 'note', 'task']
const STATUS_TONES = { completed: 'emerald', planned: 'blue', canceled: 'zinc' }

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
  <div>
    <PageHeader title="Activities" description="Calls, emails, meetings, notes and tasks.">
      <template #actions>
        <Button v-if="can('activities.create')" @click="editing = null; formOpen = true">
          <PlusIcon class="size-4" aria-hidden="true" />
          Log activity
        </Button>
      </template>
    </PageHeader>

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SearchField v-model="state.search" label="Search activities" placeholder="Search subject or notes…" />

      <Select v-model="state.filters.type" aria-label="Filter by type">
        <option value="">All types</option>
        <option v-for="t in TYPES" :key="t" :value="t">{{ humanize(t) }}</option>
      </Select>

      <Select v-model="state.filters.status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="planned">Planned</option>
        <option value="completed">Completed</option>
        <option value="canceled">Canceled</option>
      </Select>

      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading activities…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load activities"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState v-else-if="isEmpty" title="No activities found" message="Log a call, email or meeting to get started.">
      <Button v-if="can('activities.create')" @click="editing = null; formOpen = true">Log activity</Button>
    </EmptyState>

    <template v-else>
      <Table class="mt-4">
        <TableHead>
          <tr>
            <TableHeader>Subject</TableHeader>
            <SortHeader field="type" label="Type" :sort="state.sort" @sort="setSort" />
            <TableHeader>Status</TableHeader>
            <TableHeader>Related to</TableHeader>
            <TableHeader>Owner</TableHeader>
            <SortHeader field="due_at" label="Due" :sort="state.sort" @sort="setSort" />
            <TableHeader class="sticky right-0 bg-white dark:bg-zinc-900"><span class="sr-only">Actions</span></TableHeader>
          </tr>
        </TableHead>

        <TableBody>
          <TableRow v-for="activity in rows" :key="activity.id" clickable :muted="activity.audit.is_deleted">
            <TableCell>
              <p class="font-medium text-zinc-950 dark:text-white">{{ activity.subject }}</p>
              <p v-if="activity.body" class="max-w-md truncate text-xs/5 text-zinc-500 dark:text-zinc-400">
                {{ activity.body }}
              </p>
            </TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ humanize(activity.type) }}</TableCell>
            <TableCell>
              <div class="flex items-center gap-1.5">
                <Badge :color="STATUS_TONES[activity.status] ?? 'zinc'">{{ humanize(activity.status) }}</Badge>
                <Badge v-if="activity.is_overdue" color="red">Overdue</Badge>
              </div>
            </TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">
              {{ activity.related?.label ?? (activity.related_type ? humanize(activity.related_type) : '—') }}
            </TableCell>
            <TableCell><OwnerChip :owner="activity.owner" /></TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ formatDateTime(activity.due_at) }}</TableCell>
            <TableCell class="sticky right-0 bg-white dark:bg-zinc-900">
              <div class="-my-1.5 flex justify-end gap-1">
                <Button
                  v-if="activity.status === 'planned' && can('activities.update') && !activity.audit.is_deleted"
                  plain size="sm"
                  @click="complete.mutate(activity)"
                >
                  Complete
                </Button>
                <Button
                  v-if="can('activities.update') && !activity.audit.is_deleted"
                  plain size="sm"
                  @click="editing = activity; formOpen = true"
                >
                  Edit
                </Button>
                <Button
                  v-if="can('activities.delete') && !activity.audit.is_deleted"
                  plain size="sm"
                  @click="pendingDelete = activity"
                >
                  Delete
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <Pagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
    </template>

    <ActivityFormModal :open="formOpen" :activity="editing" @close="formOpen = false" />

    <Alert
      :open="pendingDelete !== null"
      title="Move activity to trash?"
      :description="`“${pendingDelete?.subject}” will be soft deleted.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
