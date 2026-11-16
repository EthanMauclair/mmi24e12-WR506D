<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const recherche = ref('')
const membres = ref([])
const chargement = ref(false)
const erreur = ref('')
const assezLong = computed(() => recherche.value.trim().length >= 2)

let delai = null
let derniere = 0

// Bouton de chaque ligne, comme sur un profil : Abonné, Demande envoyée, S'abonner ou Demander à suivre
function action(membre) {
  if (membre.abonnement === 'ACCEPTE') {
    return { libelle: t('profil.abonne'), secondaire: true }
  }
  if (membre.abonnement === 'EN_ATTENTE') {
    return { libelle: t('profil.demandeEnvoyee'), secondaire: true }
  }
  return { libelle: t(membre.estPrive ? 'profil.demanderSuivre' : 'profil.abonner'), secondaire: false }
}

async function basculerAbonnement(membre) {
  if (!session.utilisateur) {
    router.push({ name: 'connexion', query: { redirection: route.fullPath } })
    return
  }
  if (membre.abonnement === 'ACCEPTE' && membre.estPrive && !window.confirm(t('profil.confirmerDesabonnement', { pseudo: membre.pseudo }))) {
    return
  }
  erreur.value = ''
  try {
    const profil = await api(`/profils/${membre.pseudo}/abonnement`, { methode: membre.abonnement ? 'DELETE' : 'POST' })
    membre.abonnement = profil.relation.abonnement
  } catch (e) {
    erreur.value = e.message
  }
}

// La recherche part 250 ms après la dernière frappe ; une réponse dépassée par une plus récente est ignorée
watch(recherche, () => {
  clearTimeout(delai)
  if (!assezLong.value) {
    membres.value = []
    return
  }
  delai = setTimeout(async () => {
    const numero = ++derniere
    chargement.value = true
    erreur.value = ''
    try {
      const resultats = await api(`/membres?q=${encodeURIComponent(recherche.value.trim())}`)
      if (numero === derniere) {
        membres.value = resultats
      }
    } catch (e) {
      erreur.value = e.message
    } finally {
      if (numero === derniere) {
        chargement.value = false
      }
    }
  }, 250)
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <button type="button" class="bouton-icone" :aria-label="t('commun.retour')" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <h1 class="titre-page">{{ t('profil.membres') }}</h1>
      </header>

      <label class="recherche colonne-etroite">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
        <input v-model="recherche" type="search" :placeholder="t('membres.exemple')" :aria-label="t('profil.rechercherMembre')" autofocus />
      </label>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="!assezLong" class="etat">{{ t('membres.deuxLettres') }}</p>
      <p v-else-if="!chargement && membres.length === 0" class="etat">{{ t('membres.aucun', { texte: recherche.trim() }) }}</p>
      <ul v-else class="lignes liste-membres liste-membres--grille">
        <LigneMembre v-for="membre in membres" :key="membre.id" :membre="membre" :detail="membre.estPrive ? t('membres.profilPrive') : ''">
          <button
            v-if="!membre.estMoi"
            type="button"
            class="bouton bouton--petit"
            :class="{ 'bouton--contour': action(membre).secondaire }"
            @click="basculerAbonnement(membre)"
          >
            {{ action(membre).libelle }}
          </button>
        </LigneMembre>
      </ul>
    </main>

    <BarreNavigation actif="profil" />
  </div>
</template>

<style scoped>
.recherche {
  --largeur-etroite: 640px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 46px;
  padding: 0 12px;
  box-sizing: border-box;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 12px;
  background: var(--couleur-surface);
  color: var(--couleur-texte-secondaire);
}

.recherche:focus-within {
  border-color: var(--couleur-primaire);
}

.recherche input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--couleur-texte);
}
</style>
