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
    {
      path: '/inscription',
      name: 'inscription',
      component: () => import('../views/InscriptionView.vue'),
    },
    {
      path: '/dressing',
      name: 'dressing',
      component: () => import('../views/DressingView.vue'),
    },
    {
      path: '/pieces/:id',
      name: 'piece',
      component: () => import('../views/PieceView.vue'),
    },
    {
      path: '/articles/:id',
      name: 'article',
      component: () => import('../views/ArticleView.vue'),
    },
  ],
})

export default router
