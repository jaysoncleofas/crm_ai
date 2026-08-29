<script setup>
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: null },
  size: { type: String, default: 'lg' },
})
const emit = defineEmits(['close'])

const panel = ref(null)
const titleId = useId()

const SIZES = {
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-md',
  lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl',
  '2xl': 'sm:max-w-2xl',
  '3xl': 'sm:max-w-3xl',
}

function onKeydown(event) {
  if (!props.open) return

  if (event.key === 'Escape') {
    emit('close')
    return
  }

  // Keep Tab inside the dialog while it is open.
  if (event.key === 'Tab' && panel.value) {
    const focusables = panel.value.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )
    if (focusables.length === 0) return

    const first = focusables[0]
    const last = focusables[focusables.length - 1]

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
    if (isOpen) {
      await new Promise((resolve) => requestAnimationFrame(resolve))
      panel.value?.querySelector('input, select, textarea, button')?.focus()
    }
  },
)

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 w-screen overflow-y-auto pt-6 sm:pt-0">
      <div class="fixed inset-0 bg-zinc-950/25 backdrop-blur-sm dark:bg-zinc-950/50" @click="emit('close')" />

      <!--
        `relative` matters: the backdrop is position:fixed, and positioned
        elements paint above static ones in the same stacking context. Without
        it the panel and its labels render *under* the blur while the inputs
        (which are relative) stay crisp.
      -->
      <div class="relative grid min-h-full grid-rows-[1fr_auto] justify-items-center sm:grid-rows-[1fr_auto_3fr] sm:p-4">
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          class="row-start-2 w-full rounded-t-3xl bg-white p-(--gutter) shadow-lg ring-1 ring-zinc-950/10 [--gutter:--spacing(8)] sm:mb-auto sm:rounded-2xl dark:bg-zinc-900 dark:ring-white/10 forced-colors:outline"
          :class="SIZES[size] ?? SIZES.lg"
        >
          <h2 :id="titleId" class="text-lg/6 font-semibold text-balance text-zinc-950 sm:text-base/6 dark:text-white">
            {{ title }}
          </h2>
          <p v-if="description" class="mt-2 text-base/6 text-pretty text-zinc-500 sm:text-sm/6 dark:text-zinc-400">
            {{ description }}
          </p>

          <!-- Long forms scroll inside the panel so the actions stay reachable. -->
          <div class="mt-6 max-h-[60vh] overflow-y-auto scrollbar-thin">
            <slot />
          </div>

          <div v-if="$slots.actions" class="mt-8 flex flex-col-reverse items-center justify-end gap-3 *:w-full sm:flex-row sm:*:w-auto">
            <slot name="actions" />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
