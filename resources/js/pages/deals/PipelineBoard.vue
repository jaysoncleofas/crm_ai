<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { Badge, Button, EmptyState, Select } from '@/components/catalyst'
import PageHeader from '@/components/crm/PageHeader.vue'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/lib/format'
import DealFormModal from './DealFormModal.vue'

const { can } = useAuth()
const toast = useToast()
const queryClient = useQueryClient()

const pipelineId = ref(null)
const formOpen = ref(false)
const draggingId = ref(null)
const dragOverStage = ref(null)

const { data: pipelines, isPending: pipelinesPending } = useQuery({
  queryKey: ['pipelines'],
  queryFn: async () => (await api.get('/pipelines')).data.data,
  staleTime: STALE.reference,
})

watch(
  pipelines,
  (list) => {
    if (pipelineId.value === null && list?.length) {
      pipelineId.value = (list.find((p) => p.is_default) ?? list[0]).id
    }
  },
  { immediate: true },
)

const boardQuery = useQuery({
  queryKey: ['deals', 'board', pipelineId],
  queryFn: async ({ signal }) =>
    (await api.get('/deals/board', { params: { pipeline_id: pipelineId.value }, signal })).data.data,
  enabled: computed(() => pipelineId.value !== null),
  staleTime: STALE.list,
})

const pipeline = computed(() => (pipelines.value ?? []).find((p) => p.id === pipelineId.value))
const stages = computed(() => pipeline.value?.stages ?? [])

function dealsIn(stageId) {
  return boardQuery.data.value?.[stageId] ?? []
}

function stageTotal(stageId) {
  return dealsIn(stageId).reduce((sum, deal) => sum + Number(deal.amount ?? 0), 0)
}

const moveStage = useMutation({
  mutationFn: ({ dealId, stageId }) => api.patch(`/deals/${dealId}/stage`, { pipeline_stage_id: stageId }),
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['deals'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success('Deal moved.')
  },
})

function onDragStart(event, deal) {
  draggingId.value = deal.id
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(deal.id))
}

function onDrop(stageId) {
  const dealId = draggingId.value
  dragOverStage.value = null
  draggingId.value = null

  if (!dealId) return
  if (dealsIn(stageId).some((d) => d.id === dealId)) return // already here

  moveStage.mutate({ dealId, stageId })
}

/** Keyboard equivalent of the drag: move a focused card to an adjacent stage. */
function moveByKeyboard(deal, direction) {
  const index = stages.value.findIndex((s) => s.id === deal.pipeline_stage_id)
  const target = stages.value[index + direction]

  if (target) {
    moveStage.mutate({ dealId: deal.id, stageId: target.id })
  }
}
</script>

<template>
  <div class="space-y-4">
    <PageHeader title="Pipeline" description="Drag a card to move a deal, or use ← / → on a focused card.">
      <template #actions>
        <div class="w-48">
          <Select v-model="pipelineId" aria-label="Pipeline">
            <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
          </Select>
        </div>
        <Button outline to="/deals">List view</Button>
        <Button v-if="can('deals.create')" @click="formOpen = true">New deal</Button>
      </template>
    </PageHeader>

    <EmptyState v-if="pipelinesPending || boardQuery.isPending.value" variant="loading" title="Loading pipeline…" />

    <EmptyState
      v-else-if="boardQuery.isError.value"
      variant="error"
      title="Couldn't load the board"
      :message="boardQuery.error.value?.message"
    >
      <Button size="sm" @click="boardQuery.refetch()">Try again</Button>
    </EmptyState>

    <div v-else class="overflow-x-auto pb-2">
      <ul class="flex min-w-max gap-4">
        <li
          v-for="stage in stages"
          :key="stage.id"
          class="w-72 shrink-0"
          @dragover.prevent="dragOverStage = stage.id"
          @dragleave="dragOverStage = null"
          @drop.prevent="onDrop(stage.id)"
        >
          <div
            class="flex h-full flex-col rounded-xl border bg-zinc-950/5 dark:bg-white/10/60 transition"
            :class="dragOverStage === stage.id
                ? 'border-blue-500 bg-blue-500/5'
                : 'border-zinc-950/10 bg-zinc-950/2.5 dark:border-white/10 dark:bg-white/2.5'"
          >
            <div class="border-b border-zinc-950/10 px-3 py-2.5 dark:border-white/10">
              <div class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-zinc-950 dark:text-white">
                  <span class="size-2.5 rounded-full" :style="{ backgroundColor: stage.color }" aria-hidden="true" />
                  {{ stage.name }}
                </span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs tabular-nums text-zinc-500 ring-1 ring-zinc-950/5 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-white/10">
                  {{ dealsIn(stage.id).length }}
                </span>
              </div>
              <p class="mt-1 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ formatCurrency(stageTotal(stage.id)) }}</p>
            </div>

            <ul class="flex-1 space-y-2 p-2">
              <li
                v-for="deal in dealsIn(stage.id)"
                :key="deal.id"
                :draggable="can('deals.update')"
                class="rounded-xl border border-zinc-950/5 cursor-grab p-3 active:cursor-grabbing dark:border-white/10"
                :class="draggingId === deal.id ? 'opacity-50' : ''"
                tabindex="0"
                @dragstart="onDragStart($event, deal)"
                @dragend="draggingId = null"
                @keydown.left.prevent="moveByKeyboard(deal, -1)"
                @keydown.right.prevent="moveByKeyboard(deal, 1)"
              >
                <RouterLink :to="`/deals/${deal.id}`" class="block text-sm font-medium text-zinc-950 dark:text-white hover:text-zinc-950 dark:text-white">
                  {{ deal.name }}
                </RouterLink>
                <p class="mt-1 text-sm font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">
                  {{ formatCurrency(deal.amount, deal.currency) }}
                </p>
                <p v-if="deal.company" class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ deal.company.name }}</p>
                <p v-if="deal.owner" class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ deal.owner.name }}</p>
              </li>

              <li v-if="dealsIn(stage.id).length === 0" class="rounded-lg border border-dashed border-zinc-950/10 px-3 py-6 text-center text-xs text-zinc-400 dark:border-white/10 dark:text-zinc-500">
                Drop a deal here
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </div>

    <DealFormModal :open="formOpen" :deal="null" @close="formOpen = false" />
  </div>
</template>
