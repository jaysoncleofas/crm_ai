<script setup>
import { reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import FormField from '@/components/ui/FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  company: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const toast = useToast()
const queryClient = useQueryClient()
const errors = ref({})

const SIZES = ['1-10', '11-50', '51-200', '201-1000', '1000+']

const form = reactive({
  name: '', domain: '', industry: '', size: '', phone: '', website: '',
  address_line1: '', city: '', state: '', postal_code: '', country: '',
  annual_revenue: null, description: '', owner_id: '',
})

const { data: users } = useQuery({
  queryKey: ['users', 'options'],
  queryFn: async () => (await api.get('/users', { params: { per_page: 100, sort: 'name' } })).data.data,
  staleTime: STALE.reference,
  enabled: () => props.open,
})

watch(
  () => [props.open, props.company],
  () => {
    if (!props.open) return
    errors.value = {}

    const s = props.company ?? {}
    Object.assign(form, {
      name: s.name ?? '',
      domain: s.domain ?? '',
      industry: s.industry ?? '',
      size: s.size ?? '',
      phone: s.phone ?? '',
      website: s.website ?? '',
      address_line1: s.address?.line1 ?? '',
      city: s.address?.city ?? '',
      state: s.address?.state ?? '',
      postal_code: s.address?.postal_code ?? '',
      country: s.address?.country ?? '',
      annual_revenue: s.annual_revenue ?? null,
      description: s.description ?? '',
      owner_id: s.owner_id ?? '',
    })
  },
  { immediate: true },
)

const mutation = useMutation({
  mutationFn: async (payload) => {
    const body = { ...payload, owner_id: payload.owner_id || null, website: payload.website || null }

    return props.company
      ? (await api.patch(`/companies/${props.company.id}`, body)).data.data
      : (await api.post('/companies', body)).data.data
  },
  onSuccess(saved) {
    queryClient.invalidateQueries({ queryKey: ['companies'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success(props.company ? 'Company updated.' : 'Company created.')
    emit('saved', saved)
    emit('close')
  },
  onError(error) {
    if (error.status === 422) errors.value = error.errors
  },
})
</script>

<template>
  <BaseModal :open="open" :title="company ? 'Edit company' : 'New company'" @close="emit('close')">
    <form id="company-form" class="space-y-4" novalidate @submit.prevent="mutation.mutate({ ...form })">
      <div class="grid gap-4 sm:grid-cols-2">
        <FormField v-slot="{ id, invalid }" label="Company name" :error="errors.name?.[0]" required>
          <input :id="id" v-model="form.name" type="text" class="field-input" required :aria-invalid="invalid" />
        </FormField>

        <FormField v-slot="{ id }" label="Domain" :error="errors.domain?.[0]">
          <input :id="id" v-model="form.domain" type="text" class="field-input" placeholder="example.com" />
        </FormField>

        <FormField v-slot="{ id }" label="Industry" :error="errors.industry?.[0]">
          <input :id="id" v-model="form.industry" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Size" :error="errors.size?.[0]">
          <select :id="id" v-model="form.size" class="field-input">
            <option value="">Unknown</option>
            <option v-for="size in SIZES" :key="size" :value="size">{{ size }} employees</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Phone" :error="errors.phone?.[0]">
          <input :id="id" v-model="form.phone" type="tel" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Website" :error="errors.website?.[0]" hint="Include https://">
          <input :id="id" v-model="form.website" type="url" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Owner" :error="errors.owner_id?.[0]">
          <select :id="id" v-model="form.owner_id" class="field-input">
            <option value="">Unassigned</option>
            <option v-for="person in users ?? []" :key="person.id" :value="person.id">{{ person.name }}</option>
          </select>
        </FormField>

        <FormField v-slot="{ id }" label="Annual revenue (USD)" :error="errors.annual_revenue?.[0]">
          <input :id="id" v-model.number="form.annual_revenue" type="number" min="0" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Address" :error="errors.address_line1?.[0]">
          <input :id="id" v-model="form.address_line1" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="City" :error="errors.city?.[0]">
          <input :id="id" v-model="form.city" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="State" :error="errors.state?.[0]">
          <input :id="id" v-model="form.state" type="text" class="field-input" />
        </FormField>

        <FormField v-slot="{ id }" label="Country" :error="errors.country?.[0]">
          <input :id="id" v-model="form.country" type="text" class="field-input" />
        </FormField>
      </div>

      <FormField v-slot="{ id }" label="Description" :error="errors.description?.[0]">
        <textarea :id="id" v-model="form.description" rows="3" class="field-input"></textarea>
      </FormField>
    </form>

    <template #footer>
      <BaseButton variant="secondary" @click="emit('close')">Cancel</BaseButton>
      <BaseButton type="submit" form="company-form" :loading="mutation.isPending.value">
        {{ company ? 'Save changes' : 'Create company' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
