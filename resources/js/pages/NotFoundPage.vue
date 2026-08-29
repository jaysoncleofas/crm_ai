<script setup>
import AuthLayout from '@/components/catalyst/AuthLayout.vue'
import { Button, Heading, Text } from '@/components/catalyst'
</script>

<template>
  <AuthLayout>
    <div class="flex max-w-sm flex-col items-center gap-4 text-center">
      <p class="text-sm/6 font-semibold text-zinc-500 dark:text-zinc-400">404</p>
      <Heading>Page not found</Heading>
      <Text>The page you were looking for doesn't exist or has moved.</Text>
      <Button to="/" class="mt-2">Back to dashboard</Button>
    </div>
  </AuthLayout>
</template>
