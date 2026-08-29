<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'

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
  <BaseModal :open="open" :title="deal ? 'Edit deal' : 'New deal'" @close="emit('close')">
    <form id="deal-form" class="space-y-4" novalidate @submit.prevent="mutation.mutate({ ...form })">
      <FormField v-slot="{ id, invalid }" label="Deal name" :error="errors.name?.[0]" required>
        <input :id="id" v-model="form.name" type="text" class="field-input" required :aria-invalid="invalid" />
      </FormField>

      <div class="grid gap-4 sm:grid-cols-2">
        <FormField v-slot="{ id }" label="Pipeline" :error="errors.pipeline_id?.[0]" required>
          <select :id="id" v-model="form.pipeline_id" class="field-input" required>
            <option v-for="p in pipelines ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Stage" :error="errors.pipeline_stage_id?.[0]" required>
          <select :id="id" v-model="form.pipeline_stage_id" class="field-input" required>
            <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Amount" :error="errors.amount?.[0]">
          <input :id="id" v-model.number="form.amount" type="number" min="0" step="0.01" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Currency" :error="errors.currency?.[0]">
          <input :id="id" v-model="form.currency" type="text" maxlength="3" class="field-input uppercase" />
        </FormField>

        <FormField v-slot="{ id }" label="Company" :error="errors.company_id?.[0]">
          <select :id="id" v-model="form.company_id" class="field-input">
            <option value="">No company</option>
            <option v-for="c in companies ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Primary contact" :error="errors.contact_id?.[0]">
          <select :id="id" v-model="form.contact_id" class="field-input">
            <option value="">No contact</option>
            <option v-for="c in contacts ?? []" :key="c.id" :value="c.id">{{ c.full_name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Owner" :error="errors.owner_id?.[0]">
          <select :id="id" v-model="form.owner_id" class="field-input">
            <option value="">Unassigned</option>
            <option v-for="u in users ?? []" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Expected close date" :error="errors.expected_close_date?.[0]">
          <input :id="id" v-model="form.expected_close_date" type="date" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Source" :error="errors.source?.[0]">
          <input :id="id" v-model="form.source" type="text" class="field-input" />
        </FormField>
      </div>

      <FormField v-slot="{ id }" label="Description" :error="errors.description?.[0]">
        <textarea :id="id" v-model="form.description" rows="3" class="field-input"></textarea>
      </FormField>
    </form>

    <template #footer>
      <BaseButton variant="secondary" @click="emit('close')">Cancel</BaseButton>
      <BaseButton type="submit" form="deal-form" :loading="mutation.isPending.value">
        {{ deal ? 'Save changes' : 'Create deal' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
