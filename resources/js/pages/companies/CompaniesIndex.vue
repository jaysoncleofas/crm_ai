<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { PlusIcon } from '@heroicons/vue/16/solid'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import SearchField from '@/components/crm/SearchField.vue'
import SortHeader from '@/components/crm/SortHeader.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import CompanyFormModal from './CompanyFormModal.vue'
import {
  Alert, Badge, Button, Divider, EmptyState, Pagination, Select,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/catalyst'

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
  <div>
    <PageHeader title="Companies" description="Accounts and the people inside them.">
      <template #actions>
        <Button v-if="can('companies.create')" @click="editing = null; formOpen = true">
          <PlusIcon class="size-4" aria-hidden="true" />
          New company
        </Button>
      </template>
    </PageHeader>

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SearchField v-model="state.search" label="Search companies" placeholder="Search name, domain, industry…" />
      <Select v-model="state.filters.trashed" aria-label="Filter by deleted state">
        <option value="">Active only</option>
        <option value="with">Include deleted</option>
        <option value="only">Deleted only</option>
      </Select>
      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading companies…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load companies"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState v-else-if="isEmpty" title="No companies found" message="Adjust your filters or add an account.">
      <Button v-if="can('companies.create')" @click="editing = null; formOpen = true">New company</Button>
    </EmptyState>

    <template v-else>
      <Table class="mt-4">
        <TableHead>
          <tr>
            <SortHeader field="name" label="Company" :sort="state.sort" @sort="setSort" />
            <SortHeader field="industry" label="Industry" :sort="state.sort" @sort="setSort" />
            <TableHeader>Contacts</TableHeader>
            <TableHeader>Deals</TableHeader>
            <SortHeader field="annual_revenue" label="Revenue" :sort="state.sort" @sort="setSort" />
            <TableHeader>Owner</TableHeader>
            <TableHeader class="sticky right-0 bg-white dark:bg-zinc-900"><span class="sr-only">Actions</span></TableHeader>
          </tr>
        </TableHead>

        <TableBody>
          <TableRow v-for="company in rows" :key="company.id" clickable :muted="company.audit.is_deleted">
            <TableCell>
              <div class="flex items-center gap-2">
                <RouterLink :to="`/companies/${company.id}`" class="font-medium text-zinc-950 hover:underline dark:text-white">
                  {{ company.name }}
                </RouterLink>
                <Badge v-if="company.audit.is_deleted" color="red">Deleted</Badge>
              </div>
              <p class="text-xs/5 text-zinc-500 dark:text-zinc-400">{{ company.domain ?? '—' }}</p>
            </TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ company.industry ?? '—' }}</TableCell>
            <TableCell class="tabular-nums">{{ company.contacts_count ?? 0 }}</TableCell>
            <TableCell class="tabular-nums">{{ company.deals_count ?? 0 }}</TableCell>
            <TableCell class="tabular-nums">
              {{ company.annual_revenue ? formatCurrency(company.annual_revenue) : '—' }}
            </TableCell>
            <TableCell><OwnerChip :owner="company.owner" /></TableCell>
            <TableCell class="sticky right-0 bg-white dark:bg-zinc-900">
              <div class="-my-1.5 flex justify-end gap-1">
                <Button
                  v-if="!company.audit.is_deleted && can('companies.update')"
                  plain size="sm"
                  @click="editing = company; formOpen = true"
                >
                  Edit
                </Button>
                <Button
                  v-if="!company.audit.is_deleted && can('companies.delete')"
                  plain size="sm"
                  @click="pendingDelete = company"
                >
                  Delete
                </Button>
                <Button
                  v-if="company.audit.is_deleted && can('companies.restore')"
                  plain size="sm"
                  :loading="restore.isPending.value"
                  @click="restore.mutate(company)"
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

    <CompanyFormModal :open="formOpen" :company="editing" @close="formOpen = false" />

    <Alert
      :open="pendingDelete !== null"
      title="Move company to trash?"
      :description="`${pendingDelete?.name} will be soft deleted. Its contacts and deals stay intact.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
