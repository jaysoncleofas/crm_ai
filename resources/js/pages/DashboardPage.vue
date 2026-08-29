<script setup>
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, formatNumber } from '@/lib/format'
import StatCard from '@/components/crm/StatCard.vue'
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const { user } = useAuth()

const { data, isPending, isError, error, refetch, isFetching } = useQuery({
  queryKey: ['dashboard'],
  queryFn: async ({ signal }) => (await api.get('/dashboard', { signal })).data.data,
  staleTime: STALE.detail,
})

const totals = computed(() => data.value?.totals ?? {})
const mine = computed(() => data.value?.my ?? {})
const pipeline = computed(() => data.value?.pipeline ?? [])
const upcoming = computed(() => data.value?.upcoming_activities ?? [])
const recentWon = computed(() => data.value?.recent_won ?? [])

const pipelineMax = computed(() => Math.max(1, ...pipeline.value.map((s) => s.value)))
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Good to see you, {{ user?.name?.split(' ')[0] }}</h1>
        <p class="text-sm text-slate-500">Here's where your pipeline stands today.</p>
      </div>
      <BaseButton variant="secondary" size="sm" :loading="isFetching" @click="refetch">Refresh</BaseButton>
    </header>

    <StateBlock v-if="isPending" variant="loading" title="Loading dashboard…" />

    <StateBlock
      v-else-if="isError"
      variant="error"
      title="Couldn't load the dashboard"
      :message="error?.message"
    >
      <BaseButton size="sm" @click="refetch">Try again</BaseButton>
    </StateBlock>

    <template v-else>
      <section aria-labelledby="team-heading">
        <h2 id="team-heading" class="sr-only">Team totals</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Open pipeline" :value="formatCurrency(totals.open_deal_value)" :sublabel="`${formatNumber(totals.open_deals)} open deals`" tone="indigo" />
          <StatCard label="Won this month" :value="formatCurrency(totals.won_deal_value_this_month)" tone="green" />
          <StatCard label="Contacts" :value="formatNumber(totals.contacts)" :sublabel="`${formatNumber(totals.companies)} companies`" />
          <StatCard label="Due today" :value="formatNumber(totals.activities_due_today)" sublabel="planned activities" tone="amber" />
        </div>
      </section>

      <section aria-labelledby="mine-heading">
        <h2 id="mine-heading" class="mb-3 text-sm font-semibold text-slate-900">Your book</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="My open deals" :value="formatNumber(mine.open_deals)" />
          <StatCard label="My pipeline value" :value="formatCurrency(mine.open_deal_value)" tone="indigo" />
          <StatCard label="My overdue tasks" :value="formatNumber(mine.overdue_activities)" :tone="mine.overdue_activities > 0 ? 'red' : 'slate'" />
          <StatCard label="My contacts" :value="formatNumber(mine.contacts)" />
        </div>
      </section>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2" aria-labelledby="funnel-heading">
          <h2 id="funnel-heading" class="mb-4 text-sm font-semibold text-slate-900">Pipeline by stage</h2>

          <ul v-if="pipeline.length" class="space-y-3">
            <li v-for="stage in pipeline" :key="stage.stage_id">
              <div class="mb-1 flex items-center justify-between text-sm">
                <span class="flex items-center gap-2 text-slate-700">
                  <span class="size-2.5 rounded-full" :style="{ backgroundColor: stage.color }" aria-hidden="true" />
                  {{ stage.name }}
                </span>
                <span class="tabular-nums text-slate-600">
                  {{ formatCurrency(stage.value) }}
                  <span class="text-slate-400">· {{ stage.deals }}</span>
                </span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full"
                  :style="{ width: `${Math.round((stage.value / pipelineMax) * 100)}%`, backgroundColor: stage.color }"
                />
              </div>
            </li>
          </ul>

          <StateBlock v-else title="No pipeline data" message="Create a deal to see your funnel." />
        </section>

        <section class="card p-5" aria-labelledby="upcoming-heading">
          <h2 id="upcoming-heading" class="mb-4 text-sm font-semibold text-slate-900">Your next activities</h2>
          <ActivityTimeline :activities="upcoming" />
        </section>
      </div>

      <section class="card p-5" aria-labelledby="won-heading">
        <h2 id="won-heading" class="mb-4 text-sm font-semibold text-slate-900">Recently won</h2>

        <ul v-if="recentWon.length" class="divide-y divide-slate-100">
          <li v-for="deal in recentWon" :key="deal.id" class="flex items-center justify-between gap-4 py-2.5">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-900">{{ deal.name }}</p>
              <p class="text-xs text-slate-500">{{ deal.owner ?? 'Unassigned' }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold tabular-nums text-emerald-600">
              {{ formatCurrency(deal.amount, deal.currency) }}
            </span>
          </li>
        </ul>

        <StateBlock v-else title="Nothing won yet" message="Closed-won deals will appear here." />
      </section>
    </template>
  </div>
</template>
