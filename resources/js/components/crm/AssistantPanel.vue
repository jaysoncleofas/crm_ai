<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PaperAirplaneIcon, SparklesIcon, XMarkIcon } from '@heroicons/vue/16/solid'
import { useAssistant } from '@/composables/useAssistant'
import { Badge, Button, EmptyState } from '@/components/catalyst'

const { open, available, configured, redactsPii, messages, ask, reset, pending, toggle } = useAssistant()

const draft = ref('')
const scroller = ref(null)
const input = ref(null)

const SUGGESTIONS = [
  'What are my overdue tasks?',
  'Show me open deals closing this month',
  'Who are the contacts at our biggest account?',
  'Summarise the pipeline by stage',
]

/** Record type -> route, so a citation links straight to the record. */
const ROUTES = { contact: '/contacts', company: '/companies', deal: '/deals' }

function submit() {
  ask(draft.value)
  draft.value = ''
}

async function scrollToEnd() {
  await nextTick()
  scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: 'smooth' })
}

watch(messages, scrollToEnd, { deep: true })
watch(pending, scrollToEnd)

watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    input.value?.focus()
  }
})

// ⌘K / Ctrl-K opens the assistant from anywhere.
function onKeydown(event) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    toggle()
  } else if (event.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

/**
 * The model replies in light markdown. Rather than pull in a parser (and the
 * XSS surface that comes with rendering HTML from a model), render a small,
 * safe subset: paragraphs, bullets and **bold**, all as text nodes.
 */
function blocks(content) {
  return String(content ?? '')
    .split(/\n{2,}/)
    .map((chunk) => {
      const lines = chunk.split('\n').filter(Boolean)
      const bulleted = lines.every((l) => /^\s*[-*]\s+/.test(l))

      return bulleted
        ? { type: 'list', items: lines.map((l) => inline(l.replace(/^\s*[-*]\s+/, ''))) }
        : { type: 'text', spans: inline(lines.join(' ')) }
    })
}

function inline(text) {
  // Split on **bold** and keep the delimiters so we can mark those spans.
  return text.split(/(\*\*[^*]+\*\*)/g).filter(Boolean).map((part) =>
    part.startsWith('**') && part.endsWith('**')
      ? { bold: true, text: part.slice(2, -2) }
      : { bold: false, text: part },
  )
}
</script>

<template>
  <div v-if="available">
    <!-- Launcher -->
    <button
      type="button"
      class="fixed right-5 bottom-5 z-40 flex items-center gap-2 rounded-full bg-zinc-900 py-3 pr-4 pl-3.5 text-sm font-semibold text-white shadow-lg ring-1 ring-zinc-950/10 hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:ring-white/10 dark:hover:bg-zinc-100"
      :aria-expanded="open"
      aria-controls="assistant-panel"
      @click="toggle"
    >
      <SparklesIcon class="size-4" aria-hidden="true" />
      Ask CRM
      <kbd class="ml-1 hidden rounded bg-white/15 px-1.5 py-0.5 text-[10px] font-medium sm:block dark:bg-zinc-900/10">⌘K</kbd>
    </button>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-x-full"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-x-full"
      >
        <aside
          v-if="open"
          id="assistant-panel"
          class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l border-zinc-950/10 bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900"
          role="dialog"
          aria-modal="false"
          aria-label="CRM assistant"
        >
          <header class="flex items-center justify-between gap-3 border-b border-zinc-950/5 px-5 py-4 dark:border-white/5">
            <div class="flex items-center gap-2">
              <SparklesIcon class="size-4 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
              <h2 class="text-sm/6 font-semibold text-zinc-950 dark:text-white">Ask CRM</h2>
            </div>
            <div class="flex items-center gap-1">
              <Button v-if="messages.length" plain size="sm" @click="reset">New chat</Button>
              <button
                type="button"
                class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-950/5 hover:text-zinc-600 dark:hover:bg-white/5 dark:hover:text-zinc-200"
                aria-label="Close assistant"
                @click="open = false"
              >
                <XMarkIcon class="size-4" aria-hidden="true" />
              </button>
            </div>
          </header>

          <div ref="scroller" class="flex-1 space-y-5 overflow-y-auto px-5 py-5 scrollbar-thin">
            <div
              v-if="!configured"
              class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm/6 text-amber-800 dark:text-amber-300"
              role="status"
            >
              Gemini credentials not found. Add <code class="text-xs">gemini_credentials.json</code> to
              <code class="text-xs">laravel/</code>, or set <code class="text-xs">GEMINI_API_KEY</code>.
            </div>

            <template v-if="messages.length === 0">
              <EmptyState
                title="Ask about your customers"
                message="I can look up contacts, companies, deals and activities — read only."
              />
              <div class="space-y-2">
                <button
                  v-for="suggestion in SUGGESTIONS"
                  :key="suggestion"
                  type="button"
                  class="block w-full rounded-lg border border-zinc-950/10 px-3 py-2 text-left text-sm/6 text-zinc-700 hover:bg-zinc-950/2.5 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5 disabled:opacity-50"
                  :disabled="!configured"
                  @click="ask(suggestion)"
                >
                  {{ suggestion }}
                </button>
              </div>
            </template>

            <div v-for="message in messages" :key="message.id">
              <!-- User turn -->
              <div v-if="message.role === 'user'" class="flex justify-end">
                <p class="max-w-[85%] rounded-2xl rounded-br-sm bg-zinc-900 px-3.5 py-2 text-sm/6 text-white dark:bg-white dark:text-zinc-900">
                  {{ message.content }}
                </p>
              </div>

              <!-- Failure -->
              <div v-else-if="message.role === 'error'" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm/6 text-red-700 dark:text-red-400" role="alert">
                {{ message.content }}
              </div>

              <!-- Assistant turn -->
              <div v-else class="space-y-3">
                <div class="space-y-3 text-sm/6 text-zinc-950 dark:text-white">
                  <template v-for="(block, index) in blocks(message.content)" :key="index">
                    <ul v-if="block.type === 'list'" class="list-disc space-y-1 pl-5">
                      <li v-for="(item, i) in block.items" :key="i">
                        <span v-for="(span, j) in item" :key="j" :class="span.bold ? 'font-semibold' : ''">{{ span.text }}</span>
                      </li>
                    </ul>
                    <p v-else>
                      <span v-for="(span, j) in block.spans" :key="j" :class="span.bold ? 'font-semibold' : ''">{{ span.text }}</span>
                    </p>
                  </template>
                </div>

                <!-- Which records this answer came from -->
                <div v-if="message.citations?.length" class="flex flex-wrap gap-1.5">
                  <RouterLink
                    v-for="citation in message.citations"
                    :key="`${citation.type}-${citation.id}`"
                    :to="`${ROUTES[citation.type] ?? '/'}/${citation.id}`"
                    class="inline-flex items-center gap-1 rounded-md bg-zinc-950/5 px-2 py-0.5 text-xs/5 text-zinc-700 hover:bg-zinc-950/10 dark:bg-white/5 dark:text-zinc-300 dark:hover:bg-white/10"
                    @click="open = false"
                  >
                    <span class="text-zinc-400 dark:text-zinc-500">{{ citation.type }}</span>
                    {{ citation.label }}
                  </RouterLink>
                </div>

                <div v-if="message.tool_calls?.length" class="flex flex-wrap gap-1">
                  <Badge v-for="(call, i) in message.tool_calls" :key="i">{{ call.name.replace(/_/g, ' ') }}</Badge>
                </div>
              </div>
            </div>

            <div v-if="pending" class="flex items-center gap-2 text-sm/6 text-zinc-500 dark:text-zinc-400">
              <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              Looking through the CRM…
            </div>
          </div>

          <form class="border-t border-zinc-950/5 p-4 dark:border-white/5" @submit.prevent="submit">
            <div class="flex items-end gap-2">
              <label class="sr-only" for="assistant-input">Ask about your customers</label>
              <textarea
                id="assistant-input"
                ref="input"
                v-model="draft"
                rows="2"
                placeholder="Ask about a contact, company or deal…"
                class="block w-full resize-none rounded-lg border border-zinc-950/10 bg-transparent px-3 py-2 text-sm/6 text-zinc-950 placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-white/10 dark:bg-white/5 dark:text-white disabled:opacity-50"
                :disabled="!configured"
                @keydown.enter.exact.prevent="submit"
              ></textarea>
              <Button type="submit" :disabled="!draft.trim() || !configured" :loading="pending" aria-label="Send">
                <PaperAirplaneIcon v-if="!pending" class="size-4" aria-hidden="true" />
              </Button>
            </div>
            <p class="mt-2 text-xs/5 text-zinc-500 dark:text-zinc-400">
              Read-only. Answers can be wrong — check the linked records.
              <span v-if="redactsPii">Emails and phone numbers are masked before leaving the CRM.</span>
            </p>
          </form>
        </aside>
      </Transition>
    </Teleport>
  </div>
</template>
