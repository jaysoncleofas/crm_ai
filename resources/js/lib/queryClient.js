import { QueryClient } from '@tanstack/vue-query'
import { useToast } from '@/composables/useToast'

const toast = useToast()

/**
 * One place for cache policy. Lists go stale quickly (30s) so a rep sees a
 * teammate's edits; reference data (pipelines, tags) is held far longer.
 */
export const STALE = {
  list: 30_000,
  detail: 60_000,
  reference: 10 * 60_000,
}

export function createAppQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: STALE.list,
        gcTime: 5 * 60_000,
        refetchOnWindowFocus: false,
        retry(failureCount, error) {
          // Never hammer a throttled or unauthorised endpoint.
          if ([401, 403, 404, 419, 422, 429].includes(error?.status)) return false
          return failureCount < 2
        },
      },
      mutations: {
        retry: false,
        onError(error) {
          if (error?.status === 422) return // surfaced inline on the form
          if (error?.status === 429) {
            const wait = error.retryAfter ? ` Try again in ${error.retryAfter}s.` : ''
            toast.error(`Too many requests.${wait}`)
            return
          }
          toast.error(error?.message ?? 'Something went wrong.')
        },
      },
    },
  })
}
