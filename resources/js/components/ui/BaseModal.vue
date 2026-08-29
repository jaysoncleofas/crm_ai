<script setup>
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  size: { type: String, default: 'md' },
})
const emit = defineEmits(['close'])

const panel = ref(null)
const titleId = useId()

const SIZES = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl' }

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
    <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-slate-900/40" @click="emit('close')" />

      <div class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-4">
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          class="relative w-full rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
          :class="SIZES[size] ?? SIZES.md"
        >
          <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 :id="titleId" class="text-base font-semibold text-slate-900">{{ title }}</h2>
            <button
              type="button"
              class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              aria-label="Close dialog"
              @click="emit('close')"
            >
              <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.3 6.3a1 1 0 011.4 0L10 8.6l2.3-2.3a1 1 0 111.4 1.4L11.4 10l2.3 2.3a1 1 0 01-1.4 1.4L10 11.4l-2.3 2.3a1 1 0 01-1.4-1.4L8.6 10 6.3 7.7a1 1 0 010-1.4z" />
              </svg>
            </button>
          </header>

          <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
            <slot />
          </div>

          <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </div>
  </Teleport>
</template>
