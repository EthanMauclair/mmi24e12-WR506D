<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const props = defineProps({
  // 'abonnes', 'abonnements' ou 'amis' (les abonnés de l'utilisateur connecté, à choisir comme amis)
  liste: { type: String, required: true },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const membres = ref([])
const chargement = ref(true)
const erreur = ref('')

const pseudo = computed(() => (props.liste === 'amis' ? session.utilisateur?.pseudo : route.params.pseudo))
// Sur ses propres abonnés, l'API indique lesquels sont des amis : on peut alors les choisir ou les retirer
const gereSesAbonnes = computed(() => props.liste !== 'abonnements' && pseudo.value === session.utilisateur?.pseudo)
const titre = computed(() => t(`membres.titres.${props.liste}`))
const nbAmis = computed(() => membres.value.filter((membre) => membre.estAmi).length)

async function charger() {
  chargement.value = true
  erreur.value = ''
  try {
    membres.value = await api(`/profils/${pseudo.value}/${props.liste === 'amis' ? 'abonnes' : props.liste}`)
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
}

async function basculerAmi(membre) {
  erreur.value = ''
  membre.estAmi = !membre.estAmi
  try {
    await api(`/moi/abonnes/${membre.pseudo}`, { methode: 'PUT', corps: { estAmi: membre.estAmi } })
  } catch (e) {
    membre.estAmi = !membre.estAmi
    erreur.value = e.violations.estAmi ?? e.message
  }
}

async function retirer(membre) {
  if (!window.confirm(t('membres.confirmerRetrait', { pseudo: membre.pseudo }))) {
    return
  }
  erreur.value = ''
  try {
    await api(`/moi/abonnes/${membre.pseudo}`, { methode: 'DELETE' })
    membres.value = membres.value.filter((autre) => autre.id !== membre.id)
  } catch (e) {
    erreur.value = e.message
  }
}

watch(pseudo, (valeur) => valeur && charger(), { immediate: true })
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <button type="button" class="bouton-icone" :aria-label="t('commun.retour')" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <div class="entete__textes">
          <span v-if="liste !== 'amis'" class="fil-ariane">@{{ pseudo }}</span>
          <h1 class="titre-page">{{ titre }}<template v-if="liste === 'amis'"> · {{ nbAmis }}</template></h1>
        </div>
      </header>

      <p v-if="liste === 'amis'" class="carte carte--grise texte-secondaire colonne-etroite">{{ t('membres.aideAmis') }}</p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="membres.length === 0" class="etat">
        {{ t(liste === 'abonnements' ? 'membres.aucunAbonnement' : 'membres.aucunAbonne') }}
      </p>
      <ul v-else class="lignes liste-membres liste-membres--grille">
        <LigneMembre v-for="membre in membres" :key="membre.id" :membre="membre">
          <template v-if="gereSesAbonnes">
            <!-- Badge « Ami » plein, ou « Choisir comme ami » : un clic bascule -->
            <button
              type="button"
              class="badge"
              :class="{ 'badge--plein': membre.estAmi }"
              :aria-pressed="membre.estAmi"
              :aria-label="t(membre.estAmi ? 'membres.retirerAmi' : 'membres.choisirAmi', { pseudo: membre.pseudo })"
              @click="basculerAmi(membre)"
            >
              {{ t(membre.estAmi ? 'membres.ami' : 'membres.choisirCommeAmi') }}
            </button>
            <button v-if="liste === 'abonnes'" type="button" class="bouton bouton--contour bouton--petit" :aria-label="t('membres.retirerAbonne', { pseudo: membre.pseudo })" @click="retirer(membre)">
              {{ t('membres.retirer') }}
            </button>
          </template>
        </LigneMembre>
      </ul>
    </main>

    <BarreNavigation actif="profil" ordinateur-seulement />
  </div>
</template>
