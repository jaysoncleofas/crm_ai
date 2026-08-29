<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { Badge, Button, Divider, EmptyState, Heading, Subheading, Text } from '@/components/catalyst'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, formatDate, humanize } from '@/lib/format'
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
    <EmptyState v-if="contactQuery.isPending.value" variant="loading" title="Loading contact…" />

    <EmptyState
      v-else-if="contactQuery.isError.value"
      variant="error"
      title="Couldn't load this contact"
      :message="contactQuery.error.value?.message"
    >
      <RouterLink to="/contacts" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">Back to contacts</RouterLink>
    </EmptyState>

    <template v-else-if="contact">
      <nav class="text-sm text-zinc-500 dark:text-zinc-400">
        <RouterLink to="/contacts" class="hover:underline">Contacts</RouterLink>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-700 dark:text-zinc-300">{{ contact.full_name }}</span>
      </nav>

      <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ contact.full_name }}</h1>
            <Badge color="indigo">{{ humanize(contact.lifecycle_stage) }}</Badge>
            <Badge>{{ humanize(contact.lead_status) }}</Badge>
            <Badge v-if="contact.audit.is_deleted" color="red">Deleted</Badge>
          </div>

          <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ contact.job_title || 'No job title' }}
            <template v-if="contact.company">
              at
              <RouterLink :to="`/companies/${contact.company.id}`" class="text-zinc-950 dark:text-white hover:underline">
                {{ contact.company.name }}
              </RouterLink>
            </template>
          </p>

          <div class="mt-3 flex flex-wrap gap-2">
            <Badge v-for="tag in contact.tags ?? []" :key="tag.id" :hex="tag.color">{{ tag.name }}</Badge>
          </div>
        </div>

        <Button v-if="can('contacts.update') && !contact.audit.is_deleted" @click="formOpen = true">Edit</Button>
      </header>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="details-heading">
          <h2 id="details-heading" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Details</h2>

          <dl class="space-y-3 text-sm">
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Email</dt>
              <dd>
                <a v-if="contact.email" :href="`mailto:${contact.email}`" class="text-zinc-950 dark:text-white hover:underline">{{ contact.email }}</a>
                <span v-else class="text-zinc-400 dark:text-zinc-500">—</span>
              </dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Phone</dt>
              <dd>
                <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="text-zinc-950 dark:text-white">{{ contact.phone }}</a>
                <span v-else class="text-zinc-400 dark:text-zinc-500">—</span>
              </dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Mobile</dt>
              <dd class="text-zinc-950 dark:text-white">{{ contact.mobile || '—' }}</dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Owner</dt>
              <dd><OwnerChip :owner="contact.owner" /></dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Lead score</dt>
              <dd class="text-zinc-950 dark:text-white tabular-nums">{{ contact.lead_score }}</dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Source</dt>
              <dd class="text-zinc-950 dark:text-white">{{ contact.source || '—' }}</dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Location</dt>
              <dd class="text-zinc-950 dark:text-white">{{ [contact.city, contact.state, contact.country].filter(Boolean).join(', ') || '—' }}</dd>
            </div>
            <div>
              <dt class="text-zinc-500 dark:text-zinc-400">Last contacted</dt>
              <dd class="text-zinc-950 dark:text-white">{{ formatDate(contact.last_contacted_at) }}</dd>
            </div>
          </dl>

          <p v-if="contact.notes" class="mt-4 rounded-lg bg-zinc-950/2.5 dark:bg-white/5 p-3 text-sm text-zinc-700 dark:text-zinc-300">{{ contact.notes }}</p>
        </section>

        <section class="rounded-xl border border-zinc-950/5 p-6 lg:col-span-2 dark:border-white/10" aria-labelledby="timeline-heading">
          <h2 id="timeline-heading" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Activity timeline</h2>

          <EmptyState v-if="activitiesQuery.isPending.value" variant="loading" />
          <EmptyState
            v-else-if="activitiesQuery.isError.value"
            variant="error"
            title="Couldn't load activities"
            :message="activitiesQuery.error.value?.message"
          />
          <ActivityTimeline v-else :activities="activitiesQuery.data.value ?? []" />
        </section>
      </div>

      <section v-if="contact.deals?.length" class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="deals-heading">
        <h2 id="deals-heading" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Related deals</h2>
        <ul class="divide-y divide-zinc-950/5 dark:divide-white/5">
          <li v-for="deal in contact.deals" :key="deal.id" class="flex items-center justify-between py-2.5">
            <RouterLink :to="`/deals/${deal.id}`" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">
              {{ deal.name }}
            </RouterLink>
            <span class="text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ formatCurrency(deal.amount, deal.currency) }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="audit-heading">
        <h2 id="audit-heading" class="mb-3 text-sm font-semibold text-zinc-950 dark:text-white">Record history</h2>
        <AuditTrail :audit="contact.audit" />
      </section>

      <ContactFormModal :open="formOpen" :contact="contact" @close="formOpen = false" />
    </template>
  </div>
</template>
