<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { Badge, Button, Divider, EmptyState, Heading, Subheading, Text } from '@/components/catalyst'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate, humanize } from '@/lib/format'
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue'
import AuditTrail from '@/components/crm/AuditTrail.vue'
import OwnerChip from '@/components/crm/OwnerChip.vue'
import DealFormModal from './DealFormModal.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()
const formOpen = ref(false)

const dealQuery = useQuery({
  queryKey: ['deals', computed(() => Number(props.id))],
  queryFn: async ({ signal }) => (await api.get(`/deals/${props.id}`, { signal })).data.data,
  staleTime: STALE.detail,
})

const { data: pipelines } = useQuery({
  queryKey: ['pipelines'],
  queryFn: async () => (await api.get('/pipelines')).data.data,
  staleTime: STALE.reference,
})

const activitiesQuery = useQuery({
  queryKey: ['activities', computed(() => ({ related: 'deal', id: Number(props.id) }))],
  queryFn: async ({ signal }) =>
    (
      await api.get('/activities', {
        params: { 'filter[related_type]': 'deal', 'filter[related_id]': props.id, per_page: 30, sort: '-created_at' },
        signal,
      })
    ).data.data,
  staleTime: STALE.list,
})

const deal = computed(() => dealQuery.data.value)
const stages = computed(
  () => (pipelines.value ?? []).find((p) => p.id === deal.value?.pipeline_id)?.stages ?? [],
)

const moveStage = useMutation({
  mutationFn: (stageId) => api.patch(`/deals/${props.id}/stage`, { pipeline_stage_id: stageId }),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['deals'] })
    queryClient.invalidateQueries({ queryKey: ['activities'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Stage updated.')
  },
})

const STATUS_TONES = { open: 'blue', won: 'green', lost: 'red' }
</script>

