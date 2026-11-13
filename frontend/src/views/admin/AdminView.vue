<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView } from 'vue-router'
import SelecteurLangue from '@/components/SelecteurLangue.vue'
import { useSessionStore } from '@/stores/session'
import '@/assets/admin.css'

const { t } = useI18n()
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
</script>

<template>
  <div class="admin">
    <header class="admin__entete">
      <div class="admin__marque">
        <span class="admin__logo" aria-hidden="true">P</span>
        <span>
          <span class="admin__nom">{{ t('admin.titre') }}</span>
          <span class="admin__role">{{ session.utilisateur.estSuperAdmin ? t('admin.roles.SUPER_ADMIN') : t('admin.roles.ADMIN') }} · @{{ session.utilisateur.pseudo }}</span>
        </span>
      </div>
      <div class="admin__outils">
        <SelecteurLangue recharger />
        <RouterLink :to="{ name: 'profil' }" class="admin-bouton">{{ t('admin.retourApplication') }}</RouterLink>
      </div>
    </header>

    <nav class="admin__onglets" :aria-label="t('admin.navigation')">
      <RouterLink v-for="onglet in onglets" :key="onglet.nom" :to="{ name: onglet.nom }" class="admin__onglet" exact-active-class="admin__onglet--actif">
        {{ onglet.libelle }}
      </RouterLink>
    </nav>

    <main class="admin__contenu">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin {
  min-height: 100dvh;
  background: var(--couleur-fond);
}

.admin__entete {
  max-width: 1120px;
  margin: 0 auto;
  padding: 16px;
  box-sizing: border-box;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.admin__marque {
  display: flex;
  align-items: center;
  gap: 12px;
}

.admin__logo {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 18px;
}

.admin__nom {
  display: block;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.admin__role {
  display: block;
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.admin__outils {
  display: flex;
  align-items: center;
  gap: 10px;
}

.admin__onglets {
  max-width: 1120px;
  margin: 0 auto;
  padding: 0 16px;
  box-sizing: border-box;
  display: flex;
  gap: 4px;
  overflow-x: auto;
  border-bottom: 1px solid var(--couleur-bordure);
}

.admin__onglet {
  padding: 10px 14px;
  border-bottom: 2.5px solid transparent;
  font-size: 14px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
}

.admin__onglet--actif {
  color: var(--couleur-primaire);
  border-bottom-color: var(--couleur-primaire);
}

.admin__contenu {
  max-width: 1120px;
  margin: 0 auto;
  padding: 20px 16px 40px;
  box-sizing: border-box;
}
</style>
