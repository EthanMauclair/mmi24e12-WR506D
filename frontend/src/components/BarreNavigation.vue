<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  actif: { type: String, default: 'dressing' },
})

// Les écrans Tenues, Marché et Profil n'existent pas encore : pas de route, lien provisoire '#'
const onglets = [
  {
    id: 'dressing',
    libelle: 'Dressing',
    route: '/dressing',
    traces: ['m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'M9 22V12h6v10'],
  },
  {
    id: 'tenues',
    libelle: 'Tenues',
    traces: ['M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z'],
  },
  { id: 'scan', central: true },
  {
    id: 'marche',
    libelle: 'Marché',
    traces: ['M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z'],
    cercle: { cx: 7, cy: 7, r: 1 },
  },
  {
    id: 'profil',
    libelle: 'Profil',
    traces: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'],
    cercle: { cx: 12, cy: 7, r: 4 },
  },
]
</script>

<template>
  <nav class="barre" aria-label="Navigation principale">
    <template v-for="onglet in onglets" :key="onglet.id">
      <RouterLink v-if="onglet.central" to="/scan" class="scan" aria-label="Scanner un article">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10" /></svg>
      </RouterLink>
      <component
        :is="onglet.route ? RouterLink : 'a'"
        v-else
        :to="onglet.route"
        :href="onglet.route ? undefined : '#'"
        class="onglet"
        :class="{ 'onglet--actif': onglet.id === actif }"
        :aria-current="onglet.id === actif ? 'page' : undefined"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="onglet.id === actif ? 2.2 : 2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path v-for="trace in onglet.traces" :key="trace" :d="trace" />
          <circle v-if="onglet.cercle" v-bind="onglet.cercle" />
        </svg>
        {{ onglet.libelle }}
      </component>
    </template>
  </nav>
</template>

<style scoped>
.barre {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: space-around;
  align-items: center;
  height: 80px;
  padding: 0 8px 10px;
  box-sizing: border-box;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.onglet {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 60px;
  padding: 6px 0;
  color: var(--couleur-texte-secondaire);
  font-size: 11px;
  font-weight: 600;
}

.onglet--actif {
  color: var(--couleur-primaire);
  font-weight: 700;
}

.scan {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 58px;
  height: 58px;
  margin-top: -22px;
  border-radius: 20px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  box-shadow: var(--ombre-bouton-scan);
}
</style>
