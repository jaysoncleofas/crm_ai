<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import { Button, Dialog, Field, Input, Select, Textarea } from '@/components/catalyst'

const props = defineProps({
  open: { type: Boolean, default: false },
  deal: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const toast = useToast()
const queryClient = useQueryClient()
const errors = ref({})

const form = reactive({
  name: '', pipeline_id: '', pipeline_stage_id: '', company_id: '', contact_id: '',
  owner_id: '', amount: 0, currency: 'USD', expected_close_date: '', source: '', description: '',
})

const { data: pipelines } = useQuery({
  queryKey: ['pipelines'],
  queryFn: async () => (await api.get('/pipelines')).data.data,
  staleTime: STALE.reference,
})

const { data: companies } = useQuery({
  queryKey: ['companies', 'options'],
  queryFn: async () => (await api.get('/companies', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

const { data: contacts } = useQuery({
  queryKey: ['contacts', 'options'],
  queryFn: async () => (await api.get('/contacts', { params: { per_page: 100, sort: 'last_name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

const { data: users } = useQuery({
  queryKey: ['users', 'options'],
  queryFn: async () => (await api.get('/users', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

// Stage options always follow the chosen pipeline.
const stages = computed(
  () => (pipelines.value ?? []).find((p) => p.id === Number(form.pipeline_id))?.stages ?? [],
)

watch(
  () => form.pipeline_id,
  () => {
    if (!stages.value.some((s) => s.id === Number(form.pipeline_stage_id))) {
      form.pipeline_stage_id = stages.value[0]?.id ?? ''
    }
  },
)

watch(
  () => [props.open, props.deal, pipelines.value],
  () => {
    if (!props.open) return
    errors.value = {}

    const s = props.deal ?? {}
    const defaultPipeline = (pipelines.value ?? []).find((p) => p.is_default) ?? (pipelines.value ?? [])[0]

    Object.assign(form, {
      name: s.name ?? '',
      pipeline_id: s.pipeline_id ?? defaultPipeline?.id ?? '',
      pipeline_stage_id: s.pipeline_stage_id ?? defaultPipeline?.stages?.[0]?.id ?? '',
      company_id: s.company_id ?? '',
      contact_id: s.contact_id ?? '',
      owner_id: s.owner_id ?? '',
      amount: s.amount ?? 0,
      currency: s.currency ?? 'USD',
      expected_close_date: s.expected_close_date ?? '',
      source: s.source ?? '',
      description: s.description ?? '',
    })
  },
  { immediate: true },
)

const mutation = useMutation({
  mutationFn: async (payload) => {
    const body = {
      ...payload,
      company_id: payload.company_id || null,
      contact_id: payload.contact_id || null,
      owner_id: payload.owner_id || null,
      expected_close_date: payload.expected_close_date || null,
    }

    return props.deal
      ? (await api.patch(`/deals/${props.deal.id}`, body)).data.data
      : (await api.post('/deals', body)).data.data
  },
  onSuccess(saved) {
    queryClient.invalidateQueries({ queryKey: ['deals'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success(props.deal ? 'Deal updated.' : 'Deal created.')
    emit('saved', saved)
    emit('close')
  },
  onError(error) {
    if (error.status === 422) errors.value = error.errors
  },
})
</script>

<template>
  <Dialog :open="open" :title="deal ? 'Edit deal' : 'New deal'" size="2xl" @close="emit('close')">
    <form id="deal-form" class="space-y-4" novalidate @submit.prevent="mutation.mutate({ ...form })">
      <Field label="Deal name" :error="errors.name?.[0]" required>
        <Input v-model="form.name" type="text"  required />
      </Field>

      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Pipeline" :error="errors.pipeline_id?.[0]" required>
          <Select v-model="form.pipeline_id">
            <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
          </Select>
        </Field>

        <Field label="Stage" :error="errors.pipeline_stage_id?.[0]" required>
          <Select v-model="form.pipeline_stage_id">
            <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
          </Select>
        </Field>

        <Field label="Amount" :error="errors.amount?.[0]">
          <Input v-model.number="form.amount" type="number" min="0" step="0.01"  />
        </Field>

        <Field label="Currency" :error="errors.currency?.[0]">
          <Input v-model="form.currency" type="text" maxlength="3"  />
        </Field>

        <Field label="Company" :error="errors.company_id?.[0]">
          <Select v-model="form.company_id">
            <option value="">No company</option>
            <option v-for="c in companies ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
          </Select>
        </Field>

        <Field label="Primary contact" :error="errors.contact_id?.[0]">
          <Select v-model="form.contact_id">
            <option value="">No contact</option>
            <option v-for="c in contacts ?? []" :key="c.id" :value="c.id">{{ c.full_name }}</option>
          </Select>
        </Field>

        <Field label="Owner" :error="errors.owner_id?.[0]">
          <Select v-model="form.owner_id">
            <option value="">Unassigned</option>
            <option v-for="u in users ?? []" :key="u.id" :value="u.id">{{ u.name }}</option>
          </Select>
        </Field>

        <Field label="Expected close date" :error="errors.expected_close_date?.[0]">
          <Input v-model="form.expected_close_date" type="date"  />
        </Field>

        <Field label="Source" :error="errors.source?.[0]">
          <Input v-model="form.source" type="text"  />
        </Field>
      </div>

      <Field label="Description" :error="errors.description?.[0]">
        <Textarea v-model="form.description" :rows="3" />
      </Field>
    </form>

    <template #actions>
      <Button outline @click="emit('close')">Cancel</Button>
      <Button type="submit" form="deal-form" :loading="mutation.isPending.value">
        {{ deal ? 'Save changes' : 'Create deal' }}
      </Button>
    </template>
  </Dialog>
</template>
