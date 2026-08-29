<script setup>
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { formatDateTime, initials } from '@/lib/format'
import PageHeader from '@/components/crm/PageHeader.vue'
import SearchField from '@/components/crm/SearchField.vue'
import SortHeader from '@/components/crm/SortHeader.vue'
import {
  Avatar, Badge, Button, Divider, EmptyState, Pagination, Select,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/catalyst'

const { user: currentUser } = useAuth()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('users', {
  defaultSort: 'name',
  filters: { is_active: '', trashed: '' },
})

const ROLE_TONES = { admin: 'violet', manager: 'blue', sales_rep: 'emerald', viewer: 'zinc' }
</script>

<template>
  <div>
    <PageHeader title="Team" description="Who has access, and what they can do." />

    <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SearchField v-model="state.search" label="Search team" placeholder="Search name or email…" />
      <Select v-model="state.filters.is_active" aria-label="Filter by active state">
        <option value="">All accounts</option>
        <option value="1">Active</option>
        <option value="0">Deactivated</option>
      </Select>
      <Button outline @click="resetFilters">Clear filters</Button>
    </div>

    <Divider class="mt-6" />

    <EmptyState v-if="query.isPending.value" variant="loading" title="Loading team…" />

    <EmptyState
      v-else-if="query.isError.value"
      variant="error"
      title="Couldn't load the team"
      :message="query.error.value?.message"
    >
      <Button @click="query.refetch()">Try again</Button>
    </EmptyState>

    <EmptyState v-else-if="isEmpty" title="No team members found" message="Try a different search." />

    <template v-else>
      <Table class="mt-4">
        <TableHead>
          <tr>
            <SortHeader field="name" label="Name" :sort="state.sort" @sort="setSort" />
            <TableHeader>Roles</TableHeader>
            <TableHeader>Status</TableHeader>
            <SortHeader field="last_login_at" label="Last sign-in" :sort="state.sort" @sort="setSort" />
          </tr>
        </TableHead>

        <TableBody>
          <TableRow v-for="person in rows" :key="person.id" clickable>
            <TableCell>
              <div class="flex items-center gap-3">
                <Avatar :initials="initials(person.name)" :alt="person.name" square />
                <div class="min-w-0">
                  <p class="font-medium text-zinc-950 dark:text-white">
                    {{ person.name }}
                    <span v-if="person.id === currentUser?.id" class="ml-1 text-xs/5 text-zinc-400">(you)</span>
                  </p>
                  <p class="text-xs/5 text-zinc-500 dark:text-zinc-400">{{ person.email }}</p>
                </div>
              </div>
            </TableCell>
            <TableCell>
              <div class="flex flex-wrap gap-1">
                <Badge v-for="role in person.roles ?? []" :key="role" :color="ROLE_TONES[role] ?? 'zinc'">
                  {{ role.replace('_', ' ') }}
                </Badge>
              </div>
            </TableCell>
            <TableCell>
              <Badge :color="person.is_active ? 'emerald' : 'red'">
                {{ person.is_active ? 'Active' : 'Deactivated' }}
              </Badge>
            </TableCell>
            <TableCell class="text-zinc-500 dark:text-zinc-400">{{ formatDateTime(person.last_login_at) }}</TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <Pagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
    </template>
  </div>
</template>
