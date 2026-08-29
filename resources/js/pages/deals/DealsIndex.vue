<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { PlusIcon } from '@heroicons/vue/16/solid'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate, humanize } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import SearchField from '@/components/crm/SearchField.vue'
import SortHeader from '@/components/crm/SortHeader.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import DealFormModal from './DealFormModal.vue'
import {
  Alert, Badge, Button, Divider, EmptyState, Pagination, Select,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/catalyst'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('deals', {
  defaultSort: '-created_at',
  filters: { status: '', pipeline_id: '', trashed: '' },
})

const { data: pipelines } = useQuery({
  queryKey: ['pipelines'],
  queryFn: async () => (await api.get('/pipelines')).data.data,
  staleTime: STALE.reference,
})

const formOpen = ref(false)
const editing = ref(null)
const pendingDelete = ref(null)

const remove = useMutation({
  mutationFn: (deal) => api.delete(`/deals/${deal.id}`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['deals'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Deal moved to trash.')
    pendingDelete.value = null
  },
})

const restore = useMutation({
  mutationFn: (deal) => api.post(`/deals/${deal.id}/restore`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['deals'] })
    toast.success('Deal restored.')
  },
})

const STATUS_TONES = { open: 'blue', won: 'emerald', lost: 'red' }
</script>

<template>
  <div>
    <PageHeader title="Deals" description="Every opportunity across your pipelines.">
      <template #actions>
        <Button outline to="/pipeline">Board view</Button>
        <Button v-if="can('deals.create')" @click="editing = null; formOpen = true">
          <PlusIcon class="size-4" aria-hidden="true" />
          New deal
        </Button>
      </template>
    </PageHeader>

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SearchField v-model="state.search" label="Search deals" placeholder="Search deal name…" />

      <Select v-model="state.filters.status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <option value="open">Open</option>
        <option value="won">Won</option>
        <option value="lost">Lost</option>
      </Select>

      <Select v-model="state.filters.pipeline_id" aria-label="Filter by pipeline">
        <option value="">All pipelines</option>
        <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
      </Select>

      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading deals…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load deals"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState v-else-if="isEmpty" title="No deals found" message="Adjust your filters or create an opportunity.">
      <Button v-if="can('deals.create')" @click="editing = null; formOpen = true">New deal</Button>
    </EmptyState>

    <template v-else>
      <Table class="mt-4">
        <TableHead>
          <tr>
            <SortHeader field="name" label="Deal" :sort="state.sort" @sort="setSort" />
            <TableHeader>Stage</TableHeader>
            <SortHeader field="amount" label="Amount" :sort="state.sort" @sort="setSort" />
            <TableHeader>Company</TableHeader>
            <TableHeader>Owner</TableHeader>
            <SortHeader field="expected_close_date" label="Close date" :sort="state.sort" @sort="setSort" />
            <TableHeader class="sticky right-0 bg-white dark:bg-zinc-900"><span class="sr-only">Actions</span></TableHeader>
          </tr>
        </TableHead>

        <TableBody>
          <TableRow v-for="deal in rows" :key="deal.id" clickable :muted="deal.audit.is_deleted">
            <TableCell>
              <div class="flex items-center gap-2">
                <RouterLink :to="`/deals/${deal.id}`" class="font-medium text-zinc-950 hover:underline dark:text-white">
                  {{ deal.name }}
                </RouterLink>
                <Badge :color="STATUS_TONES[deal.status]">{{ humanize(deal.status) }}</Badge>
                <Badge v-if="deal.audit.is_deleted" color="red">Deleted</Badge>
              </div>
            </TableCell>
            <TableCell>
              <Badge v-if="deal.stage" :hex="deal.stage.color">{{ deal.stage.name }}</Badge>
              <span v-else class="text-zinc-400 dark:text-zinc-500">—</span>
            </TableCell>
            <TableCell class="font-medium tabular-nums">{{ formatCurrency(deal.amount, deal.currency) }}</TableCell>
            <TableCell>
              <RouterLink
                v-if="deal.company"
                :to="`/companies/${deal.company.id}`"
                class="text-zinc-500 hover:text-zinc-950 hover:underline dark:text-zinc-400 dark:hover:text-white"
              >
                {{ deal.company.name }}
              </RouterLink>
              <span v-else class="text-zinc-400 dark:text-zinc-500">—</span>
            </TableCell>
            <TableCell><OwnerChip :owner="deal.owner" /></TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ formatDate(deal.expected_close_date) }}</TableCell>
            <TableCell class="sticky right-0 bg-white dark:bg-zinc-900">
              <div class="-my-1.5 flex justify-end gap-1">
                <Button
                  v-if="!deal.audit.is_deleted && can('deals.update')"
                  plain size="sm"
                  @click="editing = deal; formOpen = true"
                >
                  Edit
                </Button>
                <Button
                  v-if="!deal.audit.is_deleted && can('deals.delete')"
                  plain size="sm"
                  @click="pendingDelete = deal"
                >
                  Delete
                </Button>
                <Button
                  v-if="deal.audit.is_deleted && can('deals.restore')"
                  plain size="sm"
                  :loading="restore.isPending.value"
                  @click="restore.mutate(deal)"
                >
                  Restore
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <Pagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
    </template>

    <DealFormModal :open="formOpen" :deal="editing" @close="formOpen = false" />

    <Alert
      :open="pendingDelete !== null"
      title="Move deal to trash?"
      :description="`${pendingDelete?.name} will be soft deleted and removed from your forecast.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
