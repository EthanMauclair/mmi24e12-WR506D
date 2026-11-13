<script setup>
import { useI18n } from 'vue-i18n'
import { changerLangue, LANGUES } from '@/i18n'

const props = defineProps({
  // Une fois connecté, les données déjà chargées (catégories, couleurs…) viennent de l'API dans l'ancienne
  // langue : la page est rechargée pour les recevoir traduites
  recharger: { type: Boolean, default: false },
})

const { t, locale } = useI18n()

function choisir(code) {
  if (code === locale.value) {
    return
  }
  changerLangue(code)
  if (props.recharger) {
    window.location.reload()
  }
}
</script>

<template>
  <div class="langues" role="radiogroup" :aria-label="t('langue.choisir')">
    <button
      v-for="langue in LANGUES"
      :key="langue.code"
      type="button"
      role="radio"
      class="langue"
      :class="{ 'langue--active': langue.code === locale }"
      :aria-checked="langue.code === locale"
      :lang="langue.code"
      :title="langue.libelle"
      @click="choisir(langue.code)"
    >
      {{ langue.abrege }}
    </button>
  </div>
</template>

<style scoped>
.langues {
  display: inline-flex;
  padding: 3px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
}

.langue {
  min-width: 40px;
  height: 32px;
  padding: 0 8px;
  border: 0;
  border-radius: 9px;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.langue--active {
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font-weight: 800;
  box-shadow: var(--ombre-segment);
}
</style>
