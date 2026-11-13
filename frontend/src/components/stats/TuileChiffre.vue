<script setup>
import { computed } from 'vue'
import { localeIntl } from '@/i18n'

/**
 * Indicateur chiffré : libellé, valeur (nombre ou montant déjà formaté) et précision facultative.
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
  <div class="tuile">
    <span class="tuile__libelle">{{ libelle }}</span>
    <span class="tuile__valeur">{{ affichee }}</span>
    <span v-if="detail" class="tuile__detail" :class="{ 'tuile__detail--alerte': alerte }">
      <svg v-if="alerte" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
      {{ detail }}
    </span>
  </div>
</template>

<style scoped>
.tuile {
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.tuile__libelle {
  font-size: 12px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
}

.tuile__valeur {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--couleur-texte);
}

.tuile__detail {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.tuile__detail--alerte {
  color: var(--couleur-alerte);
  font-weight: 700;
}
</style>
