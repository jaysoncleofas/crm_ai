import { ref, watch } from 'vue'

const STORAGE_KEY = 'crm-theme'
const theme = ref('system') // 'light' | 'dark' | 'system'

function systemPrefersDark() {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
}

function apply(value) {
  const dark = value === 'dark' || (value === 'system' && systemPrefersDark())
  document.documentElement.classList.toggle('dark', dark)
}

/** Read the stored choice and start following the OS while set to "system". */
export function initTheme() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (['light', 'dark', 'system'].includes(stored)) theme.value = stored
  } catch {
    // Private browsing or blocked storage — fall back to the default.
  }

  apply(theme.value)

  window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (theme.value === 'system') apply('system')
  })
}

watch(theme, (value) => {
  apply(value)
  try {
    localStorage.setItem(STORAGE_KEY, value)
  } catch {
    // Not fatal — the choice just won't survive a reload.
  }
})

export function useTheme() {
  return {
    theme,
    setTheme: (value) => {
      theme.value = value
    },
    toggle: () => {
      theme.value = document.documentElement.classList.contains('dark') ? 'light' : 'dark'
    },
  }
}
