<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { formatPrix } from '@/utils/prix'

// Libellés : marche.filtres.<id> (« tout » pour l'identifiant vide)
const FILTRES = ['', 'vetements', 'vinyles', 'livres', 'mes']

const { t } = useI18n()

const annonces = ref([])
const nonLus = ref(0)
const recherche = ref('')
const filtre = ref('')
const chargement = ref(true)
const erreur = ref('')

let delai = null
let derniere = 0

async function charger() {
  const numero = ++derniere
  const parametres = new URLSearchParams()
  if (recherche.value.trim()) {
    parametres.set('q', recherche.value.trim())
  }
  if (filtre.value === 'mes') {
    parametres.set('mes', '1')
  } else if (filtre.value) {
    parametres.set('famille', filtre.value)
  }

  chargement.value = true
  erreur.value = ''
  try {
    const reponse = await api(`/annonces?${parametres}`)
    // Une réponse dépassée par une recherche plus récente est ignorée
    if (numero === derniere) {
      annonces.value = reponse.annonces
      nonLus.value = reponse.nonLus
    }
  } catch (e) {
    erreur.value = e.message
  } finally {
    if (numero === derniere) {
      chargement.value = false
    }
  }
}

watch(filtre, charger)
watch(recherche, () => {
  clearTimeout(delai)
  delai = setTimeout(charger, 300)
})
onMounted(charger)
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete-marche">
        <h1 class="titre-page">{{ t('navigation.marche') }}</h1>
        <label class="recherche">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <input v-model="recherche" type="search" :aria-label="t('marche.rechercher')" :placeholder="t('marche.rechercher')" />
        </label>
        <RouterLink :to="{ name: 'messages' }" class="bouton-icone messages" :aria-label="nonLus ? t('marche.messagesNonLus', { n: nonLus }) : t('messages.titre')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
          <span v-if="nonLus" class="badge badge--plein messages__badge" aria-hidden="true">{{ nonLus }}</span>
        </RouterLink>
      </header>

      <div class="filtres">
        <div class="puces">
          <button
            v-for="choix in FILTRES"
            :key="choix"
            type="button"
            class="puce"
            :class="{ 'puce--active': filtre === choix }"
            :aria-pressed="filtre === choix"
            @click="filtre = choix"
          >
            {{ t(`marche.filtres.${choix || 'tout'}`) }}
          </button>
        </div>
        <p class="texte-secondaire paiement">{{ t('marche.paiement') }}</p>
      </div>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="chargement && annonces.length === 0" class="etat">{{ t('marche.chargement') }}</p>
      <p v-else-if="annonces.length === 0" class="etat">
        <template v-if="filtre === 'mes'">{{ t('marche.aucuneMienne') }}</template>
        <template v-else>{{ t('marche.aucune') }}</template>
      </p>
      <div v-else class="annonces" :class="{ 'annonces--chargement': chargement }">
        <RouterLink v-for="annonce in annonces" :key="annonce.id" :to="{ name: 'annonce', params: { id: annonce.id } }" class="annonce">
          <VignetteArticle :photo="annonce.article.photo" :couleur="annonce.article.couleur" carree>
            <span v-if="annonce.statut === 'RESERVEE'" class="badge badge--blanc annonce__statut">{{ t('annonce.statuts.RESERVEE') }}</span>
          </VignetteArticle>
          <b class="annonce__prix">{{ formatPrix(annonce.prix) }}</b>
          <span class="texte-secondaire annonce__titre">{{ annonce.article.titre }}</span>
          <span class="annonce__vendeur">
            <AvatarMembre :membre="annonce.vendeur" :taille="20" />
            <span class="texte-secondaire">{{ annonce.vendeur.pseudo }}</span>
          </span>
        </RouterLink>
      </div>
    </main>

    <BarreNavigation actif="marche" />
  </div>
</template>

<style scoped>
/* En-tête : titre et messages, puis la recherche ; tout sur une ligne sur ordinateur */
.entete-marche {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas:
    'titre messages'
    'recherche recherche';
  align-items: center;
  gap: 12px;
}

.entete-marche .titre-page {
  grid-area: titre;
}

.messages {
  grid-area: messages;
  position: relative;
}

.messages__badge {
  position: absolute;
  top: -6px;
  right: -8px;
}

.recherche {
  grid-area: recherche;
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

.filtres {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.annonces {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  transition: opacity 0.2s;
}

.annonces--chargement {
  opacity: 0.5;
}

.annonce {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--couleur-texte);
}

.annonce:hover {
  color: var(--couleur-texte);
}

.annonce :deep(.vignette) {
  width: 100%;
}

.annonce:hover :deep(.vignette) {
  outline: 2px solid var(--couleur-primaire);
  outline-offset: 1px;
}

.annonce__statut {
  position: absolute;
  top: 8px;
  left: 8px;
}

.annonce__titre {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.annonce__vendeur {
  display: flex;
  align-items: center;
  gap: 6px;
}

@media (min-width: 600px) {
  .annonces {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  }
}

@media (min-width: 1024px) {
  .entete-marche {
    grid-template-columns: auto minmax(0, 1fr) auto;
    grid-template-areas: 'titre recherche messages';
  }

  .recherche {
    margin-left: 12px;
  }

  .filtres {
    flex-direction: row;
    align-items: center;
    gap: 16px;
  }

  .paiement {
    margin-left: auto;
    text-align: right;
  }

  .annonces {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
  }
}
</style>
