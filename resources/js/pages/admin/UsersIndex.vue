<script setup>
import { useResourceList } from '@/composables/useResourceList'
import { useAuth } from '@/composables/useAuth'
import { formatDateTime } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataPagination from '@/components/ui/DataPagination.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import SortableTh from '@/components/ui/SortableTh.vue'
import StateBlock from '@/components/ui/StateBlock.vue'

const { user: currentUser } = useAuth()

const { state, query, rows, meta, isEmpty, setPage, setSort, resetFilters } = useResourceList('users', {
  defaultSort: 'name',
  filters: { is_active: '', trashed: '' },
})

const ROLE_TONES = { admin: 'indigo', manager: 'blue', sales_rep: 'green', viewer: 'slate' }
</script>

<template>
  <div class="space-y-4">
    <header>
      <h1 class="text-xl font-semibold text-slate-900">Team</h1>
      <p class="text-sm text-slate-500">Who has access, and what they can do.</p>
    </header>

    <div class="card">
      <div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <SearchInput v-model="state.search" label="team" placeholder="Search name or email…" />

        <select v-model="state.filters.is_active" class="field-input" aria-label="Filter by active state">
          <option value="">All accounts</option>
          <option value="1">Active</option>
          <option value="0">Deactivated</option>
        </select>

        <BaseButton variant="secondary" @click="resetFilters">Clear filters</BaseButton>
      </div>

      <StateBlock v-if="query.isPending.value" variant="loading" title="Loading team…" />

      <StateBlock
        v-else-if="query.isError.value"
        variant="error"
        title="Couldn't load the team"
        :message="query.error.value?.message"
      >
        <BaseButton size="sm" @click="query.refetch()">Try again</BaseButton>
      </StateBlock>

      <StateBlock v-else-if="isEmpty" title="No team members found" message="Try a different search." />

      <template v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <SortableTh field="name" label="Name" :sort="state.sort" @sort="setSort" />
                <th scope="col" class="table-head">Roles</th>
                <th scope="col" class="table-head">Status</th>
                <SortableTh field="last_login_at" label="Last sign-in" :sort="state.sort" @sort="setSort" />
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="person in rows" :key="person.id" class="hover:bg-slate-50">
                <td class="table-cell">
                  <p class="font-medium text-slate-900">
                    {{ person.name }}
                    <span v-if="person.id === currentUser?.id" class="ml-1 text-xs text-slate-400">(you)</span>
                  </p>
                  <p class="text-xs text-slate-500">{{ person.email }}</p>
                </td>
                <td class="table-cell">
                  <span class="flex flex-wrap gap-1">
                    <BaseBadge v-for="role in person.roles ?? []" :key="role" :tone="ROLE_TONES[role] ?? 'slate'">
                      {{ role.replace('_', ' ') }}
                    </BaseBadge>
                  </span>
                </td>
                <td class="table-cell">
                  <BaseBadge :tone="person.is_active ? 'green' : 'red'">
                    {{ person.is_active ? 'Active' : 'Deactivated' }}
                  </BaseBadge>
                </td>
                <td class="table-cell text-slate-500">{{ formatDateTime(person.last_login_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <DataPagination :meta="meta" :fetching="query.isFetching.value" @change="setPage" />
      </template>
    </div>
  </div>
</template>
