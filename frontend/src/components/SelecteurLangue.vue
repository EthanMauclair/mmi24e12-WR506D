<script setup>
import { useI18n } from 'vue-i18n'
import { changerLangue, LANGUES } from '@/i18n'

const props = defineProps({
  // Une fois connecté, les données déjà chargées (catégories, couleurs…) viennent de l'API dans l'ancienne
  // langue : la page est rechargée pour les recevoir traduites
  recharger: { type: Boolean, default: false },
  // Noms complets des langues (Français, English, Português) au lieu de FR / EN / PT
  complet: { type: Boolean, default: false },
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
  <div class="segments langues" :class="{ 'langues--complet': complet }" role="radiogroup" :aria-label="t('langue.choisir')">
    <button
      v-for="langue in LANGUES"
      :key="langue.code"
      type="button"
      role="radio"
      class="segment"
      :class="{ 'segment--actif': langue.code === locale }"
      :aria-checked="langue.code === locale"
      :lang="langue.code"
      :title="langue.libelle"
      @click="choisir(langue.code)"
    >
      {{ complet ? langue.libelle : langue.abrege }}
    </button>
  </div>
</template>

<style scoped>
/* Abréviations FR / EN / PT : sélecteur compact ; noms complets : sélecteur pleine largeur (paramètres du profil) */
.langues {
  display: inline-flex;
  width: 130px;
  flex-shrink: 0;
}

.langues--complet {
  display: flex;
  width: 100%;
  max-width: 320px;
}
</style>
