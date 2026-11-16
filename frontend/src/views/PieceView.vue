<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'

// Mêmes valeurs que l'énumération TypeStockage du backend
const TYPES_STOCKAGE = ['ARMOIRE', 'PENDERIE', 'COMMODE', 'ETAGERE', 'PLACARD', 'BIBLIOTHEQUE', 'BOITE', 'AUTRE']

const { t } = useI18n()
const route = useRoute()

const piece = ref(null)
const chargement = ref(true)
const erreur = ref('')

const ajoutStockage = ref(false)
const nomStockage = ref('')
const typeStockage = ref('ARMOIRE')
const erreursStockage = ref({})

const articlesDe = (stockage) => piece.value.articles.filter((article) => article.stockageId === stockage.id)

async function charger(id) {
  chargement.value = true
  erreur.value = ''
  try {
    piece.value = await api(`/pieces/${id}`)
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
}

async function creerStockage() {
  erreursStockage.value = {}
  try {
    const stockage = await api(`/pieces/${piece.value.id}/stockages`, {
      methode: 'POST',
      corps: { nom: nomStockage.value, type: typeStockage.value },
    })
    piece.value.stockages.push(stockage)
    nomStockage.value = ''
    ajoutStockage.value = false
  } catch (e) {
    erreursStockage.value = Object.keys(e.violations).length ? e.violations : { general: e.message }
  }
}

watch(() => route.params.id, charger, { immediate: true })
</script>

<template>
  <div class="ecran">
    <main class="page">
      <p v-if="chargement && !piece" class="etat">{{ t('piece.chargement') }}</p>
      <p v-else-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <template v-else-if="piece">
        <header class="entete">
          <RouterLink to="/dressing" class="bouton-icone" :aria-label="t('piece.retour')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </RouterLink>
          <div class="entete__textes">
            <nav class="fil-ariane" :aria-label="t('piece.filAriane')">{{ piece.logement.nom }} › {{ piece.nom }}</nav>
            <h1 class="titre-page">{{ piece.nom }}</h1>
          </div>
          <template v-if="piece.peutGerer">
            <button
              type="button"
              class="bouton-icone seulement-mobile"
              :aria-label="t('piece.ajouterStockage')"
              :aria-expanded="ajoutStockage"
              @click="ajoutStockage = !ajoutStockage"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="19" cy="12" r="2" /></svg>
            </button>
            <button type="button" class="bouton bouton--contour bouton--petit seulement-ordinateur" :aria-expanded="ajoutStockage" @click="ajoutStockage = !ajoutStockage">
              + {{ t('piece.ajouterStockage') }}
            </button>
          </template>
        </header>

        <form v-if="ajoutStockage" class="carte carte--grise ajout" @submit.prevent="creerStockage">
          <h2 class="titre-section">{{ t('piece.nouveauStockage') }}</h2>
          <p v-if="erreursStockage.general" class="message-erreur" role="alert">{{ erreursStockage.general }}</p>
          <div class="ajout__champs">
            <label class="champ">
              <span class="champ__libelle">{{ t('piece.nomStockage') }}</span>
              <input v-model="nomStockage" class="saisie" :class="{ 'saisie--invalide': erreursStockage.nom }" type="text" :placeholder="t('piece.exempleNom')" required />
              <span v-if="erreursStockage.nom" class="champ__erreur">{{ erreursStockage.nom }}</span>
            </label>
            <label class="champ">
              <span class="champ__libelle">{{ t('piece.typeStockage') }}</span>
              <select v-model="typeStockage" class="saisie">
                <option v-for="type in TYPES_STOCKAGE" :key="type" :value="type">{{ t(`piece.types.${type}`) }}</option>
              </select>
            </label>
          </div>
          <div class="boutons boutons--droite">
            <button type="button" class="bouton bouton--contour bouton--petit" @click="ajoutStockage = false">{{ t('commun.annuler') }}</button>
            <button type="submit" class="bouton bouton--petit">{{ t('commun.ajouter') }}</button>
          </div>
        </form>

        <p v-if="piece.stockages.length === 0" class="etat">
          {{ t('piece.aucunStockage') }}<template v-if="piece.peutGerer"> {{ t('piece.ajoutezStockage') }}</template>
        </p>

        <!-- Chaque stockage de la pièce, avec la grille de ses articles -->
        <section v-for="stockage in piece.stockages" :key="stockage.id" class="stockage" :aria-labelledby="`stockage-${stockage.id}`">
          <div class="ligne-titre">
            <h2 :id="`stockage-${stockage.id}`" class="titre-section">{{ stockage.nom }}</h2>
            <span class="texte-secondaire">{{ t('piece.articles', stockage.nbArticles) }}</span>
          </div>
          <div v-if="articlesDe(stockage).length" class="grille-photos">
            <RouterLink v-for="article in articlesDe(stockage)" :key="article.id" :to="`/articles/${article.id}`" class="article" :title="article.titre">
              <VignetteArticle :photo="article.photo" :couleur="article.couleur" carree :icone="36">
                <span v-if="article.statut === 'PRETE'" class="badge badge--plein article__badge">{{ t('piece.prete') }}</span>
              </VignetteArticle>
              <span class="visuellement-cache">{{ article.titre }}</span>
            </RouterLink>
          </div>
          <p v-else class="vide">
            {{ t('piece.aucunArticle') }}
            <RouterLink :to="{ name: 'scan' }" class="lien">{{ t('piece.ajouterArticle') }}</RouterLink>
          </p>
        </section>
      </template>
    </main>

    <BarreNavigation actif="dressing" />
  </div>
</template>

<style scoped>
.ajout {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ajout__champs {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
}

@media (min-width: 600px) {
  .ajout__champs {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  }
}

.stockage {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.article {
  display: block;
  border-radius: 10px;
}

.article :deep(.vignette) {
  width: 100%;
}

.article:hover :deep(.vignette) {
  outline: 2px solid var(--couleur-primaire);
  outline-offset: 1px;
}

.article__badge {
  position: absolute;
  top: 6px;
  right: 6px;
}

.vide {
  margin: 0;
  padding: 14px;
  border: 1.5px dashed var(--couleur-bordure-pointillee);
  border-radius: 12px;
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>
