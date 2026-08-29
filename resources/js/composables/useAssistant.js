import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/lib/api'

const open = ref(false)
const conversationId = ref(null)
const messages = ref([])

export function useAssistant() {
  const queryClient = useQueryClient()

  const status = useQuery({
    queryKey: ['assistant', 'status'],
    queryFn: async () => (await api.get('/assistant/status')).data.data,
    staleTime: 10 * 60_000,
  })

  const available = computed(() => status.data.value?.enabled === true)

  const send = useMutation({
    mutationFn: async (message) => {
      const { data } = await api.post('/assistant/chat', {
        message,
        conversation_id: conversationId.value,
      })
      return data.data
    },
    onSuccess(result) {
      conversationId.value = result.conversation_id
      messages.value = [...messages.value, result.message]
      // An answer may reflect data the user then wants to see fresh.
      queryClient.invalidateQueries({ queryKey: ['assistant', 'conversations'] })
    },
    onError(error) {
      messages.value = [
        ...messages.value,
        { id: `err-${Date.now()}`, role: 'error', content: error.message, citations: [], tool_calls: [] },
      ]
    },
  })

  function ask(text) {
    const question = text.trim()
    if (!question || send.isPending.value) return

    messages.value = [
      ...messages.value,
      { id: `local-${Date.now()}`, role: 'user', content: question, citations: [], tool_calls: [] },
    ]
    send.mutate(question)
  }

  function reset() {
    conversationId.value = null
    messages.value = []
  }

  return {
    open,
    available,
    redactsPii: computed(() => status.data.value?.redacts_pii === true),
    messages,
    ask,
    reset,
    pending: send.isPending,
    toggle: () => {
      open.value = !open.value
    },
  }
}
