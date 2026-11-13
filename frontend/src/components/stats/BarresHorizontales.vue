<script setup>
import { computed } from 'vue'
import { localeIntl } from '@/i18n'

/**
 * Répartition en barres horizontales, de la plus grande à la plus petite, valeur au bout de chaque barre.
 * Une pastille facultative montre la vraie couleur d'un article (couleurs les plus portées).
 */
const props = defineProps({
  titre: { type: String, required: true },
  // [{ cle, libelle, valeur, pastille? }]
  elements: { type: Array, required: true },
  vide: { type: String, default: '' },
  // Part de chaque barre dans le total : à masquer pour un classement (top 5), dont le total n'est pas celui de l'ensemble
  parts: { type: Boolean, default: true },
})

const total = computed(() => props.elements.reduce((somme, element) => somme + element.valeur, 0))
const max = computed(() => Math.max(...props.elements.map((element) => element.valeur), 1))
const nombre = (valeur) => new Intl.NumberFormat(localeIntl()).format(valeur)
const part = (valeur) => new Intl.NumberFormat(localeIntl(), { style: 'percent' }).format(total.value ? valeur / total.value : 0)
</script>

<template>
  <figure class="barres">
    <figcaption class="barres__titre">{{ titre }}</figcaption>
    <p v-if="elements.length === 0" class="barres__vide">{{ vide }}</p>
    <ul v-else class="barres__liste">
      <li v-for="element in elements" :key="element.cle" class="barre" :title="parts ? `${element.libelle}, ${nombre(element.valeur)} (${part(element.valeur)})` : `${element.libelle}, ${nombre(element.valeur)}`">
        <span class="barre__libelle">
          <span v-if="element.pastille" class="barre__pastille" :style="{ background: element.pastille }" aria-hidden="true" />
          {{ element.libelle }}
        </span>
        <span class="barre__piste" aria-hidden="true">
          <span class="barre__remplissage" :style="{ width: `${(element.valeur / max) * 100}%` }" />
        </span>
        <span class="barre__valeur">{{ nombre(element.valeur) }} <span v-if="parts" class="barre__part">· {{ part(element.valeur) }}</span></span>
      </li>
    </ul>
  </figure>
</template>

<style scoped>
.barres {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.barres__titre {
  font-size: 14px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.barres__vide {
  margin: 0;
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
}

.barres__liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.barre {
  display: grid;
  grid-template-columns: minmax(0, 9em) 1fr auto;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.barre__libelle {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--couleur-texte);
}

.barre__pastille {
  width: 10px;
  height: 10px;
  flex-shrink: 0;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 20, 22, 0.15);
}

.barre__piste {
  height: 12px;
}

.barre__remplissage {
  display: block;
  height: 100%;
  min-width: 2px;
  border-radius: 0 4px 4px 0;
  background: var(--couleur-donnee);
}

.barre:hover .barre__remplissage {
  background: var(--couleur-donnee-survol);
}

.barre__valeur {
  font-weight: 700;
  color: var(--couleur-texte);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.barre__part {
  font-weight: 400;
  color: var(--couleur-texte-secondaire);
}
</style>
