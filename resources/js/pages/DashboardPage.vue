<script setup>
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { ArrowPathIcon } from '@heroicons/vue/16/solid'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency, formatNumber } from '@/lib/format'
import StatCard from '@/components/crm/StatCard.vue'
import ActivityTimeline from '@/components/crm/ActivityTimeline.vue'
import {
  Badge,
  Button,
  Divider,
  EmptyState,
  Heading,
  Subheading,
  Text,
} from '@/components/catalyst'

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
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <Heading>Good to see you, {{ user?.name?.split(' ')[0] }}</Heading>
        <Text class="mt-1">Here's where your pipeline stands today.</Text>
      </div>
      <Button outline :loading="isFetching" @click="refetch">
        <ArrowPathIcon class="size-4" aria-hidden="true" />
        Refresh
      </Button>
    </div>

    <EmptyState v-if="isPending" variant="loading" title="Loading dashboard…" />

    <EmptyState
      v-else-if="isError"
      variant="error"
      title="Couldn't load the dashboard"
      :message="error?.message"
    >
      <Button @click="refetch">Try again</Button>
    </EmptyState>

    <template v-else>
      <Subheading class="mt-10">Team</Subheading>
      <Divider class="mt-4" />
      <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Open pipeline"
          :value="formatCurrency(totals.open_deal_value)"
          :sublabel="`${formatNumber(totals.open_deals)} open deals`"
        />
        <StatCard label="Won this month" :value="formatCurrency(totals.won_deal_value_this_month)" />
        <StatCard
          label="Contacts"
          :value="formatNumber(totals.contacts)"
          :sublabel="`${formatNumber(totals.companies)} companies`"
        />
        <StatCard label="Due today" :value="formatNumber(totals.activities_due_today)" sublabel="planned activities" />
      </div>

      <Subheading class="mt-14">Your book</Subheading>
      <Divider class="mt-4" />
      <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="My open deals" :value="formatNumber(mine.open_deals)" />
        <StatCard label="My pipeline value" :value="formatCurrency(mine.open_deal_value)" />
        <StatCard label="My overdue tasks" :value="formatNumber(mine.overdue_activities)" />
        <StatCard label="My contacts" :value="formatNumber(mine.contacts)" />
      </div>

      <div class="mt-14 grid gap-10 lg:grid-cols-3">
        <section class="lg:col-span-2" aria-labelledby="funnel-heading">
          <Subheading id="funnel-heading">Pipeline by stage</Subheading>
          <Divider class="mt-4" />

          <ul v-if="pipeline.length" class="mt-6 space-y-4">
            <li v-for="stage in pipeline" :key="stage.stage_id">
              <div class="mb-1.5 flex items-center justify-between text-sm/6">
                <span class="flex items-center gap-2 text-zinc-950 dark:text-white">
                  <span class="size-2 rounded-full" :style="{ backgroundColor: stage.color }" aria-hidden="true" />
                  {{ stage.name }}
                </span>
                <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                  {{ formatCurrency(stage.value) }}
                  <span class="text-zinc-400 dark:text-zinc-500">· {{ stage.deals }}</span>
                </span>
              </div>
              <div class="h-1.5 overflow-hidden rounded-full bg-zinc-950/5 dark:bg-white/10">
                <div
                  class="h-full rounded-full"
                  :style="{ width: `${Math.round((stage.value / pipelineMax) * 100)}%`, backgroundColor: stage.color }"
                />
              </div>
            </li>
          </ul>

          <EmptyState v-else title="No pipeline data" message="Create a deal to see your funnel." />
        </section>

        <section aria-labelledby="upcoming-heading">
          <Subheading id="upcoming-heading">Your next activities</Subheading>
          <Divider class="mt-4" />
          <div class="mt-6">
            <ActivityTimeline :activities="upcoming" />
          </div>
        </section>
      </div>

      <section class="mt-14" aria-labelledby="won-heading">
        <Subheading id="won-heading">Recently won</Subheading>
        <Divider class="mt-4" />

        <ul v-if="recentWon.length" class="mt-2 divide-y divide-zinc-950/5 dark:divide-white/5">
          <li v-for="deal in recentWon" :key="deal.id" class="flex items-center justify-between gap-4 py-3">
            <div class="min-w-0">
              <p class="truncate text-sm/6 font-medium text-zinc-950 dark:text-white">{{ deal.name }}</p>
              <p class="text-xs/5 text-zinc-500 dark:text-zinc-400">{{ deal.owner ?? 'Unassigned' }}</p>
            </div>
            <Badge color="emerald">{{ formatCurrency(deal.amount, deal.currency) }}</Badge>
          </li>
        </ul>

        <EmptyState v-else title="Nothing won yet" message="Closed-won deals will appear here." />
      </section>
    </template>
  </div>
</template>
