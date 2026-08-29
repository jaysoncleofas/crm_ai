<script setup>
import { reactive, ref, watch } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'
import { useToast } from '@/composables/useToast'
import { humanize } from '@/lib/format'
import { Button, Dialog, Field, Input, Select, Textarea } from '@/components/catalyst'

const props = defineProps({
  open: { type: Boolean, default: false },
  activity: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const toast = useToast()
const queryClient = useQueryClient()
const errors = ref({})

const TYPES = ['call', 'email', 'meeting', 'note', 'task']
const STATUSES = ['planned', 'completed', 'canceled']

const form = reactive({
  type: 'task', subject: '', body: '', status: 'planned',
  direction: '', outcome: '', location: '', duration_minutes: null, due_at: '',
})

/** <input type="datetime-local"> needs "YYYY-MM-DDTHH:mm" in local time. */
function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

watch(
  () => [props.open, props.activity],
  () => {
    if (!props.open) return
    errors.value = {}

    const s = props.activity ?? {}
    Object.assign(form, {
      type: s.type ?? 'task',
      subject: s.subject ?? '',
      body: s.body ?? '',
      status: s.status ?? 'planned',
      direction: s.direction ?? '',
      outcome: s.outcome ?? '',
      location: s.location ?? '',
      duration_minutes: s.duration_minutes ?? null,
      due_at: toLocalInput(s.due_at),
    })
  },
  { immediate: true },
)

const mutation = useMutation({
  mutationFn: async (payload) => {
    const body = {
      ...payload,
      direction: payload.direction || null,
      due_at: payload.due_at ? new Date(payload.due_at).toISOString() : null,
      duration_minutes: payload.duration_minutes || null,
    }

    return props.activity
      ? (await api.patch(`/activities/${props.activity.id}`, body)).data.data
      : (await api.post('/activities', body)).data.data
  },
  onSuccess() {
    queryClient.invalidateQueries({ queryKey: ['activities'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    toast.success(props.activity ? 'Activity updated.' : 'Activity logged.')
    emit('close')
  },
  onError(error) {
    if (error.status === 422) errors.value = error.errors
  },
})
</script>

<template>
  <Dialog :open="open" :title="activity ? 'Edit activity' : 'Log activity'" size="2xl" @close="emit('close')">
    <form id="activity-form" class="space-y-4" novalidate @submit.prevent="mutation.mutate({ ...form })">
      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Type" :error="errors.type?.[0]" required>
          <Select v-model="form.type">
            <option v-for="t in TYPES" :key="t" :value="t">{{ humanize(t) }}</option>
          </Select>
        </Field>

        <Field label="Status" :error="errors.status?.[0]">
          <Select v-model="form.status">
            <option v-for="s in STATUSES" :key="s" :value="s">{{ humanize(s) }}</option>
          </Select>
        </Field>
      </div>

      <Field label="Subject" :error="errors.subject?.[0]" required>
        <Input v-model="form.subject" type="text"  required />
      </Field>

      <Field label="Notes" :error="errors.body?.[0]">
        <Textarea v-model="form.body" :rows="3" />
      </Field>

      <div class="grid gap-4 sm:grid-cols-2">
        <Field label="Due" :error="errors.due_at?.[0]">
          <Input v-model="form.due_at" type="datetime-local"  />
        </Field>

        <Field label="Duration (minutes)" :error="errors.duration_minutes?.[0]">
          <Input v-model.number="form.duration_minutes" type="number" min="0"  />
        </Field>

        <Field v-if="['call', 'email'].includes(form.type)" v-slot="{ id }" label="Direction" :error="errors.direction?.[0]">
          <Select v-model="form.direction">
            <option value="">Not set</option>
            <option value="inbound">Inbound</option>
            <option value="outbound">Outbound</option>
          </Select>
        </Field>

        <Field v-if="form.type === 'meeting'" v-slot="{ id }" label="Location" :error="errors.location?.[0]">
          <Input v-model="form.location" type="text"  />
        </Field>

        <Field label="Outcome" :error="errors.outcome?.[0]">
          <Input v-model="form.outcome" type="text"  />
        </Field>
      </div>
    </form>

    <template #actions>
      <Button outline @click="emit('close')">Cancel</Button>
      <Button type="submit" form="activity-form" :loading="mutation.isPending.value">
        {{ activity ? 'Save changes' : 'Log activity' }}
      </Button>
    </template>
  </Dialog>
</template>
