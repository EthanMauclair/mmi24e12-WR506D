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
      path: '/articles/:id/modifier',
      name: 'article-modifier',
      component: () => import('../views/AjoutArticleView.vue'),
    },
    {
      path: '/articles/:id/preter',
      name: 'article-preter',
      component: () => import('../views/PreterView.vue'),
    },
    {
      path: '/prets',
      name: 'prets',
      component: () => import('../views/PretsView.vue'),
    },
    {
      path: '/articles/:id/vendre',
      name: 'article-vendre',
      component: () => import('../views/VendreView.vue'),
    },
    {
      path: '/marche',
      name: 'marche',
      component: () => import('../views/MarcheView.vue'),
    },
    {
      path: '/annonces/:id',
      name: 'annonce',
      component: () => import('../views/AnnonceView.vue'),
    },
    {
      path: '/messages',
      name: 'messages',
      component: () => import('../views/ConversationsView.vue'),
    },
    {
      path: '/messages/:id',
      name: 'conversation',
      component: () => import('../views/ConversationView.vue'),
    },
    {
      path: '/articles/:id',
      name: 'article',
      component: () => import('../views/ArticleView.vue'),
      // Un article d'un profil public se consulte sans compte (l'API décide de ce qui est visible)
      meta: { ouvert: true },
    },
    {
      path: '/tenues',
      name: 'tenues',
      component: () => import('../views/TenuesView.vue'),
    },
    {
      // Lien partagé d'une tenue : ouvert sans compte pour une tenue publique d'un profil public
      path: '/tenues/:id',
      name: 'tenue',
      component: () => import('../views/TenueView.vue'),
      meta: { ouvert: true },
    },
    {
      path: '/profil',
      name: 'profil',
      component: () => import('../views/ProfilView.vue'),
    },
    {
      path: '/profil/modifier',
      name: 'profil-modifier',
      component: () => import('../views/ModifierProfilView.vue'),
    },
    {
      path: '/profil/demandes',
      name: 'profil-demandes',
      component: () => import('../views/DemandesView.vue'),
    },
    {
      path: '/profil/amis',
      name: 'profil-amis',
      component: () => import('../views/ListeMembresView.vue'),
      props: { liste: 'amis' },
    },
    {
      path: '/profil/espace',
      name: 'profil-espace',
      component: () => import('../views/EspaceView.vue'),
    },
    {
      // Back office : l'administrateur gère les référentiels et la modération,
      // le super admin (propriétaire du site) voit aussi les indicateurs et gère les membres
      path: '/admin',
      component: () => import('../views/admin/AdminView.vue'),
      meta: { admin: true },
      children: [
        {
          path: '',
          name: 'admin',
          component: () => import('../views/admin/TableauBordView.vue'),
          meta: { superAdmin: true },
        },
        {
          path: 'membres',
          name: 'admin-membres',
          component: () => import('../views/admin/MembresAdminView.vue'),
          meta: { superAdmin: true },
        },
        {
          path: 'referentiels',
          name: 'admin-referentiels',
          component: () => import('../views/admin/ReferentielsAdminView.vue'),
        },
        {
          path: 'moderation',
          name: 'admin-moderation',
          component: () => import('../views/admin/ModerationView.vue'),
        },
      ],
    },
    {
      path: '/membres',
      name: 'membres',
      component: () => import('../views/RechercheMembresView.vue'),
      meta: { ouvert: true },
    },
    {
      path: '/membres/:pseudo',
      name: 'membre',
      component: () => import('../views/ProfilView.vue'),
      meta: { ouvert: true },
    },
    {
      path: '/membres/:pseudo/abonnes',
      name: 'membre-abonnes',
      component: () => import('../views/ListeMembresView.vue'),
      props: { liste: 'abonnes' },
      meta: { ouvert: true },
    },
    {
      path: '/membres/:pseudo/abonnements',
      name: 'membre-abonnements',
      component: () => import('../views/ListeMembresView.vue'),
      props: { liste: 'abonnements' },
      meta: { ouvert: true },
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
// les écrans ouverts (profils, articles d'un profil) s'affichent avec ou sans compte ;
// les autres demandent d'être connecté, et le back office un rôle d'administration.
router.beforeEach(async (cible) => {
  const session = useSessionStore()
  await session.verifier()

  if (cible.meta.ouvert) {
    return true
  }

  if (cible.meta.public) {
    return session.utilisateur ? { name: 'dressing' } : true
  }

  if (!session.utilisateur) {
    return { name: 'connexion', query: { redirection: cible.fullPath } }
  }

  if (cible.meta.admin && !session.utilisateur.estAdmin) {
    return { name: 'dressing' }
  }

  if (cible.meta.superAdmin && !session.utilisateur.estSuperAdmin) {
    return { name: 'admin-referentiels' }
  }

  return true
})

export default router
