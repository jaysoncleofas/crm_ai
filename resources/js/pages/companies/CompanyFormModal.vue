<script setup>
import { reactive, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'
import { useToast } from '@/composables/useToast'
import { Button, Dialog, Field, Input, Select, Textarea } from '@/components/catalyst'

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
  <Dialog :open="open" :title="company ? 'Edit company' : 'New company'" size="3xl" @close="emit('close')">
    <form id="company-form" class="space-y-4" novalidate @submit.prevent="mutation.mutate({ ...form })">
      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Company name" :error="errors.name?.[0]" required>
          <Input v-model="form.name" type="text"  required />
        </Field>

        <Field label="Domain" :error="errors.domain?.[0]">
          <Input v-model="form.domain" type="text"  placeholder="example.com" />
        </Field>

        <Field label="Industry" :error="errors.industry?.[0]">
          <Input v-model="form.industry" type="text"  />
        </Field>

        <Field label="Size" :error="errors.size?.[0]">
          <Select v-model="form.size">
            <option value="">Unknown</option>
            <option v-for="size in SIZES" :key="size" :value="size">{{ size }} employees</option>
          </Select>
        </Field>

        <Field label="Phone" :error="errors.phone?.[0]">
          <Input v-model="form.phone" type="tel"  />
        </Field>

        <Field label="Website" :error="errors.website?.[0]" description="Include https://">
          <Input v-model="form.website" type="url"  />
        </Field>

        <Field label="Owner" :error="errors.owner_id?.[0]">
          <Select v-model="form.owner_id">
            <option value="">Unassigned</option>
            <option v-for="person in users ?? []" :key="person.id" :value="person.id">{{ person.name }}</option>
          </Select>
        </Field>

        <Field label="Annual revenue (USD)" :error="errors.annual_revenue?.[0]">
          <Input v-model.number="form.annual_revenue" type="number" min="0"  />
        </Field>

        <Field label="Address" :error="errors.address_line1?.[0]">
          <Input v-model="form.address_line1" type="text"  />
        </Field>

        <Field label="City" :error="errors.city?.[0]">
          <Input v-model="form.city" type="text"  />
        </Field>

        <Field label="State" :error="errors.state?.[0]">
          <Input v-model="form.state" type="text"  />
        </Field>

        <Field label="Country" :error="errors.country?.[0]">
          <Input v-model="form.country" type="text"  />
        </Field>
      </div>

      <Field label="Description" :error="errors.description?.[0]">
        <Textarea v-model="form.description" :rows="3" />
      </Field>
    </form>

    <template #actions>
      <Button outline @click="emit('close')">Cancel</Button>
      <Button type="submit" form="company-form" :loading="mutation.isPending.value">
        {{ company ? 'Save changes' : 'Create company' }}
      </Button>
    </template>
  </Dialog>
</template>
