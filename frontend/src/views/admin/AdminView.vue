<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useSessionStore } from '@/stores/session'
import '@/assets/admin.css'

const { t } = useI18n()
const route = useRoute()
const session = useSessionStore()

// Le super admin (propriétaire du site) voit tout ; l'administrateur gère les référentiels et la modération
const onglets = computed(() => [
  ...(session.utilisateur.estSuperAdmin
    ? [
        { nom: 'admin', libelle: t('admin.onglets.tableau') },
        { nom: 'admin-membres', libelle: t('admin.onglets.membres') },
      ]
    : []),
  { nom: 'admin-referentiels', libelle: t('admin.onglets.referentiels') },
  { nom: 'admin-moderation', libelle: t('admin.onglets.moderation') },
])
const role = computed(() => t(session.utilisateur.estSuperAdmin ? 'admin.roles.SUPER_ADMIN' : 'admin.roles.ADMIN'))
</script>

<template>
  <div class="admin">
    <!-- Ordinateur : menu latéral propre au back office -->
    <nav class="admin__menu" :aria-label="t('admin.navigation')">
      <span class="admin__marque">
        <span class="admin__logo" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.3 8 9 4.6-.7 8-4 8-9V6z" /></svg>
        </span>
        {{ t('admin.titre') }}
      </span>
      <RouterLink
        v-for="onglet in onglets"
        :key="onglet.nom"
        :to="{ name: onglet.nom }"
        class="admin__lien"
        :class="{ 'admin__lien--actif': route.name === onglet.nom }"
        :aria-current="route.name === onglet.nom ? 'page' : undefined"
      >
        {{ onglet.libelle }}
      </RouterLink>
      <span class="admin__espace"></span>
      <RouterLink :to="{ name: 'profil' }" class="admin__lien admin__retour">‹ {{ t('admin.retourApplication') }}</RouterLink>
    </nav>

    <div class="admin__colonne">
      <!-- Téléphone et tablette : en-tête « Back office » et onglets en puces -->
      <header class="admin__entete">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('admin.retourApplication')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <span class="titre-page">{{ t('admin.titre') }}</span>
        <span class="badge badge--plein">{{ role }}</span>
      </header>
      <nav class="puces admin__puces" :aria-label="t('admin.navigation')">
        <RouterLink
          v-for="onglet in onglets"
          :key="onglet.nom"
          :to="{ name: onglet.nom }"
          class="puce"
          :class="{ 'puce--active': route.name === onglet.nom }"
          :aria-current="route.name === onglet.nom ? 'page' : undefined"
        >
          {{ onglet.libelle }}
        </RouterLink>
      </nav>

      <main class="admin__contenu">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.admin {
  min-height: 100dvh;
  background: var(--couleur-fond);
}

.admin__menu {
  display: none;
}

.admin__colonne {
  max-width: var(--largeur-colonne);
  margin: 0 auto;
  padding: 18px 16px 32px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.admin__entete {
  display: flex;
  align-items: center;
  gap: 10px;
}

.admin__entete .titre-page {
  flex: 1;
  min-width: 0;
}

.admin__contenu {
  min-width: 0;
}

/* Ordinateur : menu latéral de 240 px, contenu à droite */
@media (min-width: 1024px) {
  .admin__menu {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    width: var(--largeur-menu);
    box-sizing: border-box;
    padding: 22px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-right: 1.5px solid var(--couleur-bordure);
    background: var(--couleur-fond);
  }

  .admin__marque {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 14px 6px;
    font-size: 18px;
    font-weight: 800;
  }

  .admin__logo {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--couleur-primaire);
    color: var(--couleur-sur-primaire);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .admin__lien {
    height: 44px;
    padding: 0 12px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    font-size: 14px;
    font-weight: 700;
    color: var(--couleur-texte-secondaire);
  }

  .admin__lien:hover {
    background: var(--couleur-separateur);
    color: var(--couleur-texte);
  }

  .admin__lien--actif,
  .admin__lien--actif:hover {
    background: var(--couleur-primaire);
    color: var(--couleur-sur-primaire);
  }

  .admin__espace {
    flex: 1;
  }

  .admin__retour {
    color: var(--couleur-primaire);
  }

  .admin__colonne {
    max-width: none;
    margin: 0;
    padding: 28px 40px 40px calc(var(--largeur-menu) + 40px);
  }

  .admin__entete,
  .admin__puces {
    display: none;
  }

  /* Contenu centré dans l'espace à droite du menu, comme les écrans de l'application */
  .admin__contenu {
    max-width: var(--largeur-contenu);
    margin: 0 auto;
  }
}
</style>
