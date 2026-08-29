<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import SortableTh from '@/components/ui/SortableTh.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import CompanyFormModal from './CompanyFormModal.vue'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('companies', {
  defaultSort: 'name',
  filters: { trashed: '' },
})

const formOpen = ref(false)
const editing = ref(null)
const pendingDelete = ref(null)

const remove = useMutation({
  mutationFn: (company) => api.delete(`/companies/${company.id}`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['companies'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Company moved to trash.')
    pendingDelete.value = null
  },
})

const restore = useMutation({
  mutationFn: (company) => api.post(`/companies/${company.id}/restore`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['companies'] })
    toast.success('Company restored.')
  },
})
</script>

<template>
  <div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Companies</h1>
        <p class="text-sm text-slate-500">Accounts and the people inside them.</p>
      </div>
      <BaseButton v-if="can('companies.create')" @click="editing = null; formOpen = true">New company</BaseButton>
    </header>

    <div class="card">
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput v-model="state.search" label="companies" placeholder="Search name, domain, industry…" />
        <select v-model="state.filters.trashed" class="field-input" aria-label="Filter by deleted state">
          <option value="">Active only</option>
          <option value="with">Include deleted</option>
          <option value="only">Deleted only</option>
        </select>
        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading companies…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load companies"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock v-else-if="isEmpty" title="No companies found" message="Adjust your filters or add an account.">
        <BaseButton v-if="can('companies.create')" size="sm" @click="editing = null; formOpen = true">New company</BaseButton>
      </StateBlock>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <SortableTh field="name" label="Company" :sort="state.sort" @sort="setSort" />
                <SortableTh field="industry" label="Industry" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Contacts</th>
                <th scope="col" class="table-head">Deals</th>
                <SortableTh field="annual_revenue" label="Revenue" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Owner</th>
                <th scope="col" class="table-head"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="company in rows"
                :key="company.id"
                class="hover:bg-slate-50"
                :class="company.audit.is_deleted ? 'opacity-60' : ''"
              >
                <td class="table-cell">
                  <RouterLink :to="`/companies/${company.id}`" class="font-medium text-indigo-600 hover:underline">
                    {{ company.name }}
                  </RouterLink>
                  <span v-if="company.audit.is_deleted" class="ml-2"><BaseBadge tone="red">Deleted</BaseBadge></span>
                  <p class="text-xs text-slate-500">{{ company.domain ?? '—' }}</p>
                </td>
                <td class="table-cell">{{ company.industry ?? '—' }}</td>
                <td class="table-cell tabular-nums">{{ company.contacts_count ?? 0 }}</td>
                <td class="table-cell tabular-nums">{{ company.deals_count ?? 0 }}</td>
                <td class="table-cell tabular-nums">{{ company.annual_revenue ? formatCurrency(company.annual_revenue) : '—' }}</td>
                <td class="table-cell"><OwnerChip :owner="company.owner" /></td>
                <td class="table-cell text-right">
                  <div class="flex justify-end gap-1">
                    <BaseButton
                      v-if="!company.audit.is_deleted && can('companies.update')"
                      variant="ghost"
                      size="sm"
                      @click="editing = company; formOpen = true"
                    >
                      Edit
                    </BaseButton>
                    <BaseButton
                      v-if="!company.audit.is_deleted && can('companies.delete')"
                      variant="ghost"
                      size="sm"
                      @click="pendingDelete = company"
                    >
                      Delete
                    </BaseButton>
                    <BaseButton
                      v-if="company.audit.is_deleted && can('companies.restore')"
                      variant="ghost"
                      size="sm"
                      :loading="restore.isPending.value"
                      @click="restore.mutate(company)"
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

    <CompanyFormModal :open="formOpen" :company="editing" @close="formOpen = false" />

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Move company to trash?"
      :message="`${pendingDelete?.name} will be soft deleted. Its contacts and deals stay intact.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
