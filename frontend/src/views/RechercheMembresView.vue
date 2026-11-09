<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import LigneMembre from '@/components/LigneMembre.vue'
import { api } from '@/services/api'

const router = useRouter()

const recherche = ref('')
const membres = ref([])
const chargement = ref(false)
const erreur = ref('')
const assezLong = computed(() => recherche.value.trim().length >= 2)

let delai = null
let derniere = 0

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
    <main class="contenu">
      <header class="entete">
        <button type="button" class="bouton-icone" aria-label="Retour" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <h1 class="entete__titre">Membres</h1>
      </header>

      <label class="saisie saisie--groupe champ-recherche">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
        <input v-model="recherche" type="search" placeholder="Pseudo, prénom ou nom" aria-label="Rechercher un membre" autofocus />
      </label>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="!assezLong" class="etat">Tapez au moins 2 lettres pour trouver un membre.</p>
      <p v-else-if="!chargement && membres.length === 0" class="etat">Aucun membre ne correspond à « {{ recherche.trim() }} ».</p>
      <ul v-else class="liste">
        <LigneMembre v-for="membre in membres" :key="membre.id" :membre="membre" />
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
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.champ-recherche {
  gap: 10px;
  height: 50px;
  color: var(--couleur-texte-secondaire);
}

.liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
</style>
