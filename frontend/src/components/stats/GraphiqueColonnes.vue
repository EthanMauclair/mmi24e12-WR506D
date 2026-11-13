<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { localeIntl } from '@/i18n'

/**
 * Histogramme d'une seule série dans le temps (ajouts par jour, par mois…).
 * Chaque colonne affiche sa valeur au survol et au focus clavier ; un tableau donne toutes les valeurs.
 */
const props = defineProps({
  titre: { type: String, required: true },
  // [{ cle, libelle (infobulle et tableau), libelleAxe (court, sous l'axe), valeur }]
  points: { type: Array, required: true },
  hauteur: { type: Number, default: 180 },
})

const { t } = useI18n()

const MARGE = { haut: 12, droite: 4, bas: 24, gauche: 30 }
const conteneur = ref(null)
const largeur = ref(320)
const survol = ref(null)
let observateur = null

onMounted(() => {
  observateur = new ResizeObserver(([entree]) => {
    largeur.value = Math.max(200, Math.floor(entree.contentRect.width))
  })
  observateur.observe(conteneur.value)
})
onBeforeUnmount(() => observateur?.disconnect())

const nombre = (valeur) => new Intl.NumberFormat(localeIntl()).format(valeur)

// Graduation ronde au-dessus du maximum : 0, la moitié et le haut de l'axe
const haut = computed(() => {
  const max = Math.max(...props.points.map((point) => point.valeur), 0)
  if (max <= 4) {
    return 4
  }
  const pas = 10 ** Math.floor(Math.log10(max))
  const arrondi = [1, 2, 2.5, 5, 10].map((m) => m * pas).find((v) => v >= max)
  return arrondi % 2 === 0 ? arrondi : arrondi * 2
})
const graduations = computed(() => [0, haut.value / 2, haut.value])

const zone = computed(() => ({
  largeur: largeur.value - MARGE.gauche - MARGE.droite,
  hauteur: props.hauteur - MARGE.haut - MARGE.bas,
}))
const bande = computed(() => zone.value.largeur / Math.max(props.points.length, 1))
const epaisseur = computed(() => Math.max(2, Math.min(24, bande.value - 2)))
const y = (valeur) => MARGE.haut + zone.value.hauteur * (1 - valeur / haut.value)

// Un libellé d'axe tous les n points, pour qu'ils ne se chevauchent pas (environ 44 px chacun)
const pasLibelles = computed(() => Math.max(1, Math.ceil(44 / bande.value)))

const colonnes = computed(() =>
  props.points.map((point, index) => {
    const centre = MARGE.gauche + bande.value * (index + 0.5)
    const x = centre - epaisseur.value / 2
    const sommet = y(point.valeur)
    const base = y(0)
    const h = base - sommet
    const r = Math.min(4, h, epaisseur.value / 2)
    // Bout arrondi de 4 px en haut, angle droit sur la ligne de base
    const chemin = h <= 0 ? '' : `M${x},${base} V${sommet + r} Q${x},${sommet} ${x + r},${sommet} H${x + epaisseur.value - r} Q${x + epaisseur.value},${sommet} ${x + epaisseur.value},${sommet + r} V${base} Z`
    // Le dernier libellé s'aligne sur le bord droit pour ne pas être coupé
    return { ...point, index, centre, chemin, dernier: index === props.points.length - 1, afficherLibelle: index % pasLibelles.value === (props.points.length - 1) % pasLibelles.value }
  }),
)

const bulle = computed(() => {
  if (survol.value === null) {
    return null
  }
  const colonne = colonnes.value[survol.value]
  const gauche = Math.min(Math.max(colonne.centre, 60), largeur.value - 60)
  return { ...colonne, gauche, haut: Math.max(0, y(colonne.valeur) - 8) }
})
</script>

