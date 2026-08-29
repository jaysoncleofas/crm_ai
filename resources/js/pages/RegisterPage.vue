<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormField from '@/components/ui/FormField.vue'

const { register } = useAuth()
const router = useRouter()
const toast = useToast()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref({})
const formError = ref(null)
const submitting = ref(false)

async function onSubmit() {
  submitting.value = true
  errors.value = {}
  formError.value = null

  try {
    await register({ ...form })
    toast.success('Account created.')
    router.push('/')
  } catch (error) {
    if (error.status === 422) {
      errors.value = error.errors
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
      <h1 class="mb-6 text-center text-xl font-semibold text-slate-900">Create your account</h1>

      <form class="card space-y-4 p-6" novalidate @submit.prevent="onSubmit">
        <div v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {{ formError }}
        </div>

        <FormField v-slot="{ id, invalid, describedby }" label="Full name" :error="errors.name?.[0]" required>
          <input :id="id" v-model="form.name" type="text" class="field-input" autocomplete="name" required :aria-invalid="invalid" :aria-describedby="describedby" />
        </FormField>

        <FormField v-slot="{ id, invalid, describedby }" label="Email" :error="errors.email?.[0]" required>
          <input :id="id" v-model="form.email" type="email" class="field-input" autocomplete="email" required :aria-invalid="invalid" :aria-describedby="describedby" />
        </FormField>

        <FormField
          v-slot="{ id, invalid, describedby }"
          label="Password"
          :error="errors.password?.[0]"
          hint="At least 8 characters, with letters and numbers."
          required
        >
          <input :id="id" v-model="form.password" type="password" class="field-input" autocomplete="new-password" required :aria-invalid="invalid" :aria-describedby="describedby" />
        </FormField>

        <FormField v-slot="{ id }" label="Confirm password" required>
          <input :id="id" v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required />
        </FormField>

        <BaseButton type="submit" class="w-full" :loading="submitting">Create account</BaseButton>

        <p class="text-center text-sm text-slate-500">
          Already have an account?
          <RouterLink to="/login" class="font-medium text-indigo-600 hover:underline">Sign in</RouterLink>
        </p>
      </form>
    </div>
  </div>
</template>
