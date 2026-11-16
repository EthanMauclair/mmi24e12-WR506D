<script setup>
import { useI18n } from 'vue-i18n'

/**
 * Trois atouts de l'application, dans la présentation de la connexion sur ordinateur.
 */
const { t } = useI18n()

const ATOUTS = [
  { cle: 'scan', trace: 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10' },
  { cle: 'rangement', trace: 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0ZM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z' },
  { cle: 'partage', trace: 'M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01' },
]
</script>

<template>
  <ul class="atouts">
    <li v-for="atout in ATOUTS" :key="atout.cle" class="atout">
      <span class="atout__icone">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="atout.trace" /></svg>
      </span>
      <b class="atout__titre">{{ t(`accueil.atouts.${atout.cle}`) }}</b>
    </li>
  </ul>
</template>

<style scoped>
.atouts {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.atout {
  display: flex;
  align-items: center;
  gap: 12px;
}

.atout__icone {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  box-sizing: border-box;
  border: 2px solid var(--couleur-primaire);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-primaire);
}

.atout__titre {
  font-size: 14px;
}
</style>
