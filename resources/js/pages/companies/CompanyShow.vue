<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { Badge, Button, Divider, EmptyState, Heading, Subheading, Text } from '@/components/catalyst'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, humanize } from '@/lib/format'
import AuditTrail from '@/components/crm/AuditTrail.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue'
import CompanyFormModal from './CompanyFormModal.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })
const { can } = useAuth()
const formOpen = ref(false)

const companyQuery = useQuery({
  queryKey: ['companies', computed(() => Number(props.id))],
  queryFn: async ({ signal }) => (await api.get(`/companies/${props.id}`, { signal })).data.data,
  staleTime: STALE.detail,
})

const contactsQuery = useQuery({
  queryKey: ['contacts', computed(() => ({ company: Number(props.id) }))],
  queryFn: async ({ signal }) =>
    (await api.get('/contacts', { params: { 'filter[company_id]': props.id, per_page: 50, sort: 'last_name' }, signal })).data.data,
  staleTime: STALE.list,
})

const dealsQuery = useQuery({
  queryKey: ['deals', computed(() => ({ company: Number(props.id) }))],
  queryFn: async ({ signal }) =>
    (await api.get('/deals', { params: { 'filter[company_id]': props.id, per_page: 50, sort: '-amount' }, signal })).data.data,
  staleTime: STALE.list,
})

const activitiesQuery = useQuery({
  queryKey: ['activities', computed(() => ({ related: 'company', id: Number(props.id) }))],
  queryFn: async ({ signal }) =>
    (
      await api.get('/activities', {
        params: { 'filter[related_type]': 'company', 'filter[related_id]': props.id, per_page: 20, sort: '-created_at' },
        signal,
      })
    ).data.data,
  staleTime: STALE.list,
})

const company = computed(() => companyQuery.data.value)
const address = computed(() => {
  const a = company.value?.address ?? {}
  return [a.line1, a.city, a.state, a.postal_code, a.country].filter(Boolean).join(', ') || '—'
})
</script>

<template>
  <div class="space-y-5">
    <EmptyState v-if="companyQuery.isPending.value" variant="loading" title="Loading company…" />

    <EmptyState
      v-else-if="companyQuery.isError.value"
      variant="error"
      title="Couldn't load this company"
      :message="companyQuery.error.value?.message"
    >
      <RouterLink to="/companies" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">Back to companies</RouterLink>
    </EmptyState>

    <template v-else-if="company">
      <nav class="text-sm text-zinc-500 dark:text-zinc-400">
        <RouterLink to="/companies" class="hover:underline">Companies</RouterLink>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-700 dark:text-zinc-300">{{ company.name }}</span>
      </nav>

      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ company.name }}</h1>
            <Badge v-if="company.industry">{{ company.industry }}</Badge>
            <Badge v-if="company.audit.is_deleted" color="red">Deleted</Badge>
          </div>
          <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            <a v-if="company.website" :href="company.website" target="_blank" rel="noopener noreferrer" class="text-zinc-950 dark:text-white hover:underline">
              {{ company.website }}
            </a>
            <span v-else>{{ company.domain || 'No website' }}</span>
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <Badge v-for="tag in company.tags ?? []" :key="tag.id" :hex="tag.color">{{ tag.name }}</Badge>
          </div>
        </div>

        <Button v-if="can('companies.update') && !company.audit.is_deleted" @click="formOpen = true">Edit</Button>
      </header>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="co-details">
          <h2 id="co-details" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Details</h2>
          <dl class="space-y-3 text-sm">
            <div><dt class="text-zinc-500 dark:text-zinc-400">Owner</dt><dd><OwnerChip :owner="company.owner" /></dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Phone</dt><dd class="text-zinc-950 dark:text-white">{{ company.phone || '—' }}</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Size</dt><dd class="text-zinc-950 dark:text-white">{{ company.size ? `${company.size} employees` : '—' }}</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Annual revenue</dt><dd class="text-zinc-950 dark:text-white tabular-nums">{{ company.annual_revenue ? formatCurrency(company.annual_revenue) : '—' }}</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Address</dt><dd class="text-zinc-950 dark:text-white">{{ address }}</dd></div>
          </dl>
          <p v-if="company.description" class="mt-4 rounded-lg bg-zinc-950/2.5 dark:bg-white/5 p-3 text-sm text-zinc-700 dark:text-zinc-300">{{ company.description }}</p>
        </section>

        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="co-contacts">
          <h2 id="co-contacts" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">
            Contacts <span class="text-zinc-400 dark:text-zinc-500">({{ company.contacts_count ?? 0 }})</span>
          </h2>

          <EmptyState v-if="contactsQuery.isPending.value" variant="loading" />
          <ul v-else-if="contactsQuery.data.value?.length" class="divide-y divide-zinc-950/5 dark:divide-white/5">
            <li v-for="c in contactsQuery.data.value" :key="c.id" class="py-2.5">
              <RouterLink :to="`/contacts/${c.id}`" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">{{ c.full_name }}</RouterLink>
              <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ c.job_title || humanize(c.lifecycle_stage) }}</p>
            </li>
          </ul>
          <p v-else class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No contacts yet.</p>
        </section>

        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="co-deals">
          <h2 id="co-deals" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">
            Deals <span class="text-zinc-400 dark:text-zinc-500">({{ company.deals_count ?? 0 }})</span>
          </h2>

          <EmptyState v-if="dealsQuery.isPending.value" variant="loading" />
          <ul v-else-if="dealsQuery.data.value?.length" class="divide-y divide-zinc-950/5 dark:divide-white/5">
            <li v-for="d in dealsQuery.data.value" :key="d.id" class="flex items-center justify-between gap-2 py-2.5">
              <div class="min-w-0">
                <RouterLink :to="`/deals/${d.id}`" class="block truncate text-sm font-medium text-zinc-950 dark:text-white hover:underline">{{ d.name }}</RouterLink>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ d.stage?.name ?? humanize(d.status) }}</p>
              </div>
              <span class="shrink-0 text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ formatCurrency(d.amount, d.currency) }}</span>
            </li>
          </ul>
          <p v-else class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No deals yet.</p>
        </section>
      </div>

      <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="co-timeline">
        <h2 id="co-timeline" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Activity timeline</h2>
        <EmptyState v-if="activitiesQuery.isPending.value" variant="loading" />
        <ActivityTimeline v-else :activities="activitiesQuery.data.value ?? []" />
      </section>

      <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="co-audit">
        <h2 id="co-audit" class="mb-3 text-sm font-semibold text-zinc-950 dark:text-white">Record history</h2>
        <AuditTrail :audit="company.audit" />
      </section>

      <CompanyFormModal :open="formOpen" :company="company" @close="formOpen = false" />
    </template>
  </div>
</template>
