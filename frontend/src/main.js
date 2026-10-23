import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { definirSurNonConnecte } from './services/api'
import { useSessionStore } from './stores/session'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Session expirée : retour à l'écran de connexion, puis à la page demandée
definirSurNonConnecte(() => {
  useSessionStore().oublier()
  const page = router.currentRoute.value
  if (!page.meta.public) {
    router.push({ name: 'connexion', query: { redirection: page.fullPath } })
  }
})

app.mount('#app')
