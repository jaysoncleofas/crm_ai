<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import AuthLayout from '@/components/catalyst/AuthLayout.vue'
import { Button, Field, Heading, Input, Strong, Text, TextLink } from '@/components/catalyst'

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
    if (error.status === 422) errors.value = error.errors
    else formError.value = error.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <form class="grid w-full max-w-sm grid-cols-1 gap-8" novalidate @submit.prevent="onSubmit">
      <Heading>Create your account</Heading>

      <div
        v-if="formError"
        class="rounded-lg bg-red-500/10 px-3 py-2 text-sm/6 text-red-700 dark:text-red-400"
        role="alert"
      >
        {{ formError }}
      </div>

      <Field label="Full name" :error="errors.name?.[0]" required>
        <Input v-model="form.name" autocomplete="name" required />
      </Field>

      <Field label="Email" :error="errors.email?.[0]" required>
        <Input v-model="form.email" type="email" autocomplete="email" required />
      </Field>

      <Field
        label="Password"
        :error="errors.password?.[0]"
        description="At least 8 characters, with letters and numbers."
        required
      >
        <Input v-model="form.password" type="password" autocomplete="new-password" required />
      </Field>

      <Field label="Confirm password" required>
        <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
      </Field>

      <Button type="submit" class="w-full" :loading="submitting">Create account</Button>

      <Text>
        Already have an account?
        <TextLink to="/login"><Strong>Sign in</Strong></TextLink>
      </Text>
    </form>
  </AuthLayout>
</template>
