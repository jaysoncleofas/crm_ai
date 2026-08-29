<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { PlusIcon } from '@heroicons/vue/16/solid'
import api from '@/lib/api'
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatDate, humanize } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import SearchField from '@/components/crm/SearchField.vue'
import SortHeader from '@/components/crm/SortHeader.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ContactFormModal from './ContactFormModal.vue'
import {
  Alert,
  Badge,
  Button,
  Divider,
  EmptyState,
  Pagination,
  Select,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/catalyst'

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

const STAGE_TONES = {
  customer: 'emerald',
  opportunity: 'violet',
  sales_qualified_lead: 'blue',
  marketing_qualified_lead: 'sky',
  evangelist: 'teal',
  lead: 'zinc',
}

const formOpen = ref(false)
const editing = ref(null)
const pendingDelete = ref(null)

function openCreate() {
  editing.value = null
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
</script>

<template>
  <div>
    <PageHeader title="Contacts" description="People across every account you work with.">
      <template #actions>
        <Button v-if="can('contacts.create')" @click="openCreate">
          <PlusIcon class="size-4" aria-hidden="true" />
          New contact
        </Button>
      </template>
    </PageHeader>

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SearchField v-model="state.search" label="Search contacts" placeholder="Search name, email, phone…" />

      <Select v-model="state.filters.lifecycle_stage" aria-label="Filter by lifecycle stage">
        <option value="">All lifecycle stages</option>
        <option v-for="stage in LIFECYCLE_STAGES" :key="stage" :value="stage">{{ humanize(stage) }}</option>
      </Select>

      <Select v-model="state.filters.trashed" aria-label="Filter by deleted state">
        <option value="">Active only</option>
        <option value="with">Include deleted</option>
        <option value="only">Deleted only</option>
      </Select>

      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading contacts…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load contacts"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState
      v-else-if="isEmpty"
      title="No contacts found"
      message="Try adjusting your search or filters, or add your first contact."
    >
      <Button v-if="can('contacts.create')" @click="openCreate">New contact</Button>
    </EmptyState>

    <template v-else>
      <Table class="mt-4">
        <TableHead>
          <tr>
            <SortHeader field="last_name" label="Name" :sort="state.sort" @sort="setSort" />
            <TableHeader>Company</TableHeader>
            <TableHeader>Stage</TableHeader>
            <SortHeader field="lead_score" label="Score" :sort="state.sort" @sort="setSort" />
            <TableHeader>Owner</TableHeader>
            <SortHeader field="created_at" label="Created" :sort="state.sort" @sort="setSort" />
            <TableHeader class="sticky right-0 bg-white dark:bg-zinc-900"><span class="sr-only">Actions</span></TableHeader>
          </tr>
        </TableHead>

        <TableBody>
          <TableRow v-for="contact in rows" :key="contact.id" clickable :muted="contact.audit.is_deleted">
            <TableCell>
              <div class="flex items-center gap-2">
                <RouterLink
                  :to="`/contacts/${contact.id}`"
                  class="font-medium text-zinc-950 hover:underline dark:text-white"
                >
                  {{ contact.full_name }}
                </RouterLink>
                <Badge v-if="contact.audit.is_deleted" color="red">Deleted</Badge>
              </div>
              <p class="text-xs/5 text-zinc-500 dark:text-zinc-400">{{ contact.email ?? '—' }}</p>
            </TableCell>

            <TableCell>
              <RouterLink
                v-if="contact.company"
                :to="`/companies/${contact.company.id}`"
                class="text-zinc-500 hover:text-zinc-950 hover:underline dark:text-zinc-400 dark:hover:text-white"
              >
                {{ contact.company.name }}
              </RouterLink>
              <span v-else class="text-zinc-400 dark:text-zinc-500">—</span>
            </TableCell>

            <TableCell>
              <Badge :color="STAGE_TONES[contact.lifecycle_stage] ?? 'zinc'">
                {{ humanize(contact.lifecycle_stage) }}
              </Badge>
            </TableCell>

            <TableCell class="tabular-nums">{{ contact.lead_score }}</TableCell>
            <TableCell><OwnerChip :owner="contact.owner" /></TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ formatDate(contact.audit.created_at) }}</TableCell>

            <TableCell class="sticky right-0 bg-white dark:bg-zinc-900">
              <div class="-my-1.5 flex justify-end gap-1">
                <Button
                  v-if="!contact.audit.is_deleted && can('contacts.update')"
                  plain
                  size="sm"
                  @click="editing = contact; formOpen = true"
                >
                  Edit
                </Button>
                <Button
                  v-if="!contact.audit.is_deleted && can('contacts.delete')"
                  plain
                  size="sm"
                  @click="pendingDelete = contact"
                >
                  Delete
                </Button>
                <Button
                  v-if="contact.audit.is_deleted && can('contacts.restore')"
                  plain
                  size="sm"
                  :loading="restore.isPending.value"
                  @click="restore.mutate(contact)"
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

    <ContactFormModal :open="formOpen" :contact="editing" @close="formOpen = false" />

    <Alert
      :open="pendingDelete !== null"
      title="Move contact to trash?"
      :description="`${pendingDelete?.full_name} will be soft deleted. You can restore it later from the deleted filter.`"
      confirm-label="Move to trash"
      :loading="remove.isPending.value"
      @cancel="pendingDelete = null"
      @confirm="remove.mutate(pendingDelete)"
    />
  </div>
</template>
