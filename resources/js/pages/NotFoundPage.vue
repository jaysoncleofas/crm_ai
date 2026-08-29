<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-3 px-4 text-center">
    <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">404</p>
    <h1 class="text-2xl font-semibold text-slate-900">Page not found</h1>
    <p class="text-sm text-slate-500">The page you were looking for doesn't exist or has moved.</p>
    <RouterLink to="/" class="mt-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
      Back to dashboard
    </RouterLink>
  </div>
</template>
