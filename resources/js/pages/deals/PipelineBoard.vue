<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/lib/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import StateBlock from '@/components/ui/StateBlock.vue'
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
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Pipeline</h1>
        <p class="text-sm text-slate-500">Drag a card to move a deal, or use ← / → on a focused card.</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <label class="sr-only" for="pipeline-select">Pipeline</label>
        <select id="pipeline-select" v-model="pipelineId" class="field-input w-auto">
          <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <RouterLink to="/deals"><BaseButton variant="secondary">List view</BaseButton></RouterLink>
        <BaseButton v-if="can('deals.create')" @click="formOpen = true">New deal</BaseButton>
      </div>
    </header>

    <StateBlock v-if="pipelinesPending || boardQuery.isPending.value" variant="loading" title="Loading pipeline…" />

    <StateBlock
      v-else-if="boardQuery.isError.value"
      variant="error"
      title="Couldn't load the board"
      :message="boardQuery.error.value?.message"
    >
      <BaseButton size="sm" @click="boardQuery.refetch()">Try again</BaseButton>
    </StateBlock>

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
            class="flex h-full flex-col rounded-xl border bg-slate-100/60 transition"
            :class="dragOverStage === stage.id ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200'"
          >
            <div class="border-b border-slate-200 px-3 py-2.5">
              <div class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                  <span class="size-2.5 rounded-full" :style="{ backgroundColor: stage.color }" aria-hidden="true" />
                  {{ stage.name }}
                </span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs tabular-nums text-slate-600">
                  {{ dealsIn(stage.id).length }}
                </span>
              </div>
              <p class="mt-1 text-xs tabular-nums text-slate-500">{{ formatCurrency(stageTotal(stage.id)) }}</p>
            </div>

            <ul class="flex-1 space-y-2 p-2">
              <li
                v-for="deal in dealsIn(stage.id)"
                :key="deal.id"
                :draggable="can('deals.update')"
                class="card cursor-grab p-3 active:cursor-grabbing"
                :class="draggingId === deal.id ? 'opacity-50' : ''"
                tabindex="0"
                @dragstart="onDragStart($event, deal)"
                @dragend="draggingId = null"
                @keydown.left.prevent="moveByKeyboard(deal, -1)"
                @keydown.right.prevent="moveByKeyboard(deal, 1)"
              >
                <RouterLink :to="`/deals/${deal.id}`" class="block text-sm font-medium text-slate-900 hover:text-indigo-600">
                  {{ deal.name }}
                </RouterLink>
                <p class="mt-1 text-sm font-semibold tabular-nums text-slate-700">
                  {{ formatCurrency(deal.amount, deal.currency) }}
                </p>
                <p v-if="deal.company" class="mt-0.5 truncate text-xs text-slate-500">{{ deal.company.name }}</p>
                <p v-if="deal.owner" class="mt-1 text-xs text-slate-400">{{ deal.owner.name }}</p>
              </li>

              <li v-if="dealsIn(stage.id).length === 0" class="rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-400">
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
