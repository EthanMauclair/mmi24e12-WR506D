import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'connexion' },
    },
    {
      path: '/connexion',
      name: 'connexion',
      component: () => import('../views/ConnexionView.vue'),
    },
  ],
})

export default router
