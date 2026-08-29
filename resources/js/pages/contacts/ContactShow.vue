<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, formatDate, humanize } from '@/lib/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue'
import AuditTrail from '@/components/crm/AuditTrail.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import ContactFormModal from './ContactFormModal.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const { can } = useAuth()
const formOpen = ref(false)

const contactQuery = useQuery({
  queryKey: ['contacts', computed(() => Number(props.id))],
  queryFn: async ({ signal }) => (await api.get(`/contacts/${props.id}`, { signal })).data.data,
  staleTime: STALE.detail,
})

const activitiesQuery = useQuery({
  queryKey: ['activities', computed(() => ({ related: 'contact', id: Number(props.id) }))],
  queryFn: async ({ signal }) =>
    (
      await api.get('/activities', {
        params: {
          'filter[related_type]': 'contact',
          'filter[related_id]': props.id,
          sort: '-created_at',
          per_page: 25,
        },
        signal,
      })
    ).data.data,
  staleTime: STALE.list,
})

const contact = computed(() => contactQuery.data.value)
</script>

<template>
  <div class="space-y-5">
    <StateBlock v-if="contactQuery.isPending.value" variant="loading" title="Loading contact…" />

    <StateBlock
      v-else-if="contactQuery.isError.value"
      variant="error"
      title="Couldn't load this contact"
      :message="contactQuery.error.value?.message"
    >
      <RouterLink to="/contacts" class="text-sm font-medium text-indigo-600 hover:underline">Back to contacts</RouterLink>
    </StateBlock>

    <template v-else-if="contact">
      <nav class="text-sm text-slate-500">
        <RouterLink to="/contacts" class="hover:underline">Contacts</RouterLink>
        <span aria-hidden="true"> / </span>
        <span class="text-slate-700">{{ contact.full_name }}</span>
      </nav>

      <header class="card flex flex-wrap items-start justify-between gap-4 p-5">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-slate-900">{{ contact.full_name }}</h1>
            <BaseBadge tone="indigo">{{ humanize(contact.lifecycle_stage) }}</BaseBadge>
            <BaseBadge>{{ humanize(contact.lead_status) }}</BaseBadge>
            <BaseBadge v-if="contact.audit.is_deleted" tone="red">Deleted</BaseBadge>
          </div>

          <p class="mt-1 text-sm text-slate-600">
            {{ contact.job_title || 'No job title' }}
            <template v-if="contact.company">
              at
              <RouterLink :to="`/companies/${contact.company.id}`" class="text-indigo-600 hover:underline">
                {{ contact.company.name }}
              </RouterLink>
            </template>
          </p>

          <div class="mt-3 flex flex-wrap gap-2">
            <BaseBadge v-for="tag in contact.tags ?? []" :key="tag.id" :color="tag.color">{{ tag.name }}</BaseBadge>
          </div>
        </div>

        <BaseButton v-if="can('contacts.update') && !contact.audit.is_deleted" @click="formOpen = true">Edit</BaseButton>
      </header>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="card p-5" aria-labelledby="details-heading">
          <h2 id="details-heading" class="mb-4 text-sm font-semibold text-slate-900">Details</h2>

          <dl class="space-y-3 text-sm">
            <div>
              <dt class="text-slate-500">Email</dt>
              <dd>
                <a v-if="contact.email" :href="`mailto:${contact.email}`" class="text-indigo-600 hover:underline">{{ contact.email }}</a>
                <span v-else class="text-slate-400">—</span>
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Phone</dt>
              <dd>
                <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="text-slate-800">{{ contact.phone }}</a>
                <span v-else class="text-slate-400">—</span>
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">Mobile</dt>
              <dd class="text-slate-800">{{ contact.mobile || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Owner</dt>
              <dd><OwnerChip :owner="contact.owner" /></dd>
            </div>
            <div>
              <dt class="text-slate-500">Lead score</dt>
              <dd class="text-slate-800 tabular-nums">{{ contact.lead_score }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Source</dt>
              <dd class="text-slate-800">{{ contact.source || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Location</dt>
              <dd class="text-slate-800">{{ [contact.city, contact.state, contact.country].filter(Boolean).join(', ') || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Last contacted</dt>
              <dd class="text-slate-800">{{ formatDate(contact.last_contacted_at) }}</dd>
            </div>
          </dl>

          <p v-if="contact.notes" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ contact.notes }}</p>
        </section>

        <section class="card p-5 lg:col-span-2" aria-labelledby="timeline-heading">
          <h2 id="timeline-heading" class="mb-4 text-sm font-semibold text-slate-900">Activity timeline</h2>

          <StateBlock v-if="activitiesQuery.isPending.value" variant="loading" />
          <StateBlock
            v-else-if="activitiesQuery.isError.value"
            variant="error"
            title="Couldn't load activities"
            :message="activitiesQuery.error.value?.message"
          />
          <ActivityTimeline v-else :activities="activitiesQuery.data.value ?? []" />
        </section>
      </div>

      <section v-if="contact.deals?.length" class="card p-5" aria-labelledby="deals-heading">
        <h2 id="deals-heading" class="mb-4 text-sm font-semibold text-slate-900">Related deals</h2>
        <ul class="divide-y divide-slate-100">
          <li v-for="deal in contact.deals" :key="deal.id" class="flex items-center justify-between py-2.5">
            <RouterLink :to="`/deals/${deal.id}`" class="text-sm font-medium text-indigo-600 hover:underline">
              {{ deal.name }}
            </RouterLink>
            <span class="text-sm tabular-nums text-slate-700">{{ formatCurrency(deal.amount, deal.currency) }}</span>
          </li>
        </ul>
      </section>

      <section class="card p-5" aria-labelledby="audit-heading">
        <h2 id="audit-heading" class="mb-3 text-sm font-semibold text-slate-900">Record history</h2>
        <AuditTrail :audit="contact.audit" />
      </section>

      <ContactFormModal :open="formOpen" :contact="contact" @close="formOpen = false" />
    </template>
  </div>
</template>
