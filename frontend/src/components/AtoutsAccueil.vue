<script setup>
import { useI18n } from 'vue-i18n'

/**
 * Atouts de l'application, dans le bandeau de la connexion et de l'inscription.
 * Affichés seulement sur ordinateur, où le bandeau devient un panneau latéral.
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
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="atout.trace" /></svg>
      </span>
      <span class="atout__textes">
        <span class="atout__titre">{{ t(`accueil.atouts.${atout.cle}.titre`) }}</span>
        <span class="atout__texte">{{ t(`accueil.atouts.${atout.cle}.texte`) }}</span>
      </span>
    </li>
  </ul>
</template>

<style scoped>
.atouts {
  display: none;
}

@media (min-width: 1024px) {
  .atouts {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 18px;
  }
}

.atout {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.atout__icone {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  border-radius: 13px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.atout__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.atout__titre {
  font-size: 15px;
  font-weight: 700;
}

.atout__texte {
  font-size: 13.5px;
  line-height: 1.45;
  opacity: 0.85;
}
</style>
