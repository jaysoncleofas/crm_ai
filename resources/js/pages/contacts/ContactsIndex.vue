<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatDate, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import SortableTh from '@/components/ui/SortableTh.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ContactFormModal from './ContactFormModal.vue'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('contacts', {
  defaultSort: '-created_at',
  filters: { lifecycle_stage: '', lead_status: '', trashed: '' },
})

const LIFECYCLE_STAGES = [
  'subscriber', 'lead', 'marketing_qualified_lead', 'sales_qualified_lead',
  'opportunity', 'customer', 'evangelist', 'other',
]

const formOpen = ref(false)
const editing = ref(null)
const pendingDelete = ref(null)

function openCreate() {
  editing.value = null
  formOpen.value = true
}

function openEdit(contact) {
  editing.value = contact
  formOpen.value = true
}

const remove = useMutation({
  mutationFn: (contact) => api.delete(`/contacts/${contact.id}`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Contact moved to trash.')
    pendingDelete.value = null
  },
})

const restore = useMutation({
  mutationFn: (contact) => api.post(`/contacts/${contact.id}/restore`),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
    toast.success('Contact restored.')
  },
})

const STAGE_TONES = {
  customer: 'green',
  opportunity: 'indigo',
  sales_qualified_lead: 'blue',
  marketing_qualified_lead: 'blue',
  evangelist: 'green',
}
</script>

<template>
  <div class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Contacts</h1>
        <p class="text-sm text-slate-500">People across every account you work with.</p>
      </div>
      <BaseButton v-if="can('contacts.create')" @click="openCreate">New contact</BaseButton>
    </header>

    <div class="card">
      <!-- Filters -->
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput v-model="state.search" label="contacts" placeholder="Search name, email, phone…" />

        <select v-model="state.filters.lifecycle_stage" class="field-input" aria-label="Filter by lifecycle stage">
          <option value="">All lifecycle stages</option>
          <option v-for="stage in LIFECYCLE_STAGES" :key="stage" :value="stage">{{ humanize(stage) }}</option>
        </select>

        <select v-model="state.filters.trashed" class="field-input" aria-label="Filter by deleted state">
          <option value="">Active only</option>
          <option value="with">Include deleted</option>
          <option value="only">Deleted only</option>
        </select>

        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading contacts…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load contacts"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock
        v-else-if="isEmpty"
        title="No contacts found"
        message="Try adjusting your search or filters, or add your first contact."
      >
        <BaseButton v-if="can('contacts.create')" size="sm" @click="openCreate">New contact</BaseButton>
      </StateBlock>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <SortableTh field="last_name" label="Name" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Company</th>
                <th scope="col" class="table-head">Stage</th>
                <SortableTh field="lead_score" label="Score" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Owner</th>
                <SortableTh field="created_at" label="Created" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <tr
                v-for="contact in rows"
                :key="contact.id"
                class="hover:bg-slate-50"
                :class="contact.audit.is_deleted ? 'opacity-60' : ''"
              >
                <td class="table-cell">
                  <RouterLink :to="`/contacts/${contact.id}`" class="font-medium text-indigo-600 hover:underline">
                    {{ contact.full_name }}
                  </RouterLink>
                  <span v-if="contact.audit.is_deleted" class="ml-2"><BaseBadge tone="red">Deleted</BaseBadge></span>
                  <p class="text-xs text-slate-500">{{ contact.email ?? '—' }}</p>
                </td>

                <td class="table-cell">
                  <RouterLink
                    v-if="contact.company"
                    :to="`/companies/${contact.company.id}`"
                    class="text-slate-700 hover:underline"
                  >
                    {{ contact.company.name }}
                  </RouterLink>
                  <span v-else class="text-slate-400">—</span>
                </td>

                <td class="table-cell">
                  <BaseBadge :tone="STAGE_TONES[contact.lifecycle_stage] ?? 'slate'">
                    {{ humanize(contact.lifecycle_stage) }}
                  </BaseBadge>
                </td>

                <td class="table-cell tabular-nums">{{ contact.lead_score }}</td>
                <td class="table-cell"><OwnerChip :owner="contact.owner" /></td>
                <td class="table-cell text-slate-500">{{ formatDate(contact.audit.created_at) }}</td>

                <td class="table-cell text-right">
                  <div class="flex justify-end gap-1">
                    <BaseButton
                      v-if="!contact.audit.is_deleted && can('contacts.update')"
                      variant="ghost"
                      size="sm"
                      @click="openEdit(contact)"
                    >
                      Edit
                    </BaseButton>
                    <BaseButton
                      v-if="!contact.audit.is_deleted && can('contacts.delete')"
                      variant="ghost"
                      size="sm"
                      @click="pendingDelete = contact"
                    >
                      Delete
                    </BaseButton>
                    <BaseButton
                      v-if="contact.audit.is_deleted && can('contacts.restore')"
                      variant="ghost"
                      size="sm"
                      :loading="restore.isPending.value"
                      @click="restore.mutate(contact)"
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

    <ContactFormModal :open="formOpen" :contact="editing" @close="formOpen = false" />

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Move contact to trash?"
      :message="`${pendingDelete?.full_name} will be soft deleted. You can restore it later from the deleted filter.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