<template>
  <div class="space-y-5">
    <EmptyState v-if="dealQuery.isPending.value" variant="loading" title="Loading deal…" />

    <EmptyState
      v-else-if="dealQuery.isError.value"
      variant="error"
      title="Couldn't load this deal"
      :message="dealQuery.error.value?.message"
    >
      <RouterLink to="/deals" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">Back to deals</RouterLink>
    </EmptyState>

    <template v-else-if="deal">
      <nav class="text-sm text-zinc-500 dark:text-zinc-400">
        <RouterLink to="/deals" class="hover:underline">Deals</RouterLink>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-700 dark:text-zinc-300">{{ deal.name }}</span>
      </nav>

      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ deal.name }}</h1>
            <Badge :color="STATUS_TONES[deal.status]">{{ humanize(deal.status) }}</Badge>
            <Badge v-if="deal.audit.is_deleted" color="red">Deleted</Badge>
          </div>
          <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">
            {{ formatCurrency(deal.amount, deal.currency) }}
          </p>
        </div>

        <Button v-if="can('deals.update') && !deal.audit.is_deleted" @click="formOpen = true">Edit</Button>
      </header>

      <!-- Stage stepper doubles as the stage control -->
      <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="stage-heading">
        <h2 id="stage-heading" class="mb-3 text-sm font-semibold text-zinc-950 dark:text-white">Stage</h2>

        <ol class="flex flex-wrap gap-2">
          <li v-for="stage in stages" :key="stage.id">
            <button
              type="button"
              class="rounded-lg border px-3 py-1.5 text-sm font-medium transition disabled:cursor-not-allowed"
              :class="
                stage.id === deal.pipeline_stage_id
                  ? 'border-transparent text-white'
                  : 'border-zinc-950/10 dark:border-white/10 bg-white text-zinc-700 dark:text-zinc-300 hover:bg-zinc-950/2.5 dark:bg-white/5'
              "
              :style="stage.id === deal.pipeline_stage_id ? { backgroundColor: stage.color } : undefined"
              :aria-current="stage.id === deal.pipeline_stage_id ? 'step' : undefined"
              :disabled="!can('deals.update') || deal.audit.is_deleted || moveStage.isPending.value"
              @click="moveStage.mutate(stage.id)"
            >
              {{ stage.name }}
            </button>
          </li>
        </ol>
      </section>

      <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="deal-details">
          <h2 id="deal-details" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Details</h2>
          <dl class="space-y-3 text-sm">
            <div><dt class="text-zinc-500 dark:text-zinc-400">Pipeline</dt><dd class="text-zinc-950 dark:text-white">{{ deal.pipeline?.name ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Probability</dt><dd class="text-zinc-950 dark:text-white tabular-nums">{{ deal.probability }}%</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Expected close</dt><dd class="text-zinc-950 dark:text-white">{{ formatDate(deal.expected_close_date) }}</dd></div>
            <div v-if="deal.closed_at"><dt class="text-zinc-500 dark:text-zinc-400">Closed</dt><dd class="text-zinc-950 dark:text-white">{{ formatDate(deal.closed_at) }}</dd></div>
            <div v-if="deal.won_reason"><dt class="text-zinc-500 dark:text-zinc-400">Won reason</dt><dd class="text-emerald-700">{{ deal.won_reason }}</dd></div>
            <div v-if="deal.lost_reason"><dt class="text-zinc-500 dark:text-zinc-400">Lost reason</dt><dd class="text-red-700">{{ deal.lost_reason }}</dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Owner</dt><dd><OwnerChip :owner="deal.owner" /></dd></div>
            <div><dt class="text-zinc-500 dark:text-zinc-400">Source</dt><dd class="text-zinc-950 dark:text-white">{{ deal.source || '—' }}</dd></div>
          </dl>
          <p v-if="deal.description" class="mt-4 rounded-lg bg-zinc-950/2.5 dark:bg-white/5 p-3 text-sm text-zinc-700 dark:text-zinc-300">{{ deal.description }}</p>
        </section>

        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="deal-people">
          <h2 id="deal-people" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">People &amp; account</h2>

          <div v-if="deal.company" class="mb-4">
            <p class="text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Company</p>
            <RouterLink :to="`/companies/${deal.company.id}`" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">
              {{ deal.company.name }}
            </RouterLink>
          </div>

          <div v-if="deal.primary_contact" class="mb-4">
            <p class="text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Primary contact</p>
            <RouterLink :to="`/contacts/${deal.primary_contact.id}`" class="text-sm font-medium text-zinc-950 dark:text-white hover:underline">
              {{ deal.primary_contact.full_name }}
            </RouterLink>
          </div>

          <div v-if="deal.contacts?.length">
            <p class="mb-1 text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Also involved</p>
            <ul class="space-y-1">
              <li v-for="c in deal.contacts" :key="c.id">
                <RouterLink :to="`/contacts/${c.id}`" class="text-sm text-zinc-700 dark:text-zinc-300 hover:underline">{{ c.full_name }}</RouterLink>
              </li>
            </ul>
          </div>

          <div v-if="deal.tags?.length" class="mt-4 flex flex-wrap gap-2">
            <Badge v-for="tag in deal.tags" :key="tag.id" :hex="tag.color">{{ tag.name }}</Badge>
          </div>
        </section>

        <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="deal-timeline">
          <h2 id="deal-timeline" class="mb-4 text-sm font-semibold text-zinc-950 dark:text-white">Timeline</h2>
          <EmptyState v-if="activitiesQuery.isPending.value" variant="loading" />
          <ActivityTimeline v-else :activities="activitiesQuery.data.value ?? []" />
        </section>
      </div>

      <section class="rounded-xl border border-zinc-950/5 p-6 dark:border-white/10" aria-labelledby="deal-audit">
        <h2 id="deal-audit" class="mb-3 text-sm font-semibold text-zinc-950 dark:text-white">Record history</h2>
        <AuditTrail :audit="deal.audit" />
      </section>

      <DealFormModal :open="formOpen" :deal="deal" @close="formOpen = false" />
    </template>
  </div>
</template>
