<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'

// Données d'exemple reprises de la maquette, en attendant l'API
const piece = { nom: 'Chambre', logement: 'Appartement Lyon' }

const stockages = [
  { id: 'armoire', nom: 'Armoire', nbArticles: 64, detail: '3 étagères · 1 penderie' },
  { id: 'commode', nom: 'Commode', nbArticles: 39 },
  { id: 'sous-le-lit', nom: 'Sous le lit' },
]

const articles = [
  { id: 1, stockage: 'armoire', nom: 'Pull col roulé', infos: 'COS · Bordeaux · M', emplacement: 'Étagère 2', fond: 'var(--couleur-vignette-bordeaux)', prete: false },
  { id: 2, stockage: 'armoire', nom: 'Veste en jean', infos: "Levi's · Bleu · S", emplacement: 'Penderie', fond: 'var(--couleur-vignette-denim)', prete: true },
  { id: 3, stockage: 'armoire', nom: 'Chemise rayée', infos: 'Uniqlo · Blanc · M', emplacement: 'Penderie', fond: 'var(--couleur-vignette-grege)', prete: false },
  { id: 4, stockage: 'armoire', nom: 'Sweat zippé', infos: 'Carhartt · Vert · L', emplacement: 'Étagère 1', fond: 'var(--couleur-vignette-kaki)', prete: false },
]

const idStockageActif = ref(stockages[0].id)
const stockageActif = computed(() => stockages.find((s) => s.id === idStockageActif.value))
const articlesAffiches = computed(() => articles.filter((a) => a.stockage === idStockageActif.value))

function libelleOnglet(stockage) {
  return stockage.nbArticles === undefined ? stockage.nom : `${stockage.nom} · ${stockage.nbArticles}`
}
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <RouterLink to="/dressing" class="bouton-icone" aria-label="Retour au dressing">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <div class="entete__titres">
          <nav class="fil-ariane" aria-label="Fil d'Ariane">{{ piece.logement }} › {{ piece.nom }}</nav>
          <h1 class="entete__titre">{{ piece.nom }}</h1>
        </div>
        <button type="button" class="bouton-icone" aria-label="Plus d'options">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="19" cy="12" r="2" /></svg>
        </button>
      </header>

      <div class="onglets" role="tablist" aria-label="Stockages de la pièce">
        <button
          v-for="stockage in stockages"
          :key="stockage.id"
          type="button"
          role="tab"
          class="onglet"
          :class="{ 'onglet--actif': stockage.id === idStockageActif, 'onglet--sans-nombre': stockage.nbArticles === undefined }"
          :aria-selected="stockage.id === idStockageActif"
          @click="idStockageActif = stockage.id"
        >
          {{ libelleOnglet(stockage) }}
        </button>
      </div>

      <div class="stockage">
        <div class="stockage__textes">
          <span class="stockage__nom">{{ stockageActif.nom }}</span>
          <span v-if="stockageActif.detail" class="stockage__detail">{{ stockageActif.detail }}</span>
        </div>
        <button type="button" class="filtrer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 3H2l8 9.46V19l4 2v-8.54z" /></svg>
          Filtrer
        </button>
      </div>

      <div v-if="articlesAffiches.length" class="articles">
        <RouterLink v-for="article in articlesAffiches" :key="article.id" :to="`/articles/${article.id}`" class="article">
          <span class="article__vignette" :style="{ background: article.fond }">
            <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
            <span class="article__emplacement">{{ article.emplacement }}</span>
            <span v-if="article.prete" class="article__prete">Prêté</span>
          </span>
          <span class="article__textes">
            <span class="article__nom">{{ article.nom }}</span>
            <span class="article__infos">{{ article.infos }}</span>
          </span>
        </RouterLink>
      </div>
      <p v-else class="vide">Aucun article dans ce stockage pour le moment.</p>
    </main>

    <BarreNavigation actif="dressing" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 16px 20px 0;
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
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.fil-ariane {
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

/* Les onglets défilent horizontalement plutôt que de passer sur deux lignes */
.onglets {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}

.onglet {
  flex-shrink: 0;
  white-space: nowrap;
  height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.onglet--sans-nombre {
  padding: 0 14px;
}

.onglet--actif {
  border: 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-weight: 700;
}

.stockage {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--couleur-primaire-pale);
}

.stockage__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stockage__nom {
  font-size: 15px;
  font-weight: 800;
  color: var(--couleur-primaire-fonce);
}

.stockage__detail {
  font-size: 12.5px;
  color: var(--couleur-primaire);
  font-weight: 600;
}

.filtrer {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  padding: 0 12px;
  border-radius: 12px;
  border: 0;
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.articles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.article {
  display: flex;
  flex-direction: column;
  gap: 8px;
  color: var(--couleur-texte);
}

.article__vignette {
  position: relative;
  height: 150px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
}

.article__emplacement {
  position: absolute;
  left: 8px;
  bottom: 8px;
  padding: 3px 8px;
  border-radius: 8px;
  background: var(--couleur-photo-etiquette);
  color: var(--couleur-texte);
  font-size: 11px;
  font-weight: 700;
}

.article__prete {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 3px 8px;
  border-radius: 8px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-size: 11px;
  font-weight: 700;
}

.article__textes {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 0 2px;
}

.article__nom {
  font-size: 14px;
  font-weight: 700;
}

.article__infos {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
}

.vide {
  margin: 0;
  font-size: 14px;
  color: var(--couleur-texte-secondaire);
}
</style>
