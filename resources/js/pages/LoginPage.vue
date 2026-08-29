<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import AuthLayout from '@/components/catalyst/AuthLayout.vue'
import { Button, Checkbox, Field, Heading, Input, Strong, Text, TextLink } from '@/components/catalyst'

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
  <AuthLayout>
    <form class="grid w-full max-w-sm grid-cols-1 gap-8" novalidate @submit.prevent="onSubmit">
      <div class="flex flex-col items-start gap-6">
        <span
          class="flex size-10 items-center justify-center rounded-xl bg-zinc-900 text-base font-semibold text-white dark:bg-white dark:text-zinc-900"
          aria-hidden="true"
        >
          C
        </span>
        <div class="space-y-1">
          <Heading>Sign in to Jayson CRM</Heading>
          <Text>Use your team account to continue.</Text>
        </div>
      </div>

      <div
        v-if="formError"
        class="rounded-lg bg-red-500/10 px-3 py-2 text-sm/6 text-red-700 dark:text-red-400"
        role="alert"
      >
        {{ formError }}
      </div>

      <Field label="Email" :error="errors.email?.[0]" required>
        <Input v-model="form.email" type="email" autocomplete="email" required />
      </Field>

      <Field label="Password" :error="errors.password?.[0]" required>
        <Input v-model="form.password" type="password" autocomplete="current-password" required />
      </Field>

      <Checkbox v-model="form.remember" label="Keep me signed in" />

      <Button type="submit" class="w-full" :loading="submitting">Sign in</Button>

      <Text>
        No account?
        <TextLink to="/register"><Strong>Create one</Strong></TextLink>
      </Text>

      <div class="rounded-lg border border-dashed border-zinc-950/10 p-4 dark:border-white/10">
        <p class="text-xs/5 font-medium text-zinc-950 dark:text-white">Demo accounts</p>
        <p class="mt-1 text-xs/5 text-zinc-500 dark:text-zinc-400">
          admin@crm.test · manager@crm.test · rep@crm.test · viewer@crm.test
        </p>
        <p class="mt-1 text-xs/5 text-zinc-500 dark:text-zinc-400">
          Password for all:
          <code class="rounded bg-zinc-950/5 px-1 py-0.5 font-mono dark:bg-white/10">password</code>
        </p>
      </div>
    </form>
  </AuthLayout>
</template>
