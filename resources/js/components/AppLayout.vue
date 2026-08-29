<script setup>
import { computed, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { useQueryClient } from '@tanstack/vue-query'
import {
  BuildingOffice2Icon,
  BriefcaseIcon,
  CalendarDaysIcon,
  ClockIcon,
  Cog6ToothIcon,
  HomeIcon,
  UserGroupIcon,
  UsersIcon,
  ViewColumnsIcon,
} from '@heroicons/vue/20/solid'
import { ComputerDesktopIcon, MoonIcon, SunIcon } from '@heroicons/vue/16/solid'
import { initials } from '@/lib/format'
import { useAuth } from '@/composables/useAuth'
import { useTheme } from '@/composables/useTheme'
import { useToast } from '@/composables/useToast'
import {
  Avatar,
  Dropdown,
  DropdownItem,
  Sidebar,
  SidebarBody,
  SidebarFooter,
  SidebarHeader,
  SidebarHeading,
  SidebarItem,
  SidebarLabel,
  SidebarLayout,
  SidebarSection,
  SidebarSpacer,
} from '@/components/catalyst'

const { user, can, logout } = useAuth()
const { theme, setTheme } = useTheme()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const queryClient = useQueryClient()

const signingOut = ref(false)

// Navigation is permission-driven, never role-name driven.
const SECTIONS = [
  {
    heading: null,
    items: [{ to: '/', label: 'Dashboard', icon: HomeIcon, permission: null, exact: true }],
  },
  {
    heading: 'Records',
    items: [
      { to: '/contacts', label: 'Contacts', icon: UsersIcon, permission: 'contacts.view' },
      { to: '/companies', label: 'Companies', icon: BuildingOffice2Icon, permission: 'companies.view' },
      { to: '/deals', label: 'Deals', icon: BriefcaseIcon, permission: 'deals.view' },
      { to: '/pipeline', label: 'Pipeline', icon: ViewColumnsIcon, permission: 'deals.view' },
      { to: '/activities', label: 'Activities', icon: CalendarDaysIcon, permission: 'activities.view' },
    ],
  },
  {
    heading: 'Workspace',
    items: [
      { to: '/users', label: 'Team', icon: UserGroupIcon, permission: 'users.view' },
      { to: '/audit-log', label: 'Audit log', icon: ClockIcon, permission: 'audit-log.view' },
    ],
  },
]

const sections = computed(() =>
  SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) => item.permission === null || can(item.permission)),
  })).filter((section) => section.items.length > 0),
)

function isCurrent(item) {
  return item.exact ? route.path === item.to : route.path.startsWith(item.to)
}

const THEMES = [
  { value: 'light', label: 'Light', icon: SunIcon },
  { value: 'dark', label: 'Dark', icon: MoonIcon },
  { value: 'system', label: 'System', icon: ComputerDesktopIcon },
]

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
  <SidebarLayout>
    <template #sidebar>
      <Sidebar>
        <SidebarHeader>
          <div class="flex items-center gap-3 px-2 py-1">
            <span
              class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900"
              aria-hidden="true"
            >
              C
            </span>
            <span class="min-w-0">
              <span class="block truncate text-sm/5 font-semibold text-zinc-950 dark:text-white">Jayson CRM</span>
              <span class="block truncate text-xs/5 text-zinc-500 dark:text-zinc-400">Sales workspace</span>
            </span>
          </div>
        </SidebarHeader>

        <SidebarBody>
          <SidebarSection v-for="(section, index) in sections" :key="section.heading ?? index">
            <SidebarHeading v-if="section.heading">{{ section.heading }}</SidebarHeading>
            <SidebarItem v-for="item in section.items" :key="item.to" :to="item.to" :current="isCurrent(item)">
              <component :is="item.icon" class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
              <SidebarLabel>{{ item.label }}</SidebarLabel>
            </SidebarItem>
          </SidebarSection>

          <SidebarSpacer />

          <SidebarSection>
            <SidebarHeading>Appearance</SidebarHeading>
            <div
              class="flex gap-1 rounded-lg bg-zinc-950/5 p-1 dark:bg-white/5"
              role="radiogroup"
              aria-label="Colour theme"
            >
              <button
                v-for="option in THEMES"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="theme === option.value"
                :title="option.label"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs/5 font-medium transition"
                :class="
                  theme === option.value
                    ? 'bg-white text-zinc-950 shadow-xs ring-1 ring-zinc-950/5 dark:bg-zinc-700 dark:text-white dark:ring-white/10'
                    : 'text-zinc-500 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white'
                "
                @click="setTheme(option.value)"
              >
                <component :is="option.icon" class="size-4" aria-hidden="true" />
                <span class="sr-only">{{ option.label }}</span>
              </button>
            </div>
          </SidebarSection>
        </SidebarBody>

        <SidebarFooter>
          <Dropdown align="left" class="w-full">
            <template #trigger>
              <button
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left hover:bg-zinc-950/5 dark:hover:bg-white/5"
              >
                <Avatar :initials="initials(user?.name)" :alt="user?.name" square size="lg" />
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm/5 font-medium text-zinc-950 dark:text-white">{{ user?.name }}</span>
                  <span class="block truncate text-xs/5 text-zinc-500 dark:text-zinc-400">{{ user?.email }}</span>
                </span>
                <svg class="size-4 shrink-0 stroke-zinc-400" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M5.75 10.75 8 13l2.25-2.25M5.75 5.25 8 3l2.25 2.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </template>

            <div class="px-3.5 py-2 sm:px-3">
              <p class="text-xs/5 text-zinc-500 dark:text-zinc-400">Signed in as</p>
              <p class="truncate text-sm/5 font-medium text-zinc-950 dark:text-white">{{ user?.email }}</p>
              <p class="mt-0.5 text-xs/5 text-zinc-500 capitalize dark:text-zinc-400">
                {{ (user?.roles ?? []).join(', ').replace('_', ' ') }}
              </p>
            </div>
            <hr class="my-1 border-zinc-950/5 dark:border-white/10" />
            <DropdownItem v-if="can('users.view')" to="/users">
              <Cog6ToothIcon class="size-4 shrink-0 text-zinc-400 group-hover:text-white" aria-hidden="true" />
              Team settings
            </DropdownItem>
            <DropdownItem @click="onSignOut">
              <span class="size-4 shrink-0" aria-hidden="true" />
              {{ signingOut ? 'Signing out…' : 'Sign out' }}
            </DropdownItem>
          </Dropdown>
        </SidebarFooter>
      </Sidebar>
    </template>

    <template #navbar>
      <div class="flex items-center justify-end gap-2 py-2.5">
        <Avatar :initials="initials(user?.name)" :alt="user?.name" square size="sm" />
      </div>
    </template>

    <RouterView />
  </SidebarLayout>
</template>
