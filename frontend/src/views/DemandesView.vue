<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'

const { t } = useI18n()
const demandes = ref([])
const chargement = ref(true)
const erreur = ref('')

async function repondre(demande, accepter) {
  erreur.value = ''
  try {
    await api(accepter ? `/moi/demandes/${demande.id}/acceptation` : `/moi/demandes/${demande.id}`, { methode: accepter ? 'POST' : 'DELETE' })
    demandes.value = demandes.value.filter((autre) => autre.id !== demande.id)
  } catch (e) {
    erreur.value = e.message
  }
}

onMounted(async () => {
  try {
    demandes.value = await api('/moi/demandes')
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('demandes.retourProfil')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">
          <span class="seulement-mobile">{{ t('demandes.titreCourt') }}</span>
          <span class="seulement-ordinateur">{{ t('demandes.titre') }}</span>
        </h1>
      </header>

      <p class="texte-secondaire">{{ t('demandes.aide') }}</p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="demandes.length === 0" class="etat">{{ t('demandes.aucune') }}</p>
      <ul v-else class="lignes liste-membres colonne-etroite">
        <LigneMembre v-for="demande in demandes" :key="demande.id" :membre="demande.membre">
          <button type="button" class="bouton bouton--petit" @click="repondre(demande, true)">{{ t('demandes.accepter') }}</button>
          <button type="button" class="bouton bouton--contour bouton--petit" :aria-label="t('demandes.refuserDe', { pseudo: demande.membre.pseudo })" @click="repondre(demande, false)">{{ t('demandes.refuser') }}</button>
        </LigneMembre>
      </ul>
    </main>

    <BarreNavigation actif="profil" ordinateur-seulement />
  </div>
</template>

<style scoped>
@media (min-width: 1024px) {
  .lignes {
    padding: 0 16px;
  }
}
</style>
