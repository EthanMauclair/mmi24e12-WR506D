<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const props = defineProps({
  // 'abonnes', 'abonnements' ou 'amis' (les abonnés de l'utilisateur connecté, à choisir comme amis)
  liste: { type: String, required: true },
})

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const membres = ref([])
const chargement = ref(true)
const erreur = ref('')

const pseudo = computed(() => (props.liste === 'amis' ? session.utilisateur?.pseudo : route.params.pseudo))
// Sur ses propres abonnés, l'API indique lesquels sont des amis : on peut alors les choisir ou les retirer
const gereSesAbonnes = computed(() => props.liste !== 'abonnements' && pseudo.value === session.utilisateur?.pseudo)
const titre = computed(() => ({ abonnes: 'Abonnés', abonnements: 'Abonnements', amis: 'Amis' })[props.liste])
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
  if (!window.confirm(`Retirer ${membre.pseudo} de vos abonnés ? Il ne verra plus votre contenu si votre profil est privé.`)) {
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
    <main class="contenu">
      <header class="entete">
        <button type="button" class="bouton-icone" aria-label="Retour" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <div class="entete__titres">
          <span v-if="liste !== 'amis'" class="entete__pseudo">@{{ pseudo }}</span>
          <h1 class="entete__titre">{{ titre }}<template v-if="liste === 'amis'"> · {{ nbAmis }}</template></h1>
        </div>
      </header>

      <p v-if="liste === 'amis'" class="aide">
        Comme les « amis proches », vos amis sont choisis parmi vos abonnés. Eux seuls peuvent commenter vos articles.
        Personne d'autre ne voit qui sont vos amis.
      </p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">Chargement…</p>
      <p v-else-if="membres.length === 0" class="etat">
        {{ liste === 'abonnements' ? 'Aucun abonnement pour le moment.' : 'Aucun abonné pour le moment.' }}
      </p>
      <ul v-else class="liste">
        <LigneMembre v-for="membre in membres" :key="membre.id" :membre="membre">
          <template v-if="gereSesAbonnes">
            <button
              type="button"
              class="ami"
              :class="{ 'ami--actif': membre.estAmi }"
              :aria-pressed="membre.estAmi"
              :aria-label="membre.estAmi ? `Retirer ${membre.pseudo} de vos amis` : `Choisir ${membre.pseudo} comme ami`"
              @click="basculerAmi(membre)"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" :fill="membre.estAmi ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2-6.2 3.2L7 14.2 2 9.3l6.9-1z" /></svg>
              {{ membre.estAmi ? 'Ami' : 'Ajouter' }}
            </button>
            <button type="button" class="retirer" :aria-label="`Retirer ${membre.pseudo} de vos abonnés`" @click="retirer(membre)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
          </template>
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

.entete__titres {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.entete__pseudo {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.entete__titre {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.aide {
  margin: 0;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire-fonce);
  font-size: 13px;
  font-weight: 600;
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

.ami {
  flex-shrink: 0;
  height: 34px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  gap: 5px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.ami--actif {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.retirer {
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 0;
  background: none;
  color: var(--couleur-texte-secondaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
</style>
