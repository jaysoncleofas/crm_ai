import axios from 'axios'

/**
 * Sanctum SPA auth: the session lives in a cookie, and Laravel expects the
 * XSRF-TOKEN cookie echoed back as a header. Axios does that for us as long as
 * credentials are sent.
 */
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfReady = null

/** Prime the XSRF-TOKEN cookie once per page load, before the first write. */
export function ensureCsrfCookie() {
  csrfReady ??= axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  return csrfReady
}

const WRITE_METHODS = new Set(['post', 'put', 'patch', 'delete'])

api.interceptors.request.use(async (config) => {
  if (WRITE_METHODS.has((config.method || 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }
  return config
})

/** Normalised error shape so every caller can rely on the same fields. */
export class ApiError extends Error {
  constructor(message, { status, errors = {}, retryAfter = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.retryAfter = retryAfter
  }

  /** First validation message for a field, if any. */
  fieldError(field) {
    return this.errors?.[field]?.[0] ?? null
  }
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status ?? 0
    const data = error.response?.data ?? {}

    if (status === 419) {
      // Session/CSRF expired — force a fresh token on the next attempt.
      csrfReady = null
    }

    const message =
      status === 0
        ? 'Network error — check your connection and try again.'
        : (data.message ?? 'Something went wrong. Please try again.')

    return Promise.reject(
      new ApiError(message, {
        status,
        errors: data.errors ?? {},
        retryAfter: Number(error.response?.headers?.['retry-after']) || null,
      }),
    )
  },
)

export default api