<template>
  <figure class="graphique">
    <figcaption class="graphique__titre">{{ titre }}</figcaption>
    <div ref="conteneur" class="graphique__zone" @pointerleave="survol = null">
      <svg :width="largeur" :height="hauteur" role="img" :aria-label="titre">
        <g aria-hidden="true">
          <g v-for="graduation in graduations" :key="graduation">
            <line :x1="MARGE.gauche" :x2="largeur - MARGE.droite" :y1="y(graduation)" :y2="y(graduation)" class="graphique__grille" />
            <text :x="MARGE.gauche - 6" :y="y(graduation)" class="graphique__graduation" text-anchor="end" dominant-baseline="middle">{{ nombre(graduation) }}</text>
          </g>
          <template v-for="colonne in colonnes" :key="colonne.cle">
            <path v-if="colonne.chemin" :d="colonne.chemin" class="graphique__colonne" :class="{ 'graphique__colonne--survol': survol === colonne.index }" />
            <text
              v-if="colonne.afficherLibelle"
              :x="colonne.dernier ? largeur - MARGE.droite : colonne.centre"
              :y="hauteur - 6"
              class="graphique__graduation"
              :text-anchor="colonne.dernier ? 'end' : 'middle'"
            >
              {{ colonne.libelleAxe }}
            </text>
          </template>
        </g>
        <!-- Zones de survol : toute la hauteur de la bande, plus large que la colonne -->
        <rect
          v-for="colonne in colonnes"
          :key="`zone-${colonne.cle}`"
          :x="colonne.centre - bande / 2"
          :y="MARGE.haut"
          :width="bande"
          :height="zone.hauteur"
          class="graphique__cible"
          tabindex="0"
          :aria-label="`${colonne.libelle}, ${nombre(colonne.valeur)}`"
          @pointerenter="survol = colonne.index"
          @focus="survol = colonne.index"
          @blur="survol = null"
        />
      </svg>
      <div v-if="bulle" class="bulle" :style="{ left: `${bulle.gauche}px`, top: `${bulle.haut}px` }" aria-hidden="true">
        <strong class="bulle__valeur">{{ nombre(bulle.valeur) }}</strong>
        <span class="bulle__libelle">{{ bulle.libelle }}</span>
      </div>
    </div>
    <details class="graphique__tableau">
      <summary>{{ t('stats.voirDonnees') }}</summary>
      <table>
        <tbody>
          <tr v-for="point in points" :key="point.cle">
            <th scope="row">{{ point.libelle }}</th>
            <td>{{ nombre(point.valeur) }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </figure>
</template>

<style scoped>
.graphique {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.graphique__titre {
  font-size: 14px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.graphique__zone {
  position: relative;
  width: 100%;
}

.graphique__zone svg {
  display: block;
}

.graphique__grille {
  stroke: var(--couleur-grille);
  stroke-width: 1;
}

.graphique__graduation {
  font-size: 11px;
  fill: var(--couleur-texte-secondaire);
  font-variant-numeric: tabular-nums;
}

.graphique__colonne {
  fill: var(--couleur-donnee);
}

.graphique__colonne--survol {
  fill: var(--couleur-donnee-survol);
}

.graphique__cible {
  fill: transparent;
  outline: none;
  cursor: default;
}

.graphique__cible:focus-visible {
  stroke: var(--couleur-primaire);
  stroke-width: 1.5;
}

.bulle {
  position: absolute;
  transform: translate(-50%, -100%);
  padding: 6px 10px;
  border-radius: 10px;
  background: var(--couleur-surface);
  box-shadow: var(--ombre-menu);
  display: flex;
  flex-direction: column;
  align-items: center;
  pointer-events: none;
  white-space: nowrap;
}

.bulle__valeur {
  font-size: 15px;
  color: var(--couleur-texte);
}

.bulle__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.graphique__tableau summary {
  font-size: 12px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
  cursor: pointer;
}

.graphique__tableau table {
  margin-top: 6px;
  border-collapse: collapse;
  font-size: 13px;
}

.graphique__tableau th,
.graphique__tableau td {
  padding: 3px 12px 3px 0;
  text-align: left;
  font-weight: 400;
  font-variant-numeric: tabular-nums;
}
</style>
