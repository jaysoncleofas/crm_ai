import { computed, reactive, watch } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import api from '@/lib/api'
import { STALE } from '@/lib/queryClient'

/**
 * Shared list-view plumbing: search, sort, filters and pagination mapped onto
 * the API's spatie/query-builder contract, wrapped in a Vue Query.
 *
 * Query keys stay hierarchical — ['contacts', { …params }] — so a mutation can
 * invalidate ['contacts'] and refresh every page and filter combination.
 */
export function useResourceList(resource, { defaultSort = '-created_at', filters = {}, perPage = 25 } = {}) {
  const state = reactive({
    page: 1,
    sort: defaultSort,
    search: '',
    filters: { ...filters },
  })

  // Any change to what we're looking at resets to the first page.
  watch(
    () => [state.search, state.sort, JSON.stringify(state.filters)],
    () => {
      state.page = 1
    },
  )

  const params = computed(() => {
    const query = {
      page: state.page,
      per_page: perPage,
      sort: state.sort,
    }

    if (state.search) query['filter[search]'] = state.search

    for (const [key, value] of Object.entries(state.filters)) {
      if (value !== '' && value !== null && value !== undefined) {
        query[`filter[${key}]`] = value
      }
    }

    return query
  })

  const query = useQuery({
    queryKey: [resource, params],
    queryFn: async ({ signal }) => {
      const { data } = await api.get(`/${resource}`, { params: params.value, signal })
      return data
    },
    staleTime: STALE.list,
    // Keeps the previous page on screen while the next one loads — no flicker.
    placeholderData: keepPreviousData,
  })

  const rows = computed(() => query.data.value?.data ?? [])
  const meta = computed(() => query.data.value?.meta ?? null)
  const isEmpty = computed(() => !query.isPending.value && rows.value.length === 0)

  function setPage(page) {
    state.page = page
  }

  function setSort(sort) {
    state.sort = sort
  }

  function resetFilters() {
    state.search = ''
    state.filters = { ...filters }
  }

  return { state, params, query, rows, meta, isEmpty, setPage, setSort, resetFilters }
}
