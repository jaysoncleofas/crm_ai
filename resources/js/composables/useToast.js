import { readonly, ref } from 'vue'

const toasts = ref([])
let nextId = 1

function push(type, message, { timeout = 5000 } = {}) {
  const id = nextId++
  toasts.value = [...toasts.value, { id, type, message }]

  if (timeout > 0) {
    setTimeout(() => dismiss(id), timeout)
  }

  return id
}

export function dismiss(id) {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

/** Every user-facing outcome goes through here — no silent failures. */
export function useToast() {
  return {
    toasts: readonly(toasts),
    dismiss,
    success: (message, opts) => push('success', message, opts),
    error: (message, opts) => push('error', message, opts),
    info: (message, opts) => push('info', message, opts),
  }
}
