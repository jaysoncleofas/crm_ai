import { createApp } from 'vue'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from '@/App.vue'
import router from '@/router'
import { createAppQueryClient } from '@/lib/queryClient'
import '../css/app.css'

createApp(App)
  .use(VueQueryPlugin, { queryClient: createAppQueryClient() })
  .use(router)
  .mount('#app')
