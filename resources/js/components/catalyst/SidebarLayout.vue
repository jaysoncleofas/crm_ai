<script setup>
import { ref } from 'vue'

const mobileOpen = ref(false)
</script>

<template>
  <!--
    Catalyst's signature frame: a tinted page, a flush sidebar, and the content
    floating in a rounded white panel with a hairline ring.
  -->
  <div class="relative isolate flex min-h-svh w-full bg-white max-lg:flex-col lg:bg-zinc-100 dark:bg-zinc-900 dark:lg:bg-zinc-950">
    <!-- Desktop sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 max-lg:hidden">
      <slot name="sidebar" />
    </div>

    <!-- Mobile sidebar -->
    <Teleport to="body">
      <div v-if="mobileOpen" class="lg:hidden">
        <div class="fixed inset-0 z-40 bg-black/30" @click="mobileOpen = false" />
        <div class="fixed inset-y-0 left-0 z-50 w-full max-w-80 p-2">
          <div class="flex h-full flex-col rounded-lg bg-white shadow-xs ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
            <div class="px-4 pt-3">
              <button
                type="button"
                class="rounded-lg p-2 text-zinc-950 hover:bg-zinc-950/5 dark:text-white dark:hover:bg-white/5"
                aria-label="Close navigation"
                @click="mobileOpen = false"
              >
                <svg class="size-5 stroke-current" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M5 5l10 10M15 5L5 15" stroke-width="1.5" stroke-linecap="round" />
                </svg>
              </button>
            </div>
            <slot name="sidebar" :mobile="true" />
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Mobile top bar -->
    <header class="flex items-center px-4 lg:hidden">
      <div class="py-2.5">
        <button
          type="button"
          class="rounded-lg p-2 text-zinc-950 hover:bg-zinc-950/5 dark:text-white dark:hover:bg-white/5"
          aria-label="Open navigation"
          :aria-expanded="mobileOpen"
          @click="mobileOpen = true"
        >
          <svg class="size-5 stroke-current" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M2 6.75h16M2 13.25h16" stroke-width="1.5" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <div class="min-w-0 flex-1"><slot name="navbar" /></div>
    </header>

    <main class="flex flex-1 flex-col pb-2 lg:min-w-0 lg:pt-2 lg:pr-2 lg:pl-64">
      <div class="grow p-6 lg:rounded-lg lg:bg-white lg:p-10 lg:shadow-xs lg:ring-1 lg:ring-zinc-950/5 dark:lg:bg-zinc-900 dark:lg:ring-white/10">
        <div class="mx-auto max-w-6xl">
          <slot />
        </div>
      </div>
    </main>
  </div>
</template>
