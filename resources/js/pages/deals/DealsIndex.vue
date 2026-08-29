<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import SortableTh from '@/components/ui/SortableTh.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import DealFormModal from './DealFormModal.vue'

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

const STATUS_TONES = { open: 'blue', won: 'green', lost: 'red' }
</script>

<template>
  <div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Deals</h1>
        <p class="text-sm text-slate-500">Every opportunity across your pipelines.</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/pipeline">
          <BaseButton variant="secondary">Board view</BaseButton>
        </RouterLink>
        <BaseButton v-if="can('deals.create')" @click="editing = null; formOpen = true">New deal</BaseButton>
      </div>
    </header>

    <div class="card">
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput v-model="state.search" label="deals" placeholder="Search deal name…" />

        <select v-model="state.filters.status" class="field-input" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="won">Won</option>
          <option value="lost">Lost</option>
        </select>

        <select v-model="state.filters.pipeline_id" class="field-input" aria-label="Filter by pipeline">
          <option value="">All pipelines</option>
          <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>

        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading deals…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load deals"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock v-else-if="isEmpty" title="No deals found" message="Adjust your filters or create an opportunity.">
        <BaseButton v-if="can('deals.create')" size="sm" @click="editing = null; formOpen = true">New deal</BaseButton>
      </StateBlock>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <SortableTh field="name" label="Deal" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Stage</th>
                <SortableTh field="amount" label="Amount" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Company</th>
                <th scope="col" class="table-head">Owner</th>
                <SortableTh field="expected_close_date" label="Close date" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="deal in rows"
                :key="deal.id"
                class="hover:bg-slate-50"
                :class="deal.audit.is_deleted ? 'opacity-60' : ''"
              >
                <td class="table-cell">
                  <RouterLink :to="`/deals/${deal.id}`" class="font-medium text-indigo-600 hover:underline">{{ deal.name }}</RouterLink>
                  <span class="ml-2"><BaseBadge :tone="STATUS_TONES[deal.status]">{{ humanize(deal.status) }}</BaseBadge></span>
                  <span v-if="deal.audit.is_deleted" class="ml-1"><BaseBadge tone="red">Deleted</BaseBadge></span>
                </td>
                <td class="table-cell">
                  <BaseBadge v-if="deal.stage" :color="deal.stage.color">{{ deal.stage.name }}</BaseBadge>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="table-cell font-medium tabular-nums">{{ formatCurrency(deal.amount, deal.currency) }}</td>
                <td class="table-cell">
                  <RouterLink v-if="deal.company" :to="`/companies/${deal.company.id}`" class="text-slate-700 hover:underline">
                    {{ deal.company.name }}
                  </RouterLink>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="table-cell"><OwnerChip :owner="deal.owner" /></td>
                <td class="table-cell text-slate-500">{{ formatDate(deal.expected_close_date) }}</td>
                <td class="table-cell text-right">
                  <div class="flex justify-end gap-1">
                    <BaseButton
                      v-if="!deal.audit.is_deleted && can('deals.update')"
                      variant="ghost"
                      size="sm"
                      @click="editing = deal; formOpen = true"
                    >
                      Edit
                    </BaseButton>
                    <BaseButton
                      v-if="!deal.audit.is_deleted && can('deals.delete')"
                      variant="ghost"
                      size="sm"
                      @click="pendingDelete = deal"
                    >
                      Delete
                    </BaseButton>
                    <BaseButton
                      v-if="deal.audit.is_deleted && can('deals.restore')"
                      variant="ghost"
                      size="sm"
                      :loading="restore.isPending.value"
                      @click="restore.mutate(deal)"
                    >
                      Restore
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

    <DealFormModal :open="formOpen" :deal="editing" @close="formOpen = false" />

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Move deal to trash?"
      :message="`${pendingDelete?.name} will be soft deleted and removed from your forecast.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
