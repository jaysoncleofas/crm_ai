<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
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
    <StateBlock v-if="companyQuery.isPending.value" variant="loading" title="Loading company…" />

    <StateBlock
      v-else-if="companyQuery.isError.value"
      variant="error"
      title="Couldn't load this company"
      :message="companyQuery.error.value?.message"
    >
      <RouterLink to="/companies" class="text-sm font-medium text-indigo-600 hover:underline">Back to companies</RouterLink>
    </StateBlock>

    <template v-else-if="company">
      <nav class="text-sm text-slate-500">
        <RouterLink to="/companies" class="hover:underline">Companies</RouterLink>
        <span aria-hidden="true"> / </span>
        <span class="text-slate-700">{{ company.name }}</span>
      </nav>

      <header class="card flex flex-wrap items-start justify-between gap-4 p-5">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-slate-900">{{ company.name }}</h1>
            <BaseBadge v-if="company.industry">{{ company.industry }}</BaseBadge>
            <BaseBadge v-if="company.audit.is_deleted" tone="red">Deleted</BaseBadge>
          </div>
          <p class="mt-1 text-sm text-slate-600">
            <a v-if="company.website" :href="company.website" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">
              {{ company.website }}
            </a>
            <span v-else>{{ company.domain || 'No website' }}</span>
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <BaseBadge v-for="tag in company.tags ?? []" :key="tag.id" :color="tag.color">{{ tag.name }}</BaseBadge>
          </div>
        </div>

        <BaseButton v-if="can('companies.update') && !company.audit.is_deleted" @click="formOpen = true">Edit</BaseButton>
      </header>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="card p-5" aria-labelledby="co-details">
          <h2 id="co-details" class="mb-4 text-sm font-semibold text-slate-900">Details</h2>
          <dl class="space-y-3 text-sm">
            <div><dt class="text-slate-500">Owner</dt><dd><OwnerChip :owner="company.owner" /></dd></div>
            <div><dt class="text-slate-500">Phone</dt><dd class="text-slate-800">{{ company.phone || '—' }}</dd></div>
            <div><dt class="text-slate-500">Size</dt><dd class="text-slate-800">{{ company.size ? `${company.size} employees` : '—' }}</dd></div>
            <div><dt class="text-slate-500">Annual revenue</dt><dd class="text-slate-800 tabular-nums">{{ company.annual_revenue ? formatCurrency(company.annual_revenue) : '—' }}</dd></div>
            <div><dt class="text-slate-500">Address</dt><dd class="text-slate-800">{{ address }}</dd></div>
          </dl>
          <p v-if="company.description" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ company.description }}</p>
        </section>

        <section class="card p-5" aria-labelledby="co-contacts">
          <h2 id="co-contacts" class="mb-4 text-sm font-semibold text-slate-900">
            Contacts <span class="text-slate-400">({{ company.contacts_count ?? 0 }})</span>
          </h2>

          <StateBlock v-if="contactsQuery.isPending.value" variant="loading" />
          <ul v-else-if="contactsQuery.data.value?.length" class="divide-y divide-slate-100">
            <li v-for="c in contactsQuery.data.value" :key="c.id" class="py-2.5">
              <RouterLink :to="`/contacts/${c.id}`" class="text-sm font-medium text-indigo-600 hover:underline">{{ c.full_name }}</RouterLink>
              <p class="text-xs text-slate-500">{{ c.job_title || humanize(c.lifecycle_stage) }}</p>
            </li>
          </ul>
          <p v-else class="py-6 text-center text-sm text-slate-500">No contacts yet.</p>
        </section>

        <section class="card p-5" aria-labelledby="co-deals">
          <h2 id="co-deals" class="mb-4 text-sm font-semibold text-slate-900">
            Deals <span class="text-slate-400">({{ company.deals_count ?? 0 }})</span>
          </h2>

          <StateBlock v-if="dealsQuery.isPending.value" variant="loading" />
          <ul v-else-if="dealsQuery.data.value?.length" class="divide-y divide-slate-100">
            <li v-for="d in dealsQuery.data.value" :key="d.id" class="flex items-center justify-between gap-2 py-2.5">
              <div class="min-w-0">
                <RouterLink :to="`/deals/${d.id}`" class="block truncate text-sm font-medium text-indigo-600 hover:underline">{{ d.name }}</RouterLink>
                <p class="text-xs text-slate-500">{{ d.stage?.name ?? humanize(d.status) }}</p>
              </div>
              <span class="shrink-0 text-sm tabular-nums text-slate-700">{{ formatCurrency(d.amount, d.currency) }}</span>
            </li>
          </ul>
          <p v-else class="py-6 text-center text-sm text-slate-500">No deals yet.</p>
        </section>
      </div>

      <section class="card p-5" aria-labelledby="co-timeline">
        <h2 id="co-timeline" class="mb-4 text-sm font-semibold text-slate-900">Activity timeline</h2>
        <StateBlock v-if="activitiesQuery.isPending.value" variant="loading" />
        <ActivityTimeline v-else :activities="activitiesQuery.data.value ?? []" />
      </section>

      <section class="card p-5" aria-labelledby="co-audit">
        <h2 id="co-audit" class="mb-3 text-sm font-semibold text-slate-900">Record history</h2>
        <AuditTrail :audit="company.audit" />
      </section>

      <CompanyFormModal :open="formOpen" :company="company" @close="formOpen = false" />
    </template>
  </div>
</template>
