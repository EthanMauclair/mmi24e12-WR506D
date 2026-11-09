<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'

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
    <main class="contenu">
      <header class="entete">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" aria-label="Retour au profil">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="entete__titre">Demandes d'abonnement</h1>
      </header>

      <p class="aide">Votre profil est privé : acceptez une demande pour que ce membre voie vos articles.</p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">Chargement…</p>
      <p v-else-if="demandes.length === 0" class="etat">Aucune demande en attente.</p>
      <ul v-else class="liste">
        <LigneMembre v-for="demande in demandes" :key="demande.id" :membre="demande.membre">
          <button type="button" class="reponse reponse--accepter" @click="repondre(demande, true)">Accepter</button>
          <button type="button" class="reponse" :aria-label="`Refuser la demande de ${demande.membre.pseudo}`" @click="repondre(demande, false)">Refuser</button>
        </LigneMembre>
      </ul>
    </main>

    <BarreNavigation actif="profil" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.entete__titre {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.aide {
  margin: 0;
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
  line-height: 1.5;
}

.liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.reponse {
  flex-shrink: 0;
  height: 34px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.reponse--accepter {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}
</style>
