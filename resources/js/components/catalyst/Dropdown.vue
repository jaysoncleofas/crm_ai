<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

defineProps({
  align: { type: String, default: 'right' },
})

const open = ref(false)
const root = ref(null)

function close() {
  open.value = false
}

function onDocumentClick(event) {
  if (root.value && !root.value.contains(event.target)) close()
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})

defineExpose({ close })
</script>

<template>
  <div ref="root" class="relative">
    <div :aria-expanded="open" @click="open = !open">
      <slot name="trigger" :open="open" />
    </div>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="scale-95 opacity-0"
      leave-active-class="transition duration-75 ease-in"
      leave-to-class="scale-95 opacity-0"
    >
      <div
        v-if="open"
        class="absolute z-40 mt-2 min-w-48 origin-top rounded-xl bg-white/95 p-1 shadow-lg ring-1 ring-zinc-950/10 backdrop-blur-xl dark:bg-zinc-800/95 dark:ring-white/10"
        :class="align === 'right' ? 'right-0' : 'left-0'"
        role="menu"
        @click="close"
      >
        <slot />
      </div>
    </Transition>
  </div>
</template>
