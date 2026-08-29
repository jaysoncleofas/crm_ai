<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useQueryClient } from '@tanstack/vue-query'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'

const { user, can, logout } = useAuth()
const router = useRouter()
const toast = useToast()
const queryClient = useQueryClient()

const mobileNavOpen = ref(false)
const signingOut = ref(false)

// Nav is permission-driven, never role-name driven.
const NAV = [
  { to: '/', label: 'Dashboard', icon: '▤', permission: null },
  { to: '/contacts', label: 'Contacts', icon: '👤', permission: 'contacts.view' },
  { to: '/companies', label: 'Companies', icon: '🏢', permission: 'companies.view' },
  { to: '/deals', label: 'Deals', icon: '💼', permission: 'deals.view' },
  { to: '/pipeline', label: 'Pipeline', icon: '▦', permission: 'deals.view' },
  { to: '/activities', label: 'Activities', icon: '🗓', permission: 'activities.view' },
  { to: '/users', label: 'Team', icon: '👥', permission: 'users.view' },
  { to: '/audit-log', label: 'Audit log', icon: '🕘', permission: 'audit-log.view' },
]

function visible(item) {
  return item.permission === null || can(item.permission)
}

async function onSignOut() {
  signingOut.value = true
  try {
    await logout()
    queryClient.clear()
    toast.success('Signed out.')
    router.push('/login')
  } catch {
    toast.error('Could not sign out. Please try again.')
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div class="min-h-screen lg:flex">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow">
      Skip to content
    </a>

    <!-- Sidebar -->
    <aside
      class="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:shrink-0 lg:border-b-0 lg:border-r"
      :class="mobileNavOpen ? 'block' : ''"
    >
      <div class="flex items-center justify-between px-4 py-4 lg:block">
        <RouterLink to="/" class="flex items-center gap-2 font-semibold text-slate-900">
          <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm text-white" aria-hidden="true">C</span>
          <span>Jayson CRM</span>
        </RouterLink>

        <button
          type="button"
          class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
          :aria-expanded="mobileNavOpen"
          aria-controls="main-nav"
          @click="mobileNavOpen = !mobileNavOpen"
        >
          <span class="sr-only">Toggle navigation</span>
          <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 5.5A.5.5 0 013.5 5h13a.5.5 0 010 1h-13a.5.5 0 01-.5-.5zM3 10a.5.5 0 01.5-.5h13a.5.5 0 010 1h-13A.5.5 0 013 10zm.5 4a.5.5 0 000 1h13a.5.5 0 000-1h-13z" />
          </svg>
        </button>
      </div>

      <nav
        id="main-nav"
        class="px-2 pb-4 lg:block"
        :class="mobileNavOpen ? 'block' : 'hidden'"
        aria-label="Main"
      >
        <ul class="space-y-1">
          <li v-for="item in NAV.filter(visible)" :key="item.to">
            <RouterLink
              :to="item.to"
              class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
              active-class="bg-indigo-50 text-indigo-700"
              @click="mobileNavOpen = false"
            >
              <span class="w-4 text-center" aria-hidden="true">{{ item.icon }}</span>
              {{ item.label }}
            </RouterLink>
          </li>
        </ul>
      </nav>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-30 flex items-center justify-end gap-3 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
        <div class="text-right">
          <p class="text-sm font-medium text-slate-900">{{ user?.name }}</p>
          <p class="text-xs text-slate-500">{{ user?.roles?.join(', ') }}</p>
        </div>
        <BaseButton variant="secondary" size="sm" :loading="signingOut" @click="onSignOut">Sign out</BaseButton>
      </header>

      <main id="main" class="flex-1 p-4 sm:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
