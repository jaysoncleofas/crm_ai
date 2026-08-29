import { computed, ref } from 'vue'
import api, { ensureCsrfCookie } from '@/lib/api'

const user = ref(null)
const ready = ref(false)

export function useAuth() {
  const isAuthenticated = computed(() => user.value !== null)
  const permissions = computed(() => new Set(user.value?.permissions ?? []))
  const roles = computed(() => new Set(user.value?.roles ?? []))

  /**
   * Permission-driven UI. Roles are never checked in templates — a screen asks
   * "can I do this?", which keeps the UI honest when roles are re-cut.
   */
  function can(permission) {
    return permissions.value.has(permission)
  }

  function canAny(...names) {
    return names.some((name) => permissions.value.has(name))
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/me')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(credentials) {
    await ensureCsrfCookie()
    const { data } = await api.post('/login', credentials)
    user.value = data.data
    return user.value
  }

  async function register(payload) {
    await ensureCsrfCookie()
    const { data } = await api.post('/register', payload)
    user.value = data.data
    return user.value
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      user.value = null
    }
  }

  return { user, ready, isAuthenticated, roles, can, canAny, fetchUser, login, register, logout }
}
