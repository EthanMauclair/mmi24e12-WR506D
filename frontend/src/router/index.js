import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from '@/stores/session'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'dressing' },
    },
    {
      path: '/connexion',
      name: 'connexion',
      component: () => import('../views/ConnexionView.vue'),
      meta: { public: true },
    },
    {
      path: '/inscription',
      name: 'inscription',
      component: () => import('../views/InscriptionView.vue'),
      meta: { public: true },
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
      path: '/articles/nouveau',
      name: 'article-nouveau',
      component: () => import('../views/AjoutArticleView.vue'),
    },
    {
      path: '/articles/:id',
      name: 'article',
      component: () => import('../views/ArticleView.vue'),
    },
    {
      path: '/scan',
      name: 'scan',
      component: () => import('../views/ScanView.vue'),
    },
    {
      path: '/scan/code-barres',
      name: 'scan-code-barres',
      component: () => import('../views/ScanCodeBarresView.vue'),
    },
  ],
})

// Les écrans publics (connexion, inscription) renvoient vers le dressing si l'on est déjà connecté ;
// les autres demandent d'être connecté.
router.beforeEach(async (cible) => {
  const session = useSessionStore()
  await session.verifier()

  if (cible.meta.public) {
    return session.utilisateur ? { name: 'dressing' } : true
  }

  if (!session.utilisateur) {
    return { name: 'connexion', query: { redirection: cible.fullPath } }
  }

  return true
})

export default router
