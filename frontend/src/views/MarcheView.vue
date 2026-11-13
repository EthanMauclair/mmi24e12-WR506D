<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'
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

function details(annonce) {
  return [annonce.article.infos, `@${annonce.vendeur.pseudo}`].join(' · ')
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
    <main class="contenu">
      <header class="entete">
        <h1 class="entete__titre">{{ t('navigation.marche') }}</h1>
        <RouterLink :to="{ name: 'messages' }" class="bouton-icone messages" :aria-label="nonLus ? t('marche.messagesNonLus', { n: nonLus }) : t('messages.titre')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
          <span v-if="nonLus" class="messages__badge" aria-hidden="true">{{ nonLus }}</span>
        </RouterLink>
      </header>

      <label class="saisie saisie--groupe champ-recherche">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
        <input v-model="recherche" type="search" :aria-label="t('marche.rechercher')" :placeholder="t('marche.rechercher')" />
      </label>

      <div class="filtres">
        <button
          v-for="choix in FILTRES"
          :key="choix"
          type="button"
          class="filtre"
          :class="{ 'filtre--actif': filtre === choix }"
          :aria-pressed="filtre === choix"
          @click="filtre = choix"
        >
          {{ t(`marche.filtres.${choix || 'tout'}`) }}
        </button>
      </div>

      <p class="info">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg>
        {{ t('marche.paiement') }}
      </p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="chargement && annonces.length === 0" class="etat">{{ t('marche.chargement') }}</p>
      <p v-else-if="annonces.length === 0" class="etat">
        <template v-if="filtre === 'mes'">{{ t('marche.aucuneMienne') }}</template>
        <template v-else>{{ t('marche.aucune') }}</template>
      </p>
      <div v-else class="annonces" :class="{ 'annonces--chargement': chargement }">
        <RouterLink v-for="annonce in annonces" :key="annonce.id" :to="{ name: 'annonce', params: { id: annonce.id } }" class="annonce">
          <span
            class="annonce__vignette"
            :class="{ 'annonce__vignette--claire': estCouleurClaire(annonce.article.couleur) }"
            :style="{ background: fondVignette(annonce.article.couleur) }"
          >
            <img v-if="annonce.article.photo" :src="annonce.article.photo" alt="" class="annonce__photo" />
            <svg v-else width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><circle cx="7" cy="7" r="1" /></svg>
            <span v-if="annonce.statut === 'RESERVEE'" class="annonce__reservee">{{ t('annonce.statuts.RESERVEE') }}</span>
          </span>
          <span class="annonce__textes">
            <span class="annonce__prix">{{ formatPrix(annonce.prix) }}</span>
            <span class="annonce__titre">{{ annonce.article.titre }}</span>
            <span class="annonce__details">{{ details(annonce) }}</span>
          </span>
        </RouterLink>
      </div>
    </main>

    <BarreNavigation actif="marche" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.entete {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.entete__titre {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.messages {
  position: relative;
}

.messages__badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  box-sizing: border-box;
  border-radius: 999px;
  border: 2px solid var(--couleur-fond);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-size: 11px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}

.champ-recherche {
  gap: 10px;
  height: 48px;
  color: var(--couleur-texte-secondaire);
}

/* Les filtres défilent horizontalement plutôt que de passer sur deux lignes */
.filtres {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}

.filtre {
  flex-shrink: 0;
  height: 38px;
  padding: 0 13px;
  border-radius: 999px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 13.5px;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}

.filtre--actif {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.info {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.4;
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
  display: flex;
  flex-direction: column;
  gap: 8px;
  color: var(--couleur-texte);
}

.annonce__vignette {
  position: relative;
  height: 150px;
  box-sizing: border-box;
  border-radius: 16px;
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
}

.annonce__vignette--claire {
  color: var(--couleur-photo-icone-sombre);
}

.annonce__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.annonce__reservee {
  position: absolute;
  top: 8px;
  left: 8px;
  padding: 3px 8px;
  border-radius: 8px;
  background: var(--couleur-photo-etiquette);
  color: var(--couleur-texte);
  font-size: 11px;
  font-weight: 800;
}

.annonce__textes {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0 2px;
  min-width: 0;
}

.annonce__prix {
  font-size: 16px;
  font-weight: 800;
  color: var(--couleur-primaire);
}

.annonce__titre {
  font-size: 13.5px;
  font-weight: 700;
}

.annonce__details {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
