<script setup>
import { computed } from 'vue'
import { localeIntl } from '@/i18n'

/**
 * Indicateur chiffré, comme sur les maquettes : la valeur (nombre ou montant déjà formaté),
 * puis le libellé et sa précision facultative (« Articles · 2 personnels »).
 */
const props = defineProps({
  libelle: { type: String, required: true },
  valeur: { type: [Number, String], required: true },
  detail: { type: String, default: '' },
  alerte: { type: Boolean, default: false },
})

const affichee = computed(() => (typeof props.valeur === 'number' ? new Intl.NumberFormat(localeIntl()).format(props.valeur) : props.valeur))
</script>

<template>
  <div class="chiffre tuile">
    <span class="chiffre__valeur">{{ affichee }}</span>
    <span class="chiffre__libelle">
      {{ libelle }}<template v-if="detail"> · <span :class="{ 'tuile__alerte': alerte }">{{ detail }}</span></template>
    </span>
  </div>
</template>

<style scoped>
.tuile__alerte {
  color: var(--couleur-alerte);
  font-weight: 700;
}
</style>
