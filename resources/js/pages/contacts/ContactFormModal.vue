<script setup>
import { reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import { humanize } from '@/lib/format'
import { Button, Dialog, Field, Input, Select, Textarea } from '@/components/catalyst'

const props = defineProps({
  open: { type: Boolean, default: false },
  contact: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const toast = useToast()
const queryClient = useQueryClient()
const errors = ref({})

const LIFECYCLE_STAGES = [
  'subscriber', 'lead', 'marketing_qualified_lead', 'sales_qualified_lead',
  'opportunity', 'customer', 'evangelist', 'other',
]
const LEAD_STATUSES = [
  'new', 'open', 'in_progress', 'open_deal', 'unqualified',
  'attempted_to_contact', 'connected', 'bad_timing',
]

const form = reactive({
  first_name: '', last_name: '', email: '', phone: '', mobile: '', job_title: '',
  company_id: '', owner_id: '', lifecycle_stage: 'lead', lead_status: 'new',
  lead_score: 0, source: '', city: '', state: '', country: '', notes: '',
})

// Reference lists for the selects — cached far longer than list data.
const { data: companies } = useQuery({
  queryKey: ['companies', 'options'],
  queryFn: async () => (await api.get('/companies', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

const { data: users } = useQuery({
  queryKey: ['users', 'options'],
  queryFn: async () => (await api.get('/users', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

watch(
  () => [props.open, props.contact],
  () => {
    if (!props.open) return
    errors.value = {}

    const source = props.contact ?? {}
    Object.assign(form, {
      first_name: source.first_name ?? '',
      last_name: source.last_name ?? '',
      email: source.email ?? '',
      phone: source.phone ?? '',
      mobile: source.mobile ?? '',
      job_title: source.job_title ?? '',
      company_id: source.company_id ?? '',
      owner_id: source.owner_id ?? '',
      lifecycle_stage: source.lifecycle_stage ?? 'lead',
      lead_status: source.lead_status ?? 'new',
      lead_score: source.lead_score ?? 0,
      source: source.source ?? '',
      city: source.city ?? '',
      state: source.state ?? '',
      country: source.country ?? '',
      notes: source.notes ?? '',
    })
  },
  { immediate: true },
)

const mutation = useMutation({
  mutationFn: async (payload) => {
    const body = { ...payload, company_id: payload.company_id || null, owner_id: payload.owner_id || null }

    return props.contact
      ? (await api.patch(`/contacts/${props.contact.id}`, body)).data.data
      : (await api.post('/contacts', body)).data.data
  },
  onSuccess(saved) {
    // Refresh every contacts list/detail view, plus aggregates that count them.
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success(props.contact ? 'Contact updated.' : 'Contact created.')
    emit('saved', saved)
    emit('close')
  },
  onError(error) {
    if (error.status === 422) {
      errors.value = error.errors
    }
  },
})

function onSubmit() {
  errors.value = {}
  mutation.mutate({ ...form })
}
</script>

<template>
  <Dialog :open="open" :title="contact ? 'Edit contact' : 'New contact'" size="3xl" @close="emit('close')">
    <form id="contact-form" class="space-y-4" novalidate @submit.prevent="onSubmit">
      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="First name" :error="errors.first_name?.[0]" required>
          <Input v-model="form.first_name" type="text" required />
        </Field>

        <Field label="Last name" :error="errors.last_name?.[0]" required>
          <Input v-model="form.last_name" type="text" required />
        </Field>

        <Field label="Email" :error="errors.email?.[0]">
          <Input v-model="form.email" type="email" />
        </Field>

        <Field label="Job title" :error="errors.job_title?.[0]">
          <Input v-model="form.job_title" type="text" />
        </Field>

        <Field label="Phone" :error="errors.phone?.[0]">
          <Input v-model="form.phone" type="tel" />
        </Field>

        <Field label="Mobile" :error="errors.mobile?.[0]">
          <Input v-model="form.mobile" type="tel" />
        </Field>

        <Field label="Company" :error="errors.company_id?.[0]">
          <Select v-model="form.company_id">
            <option value="">No company</option>
            <option v-for="company in companies ?? []" :key="company.id" :value="company.id">{{ company.name }}</option>
          </Select>
        </Field>

        <Field label="Owner" :error="errors.owner_id?.[0]">
          <Select v-model="form.owner_id">
            <option value="">Unassigned</option>
            <option v-for="person in users ?? []" :key="person.id" :value="person.id">{{ person.name }}</option>
          </Select>
        </Field>

        <Field label="Lifecycle stage" :error="errors.lifecycle_stage?.[0]">
          <Select v-model="form.lifecycle_stage">
            <option v-for="stage in LIFECYCLE_STAGES" :key="stage" :value="stage">{{ humanize(stage) }}</option>
          </Select>
        </Field>

        <Field label="Lead status" :error="errors.lead_status?.[0]">
          <Select v-model="form.lead_status">
            <option v-for="status in LEAD_STATUSES" :key="status" :value="status">{{ humanize(status) }}</option>
          </Select>
        </Field>

        <Field label="Lead score" :error="errors.lead_score?.[0]" description="0–100">
          <Input v-model.number="form.lead_score" type="number" min="0" max="100"  />
        </Field>

        <Field label="Source" :error="errors.source?.[0]">
          <Input v-model="form.source" type="text" />
        </Field>

        <Field label="City" :error="errors.city?.[0]">
          <Input v-model="form.city" type="text" />
        </Field>

        <Field label="Country" :error="errors.country?.[0]">
          <Input v-model="form.country" type="text" />
        </Field>
      </div>

      <Field label="Notes" :error="errors.notes?.[0]">
        <Textarea v-model="form.notes" :rows="3" />
      </Field>
    </form>

    <template #actions>
      <Button outline @click="emit('close')">Cancel</Button>
      <Button type="submit" form="contact-form" :loading="mutation.isPending.value">
        {{ contact ? 'Save changes' : 'Create contact' }}
      </Button>
    </template>
  </Dialog>
</template>
