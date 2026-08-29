<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormField from '@/components/ui/FormField.vue'

const { login } = useAuth()
const router = useRouter()
const route = useRoute()
const toast = useToast()

const form = reactive({ email: '', password: '', remember: false })
const errors = ref({})
const formError = ref(null)
const submitting = ref(false)

async function onSubmit() {
  submitting.value = true
  errors.value = {}
  formError.value = null

  try {
    await login({ ...form })
    toast.success('Welcome back.')
    router.push(route.query.redirect || '/')
  } catch (error) {
    if (error.status === 422) {
      errors.value = error.errors
      formError.value = error.fieldError('email') ?? error.message
    } else if (error.status === 429) {
      formError.value = `Too many attempts.${error.retryAfter ? ` Try again in ${error.retryAfter}s.` : ''}`
    } else {
      formError.value = error.message
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-6 text-center">
        <span class="mx-auto mb-3 flex size-11 items-center justify-center rounded-xl bg-indigo-600 text-lg font-semibold text-white" aria-hidden="true">C</span>
        <h1 class="text-xl font-semibold text-slate-900">Sign in to Jayson CRM</h1>
        <p class="mt-1 text-sm text-slate-500">Use your team account to continue.</p>
      </div>

      <form class="card space-y-4 p-6" novalidate @submit.prevent="onSubmit">
        <div v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {{ formError }}
        </div>

        <FormField v-slot="{ id, invalid, describedby }" label="Email" :error="errors.email?.[0]" required>
          <input
            :id="id"
            v-model="form.email"
            type="email"
            class="field-input"
            autocomplete="email"
            required
            :aria-invalid="invalid"
            :aria-describedby="describedby"
          />
        </FormField>

        <FormField v-slot="{ id, invalid, describedby }" label="Password" :error="errors.password?.[0]" required>
          <input
            :id="id"
            v-model="form.password"
            type="password"
            class="field-input"
            autocomplete="current-password"
            required
            :aria-invalid="invalid"
            :aria-describedby="describedby"
          />
        </FormField>

        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600" />
          Keep me signed in
        </label>

        <BaseButton type="submit" class="w-full" :loading="submitting">Sign in</BaseButton>

        <p class="text-center text-sm text-slate-500">
          No account?
          <RouterLink to="/register" class="font-medium text-indigo-600 hover:underline">Create one</RouterLink>
        </p>
      </form>

      <div class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-4 text-xs text-slate-500">
        <p class="mb-1 font-semibold text-slate-700">Demo accounts</p>
        <p>admin@crm.test · manager@crm.test · rep@crm.test · viewer@crm.test</p>
        <p class="mt-1">Password for all: <code class="rounded bg-slate-100 px-1 py-0.5">password</code></p>
      </div>
    </div>
  </div>
</template>
