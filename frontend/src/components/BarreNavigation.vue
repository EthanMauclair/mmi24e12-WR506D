<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

defineProps({
  actif: { type: String, default: 'dressing' },
  // Écrans sans barre en bas sur téléphone (fiche, formulaire) : le menu latéral n'apparaît que sur ordinateur
  ordinateurSeulement: { type: Boolean, default: false },
})

const { t } = useI18n()

const onglets = [
  {
    id: 'dressing',
    route: '/dressing',
    traces: ['m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'M9 22V12h6v10'],
  },
  {
    id: 'tenues',
    route: '/tenues',
    traces: ['M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z'],
  },
  { id: 'scan', central: true },
  {
    id: 'marche',
    route: '/marche',
    traces: ['M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z'],
    cercle: { cx: 7, cy: 7, r: 1 },
  },
  {
    id: 'profil',
    route: '/profil',
    traces: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'],
    cercle: { cx: 12, cy: 7, r: 4 },
  },
]
</script>

<template>
  <nav class="barre barre-navigation" :class="{ 'barre--ordinateur': ordinateurSeulement }" :aria-label="t('navigation.principale')">
    <!-- Nom de l'application, affiché seulement dans le menu latéral sur ordinateur -->
    <RouterLink to="/dressing" class="marque">
      <span class="marque__logo">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6a2 2 0 1 1 2 2c-1 0-2 .7-2 1.6v.9" /><path d="M12 10.5 2.6 17a1.4 1.4 0 0 0 .8 2.5h17.2a1.4 1.4 0 0 0 .8-2.5z" /></svg>
      </span>
      {{ t('navigation.marque') }}
    </RouterLink>
    <template v-for="onglet in onglets" :key="onglet.id">
      <RouterLink v-if="onglet.central" to="/scan" class="scan" :aria-label="t('navigation.scanner')">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10" /></svg>
        <span class="scan__libelle">{{ t('navigation.scanner') }}</span>
      </RouterLink>
      <RouterLink
        v-else
        :to="onglet.route"
        class="onglet"
        :class="{ 'onglet--actif': onglet.id === actif }"
        :aria-current="onglet.id === actif ? 'page' : undefined"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="onglet.id === actif ? 2.2 : 2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path v-for="trace in onglet.traces" :key="trace" :d="trace" />
          <circle v-if="onglet.cercle" v-bind="onglet.cercle" />
        </svg>
        {{ t(`navigation.${onglet.id}`) }}
      </RouterLink>
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

.marque,
.scan__libelle {
  display: none;
}

/* Écran sans barre du bas sur téléphone et tablette */
@media (max-width: 1023px) {
  .barre--ordinateur {
    display: none;
  }
}

/* Ordinateur : la barre du bas devient un menu latéral fixe, le scan en tête */
@media (min-width: 1024px) {
  .barre {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 10;
    width: var(--largeur-menu);
    height: auto;
    padding: 22px 14px;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 6px;
    border-top: 0;
    border-right: 1.5px solid var(--couleur-bordure);
    background: var(--couleur-fond);
  }

  .marque {
    order: -2;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 14px 6px;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--couleur-texte);
  }

  .marque__logo {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--couleur-primaire);
    color: var(--couleur-sur-primaire);
  }

  .scan {
    order: -1;
    width: auto;
    height: 46px;
    margin: 0 0 10px;
    gap: 10px;
    border-radius: 12px;
    box-shadow: none;
    font-size: 14px;
    font-weight: 700;
  }

  .scan:hover {
    background: var(--couleur-primaire-fonce);
    color: var(--couleur-sur-primaire);
  }

  .scan__libelle {
    display: inline;
  }

  .onglet {
    flex-direction: row;
    gap: 12px;
    min-width: 0;
    height: 44px;
    padding: 0 12px;
    border-radius: 10px;
    font-size: 14px;
  }

  .onglet:hover {
    background: var(--couleur-separateur);
    color: var(--couleur-texte);
  }

  .onglet--actif,
  .onglet--actif:hover {
    background: var(--couleur-primaire);
    color: var(--couleur-sur-primaire);
  }
}
</style>
