import { createApp } from 'vue'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from '@/App.vue'
import router from '@/router'
import { createAppQueryClient } from '@/lib/queryClient'
import { initTheme } from '@/composables/useTheme'
import '../css/app.css'

// Applied before mount so the first paint is already in the right theme.
initTheme()

createApp(App)
  .use(VueQueryPlugin, { queryClient: createAppQueryClient() })
  .use(router)
  .mount('#app')
