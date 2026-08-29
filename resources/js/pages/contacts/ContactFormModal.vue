<script setup>
import { reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import { humanize } from '@/lib/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'

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
  <BaseModal :open="open" :title="contact ? 'Edit contact' : 'New contact'" @close="emit('close')">
    <form id="contact-form" class="space-y-4" novalidate @submit.prevent="onSubmit">
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField v-slot="{ id, invalid }" label="First name" :error="errors.first_name?.[0]" required>
          <input :id="id" v-model="form.first_name" type="text" class="field-input" required :aria-invalid="invalid" />
        </FormField>

        <FormField v-slot="{ id, invalid }" label="Last name" :error="errors.last_name?.[0]" required>
          <input :id="id" v-model="form.last_name" type="text" class="field-input" required :aria-invalid="invalid" />
        </FormField>

        <FormField v-slot="{ id, invalid }" label="Email" :error="errors.email?.[0]">
          <input :id="id" v-model="form.email" type="email" class="field-input" :aria-invalid="invalid" />
        </FormField>

        <FormField v-slot="{ id }" label="Job title" :error="errors.job_title?.[0]">
          <input :id="id" v-model="form.job_title" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Phone" :error="errors.phone?.[0]">
          <input :id="id" v-model="form.phone" type="tel" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Mobile" :error="errors.mobile?.[0]">
          <input :id="id" v-model="form.mobile" type="tel" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Company" :error="errors.company_id?.[0]">
          <select :id="id" v-model="form.company_id" class="field-input">
            <option value="">No company</option>
            <option v-for="company in companies ?? []" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Owner" :error="errors.owner_id?.[0]">
          <select :id="id" v-model="form.owner_id" class="field-input">
            <option value="">Unassigned</option>
            <option v-for="person in users ?? []" :key="person.id" :value="person.id">{{ person.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Lifecycle stage" :error="errors.lifecycle_stage?.[0]">
          <select :id="id" v-model="form.lifecycle_stage" class="field-input">
            <option v-for="stage in LIFECYCLE_STAGES" :key="stage" :value="stage">{{ humanize(stage) }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Lead status" :error="errors.lead_status?.[0]">
          <select :id="id" v-model="form.lead_status" class="field-input">
            <option v-for="status in LEAD_STATUSES" :key="status" :value="status">{{ humanize(status) }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Lead score" :error="errors.lead_score?.[0]" hint="0–100">
          <input :id="id" v-model.number="form.lead_score" type="number" min="0" max="100" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Source" :error="errors.source?.[0]">
          <input :id="id" v-model="form.source" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="City" :error="errors.city?.[0]">
          <input :id="id" v-model="form.city" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Country" :error="errors.country?.[0]">
          <input :id="id" v-model="form.country" type="text" class="field-input" />
        </FormField>
      </div>

      <FormField v-slot="{ id }" label="Notes" :error="errors.notes?.[0]">
        <textarea :id="id" v-model="form.notes" rows="3" class="field-input"></textarea>
      </FormField>
    </form>

    <template #footer>
      <BaseButton variant="secondary" @click="emit('close')">Cancel</BaseButton>
      <BaseButton type="submit" form="contact-form" :loading="mutation.isPending.value">
        {{ contact ? 'Save changes' : 'Create contact' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
