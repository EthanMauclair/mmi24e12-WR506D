<script setup>
const emplacement = defineModel({ type: [Number, null], default: null })

defineProps({
  libelle: { type: String, default: 'Emplacement' },
  // Bordure et libellé à la couleur principale (écran « Vinyle trouvé »)
  accentue: { type: Boolean, default: false },
  stockages: { type: Array, required: true },
  erreur: { type: String, default: '' },
})
</script>

<template>
  <label class="emplacement" :class="{ 'emplacement--accentue': accentue, 'emplacement--invalide': erreur }">
    <span class="emplacement__libelle">{{ libelle }}</span>
    <span class="emplacement__ligne">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="emplacement__icone"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
      <select v-model="emplacement" class="emplacement__choix">
        <option :value="null">À ranger plus tard</option>
        <option v-for="stockage in stockages" :key="stockage.id" :value="stockage.id">{{ stockage.chemin }}</option>
      </select>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="emplacement__chevron"><path d="m6 9 6 6 6-6" /></svg>
    </span>
    <span v-if="erreur" class="champ__erreur">{{ erreur }}</span>
  </label>
</template>

<style scoped>
.emplacement {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1.5px solid var(--couleur-bordure);
}

.emplacement--accentue {
  border-color: var(--couleur-primaire);
}

.emplacement--invalide {
  border-color: var(--couleur-erreur);
}

.emplacement__libelle {
  font-size: 12px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.emplacement--accentue .emplacement__libelle {
  color: var(--couleur-primaire);
}

.emplacement__ligne {
  display: flex;
  align-items: center;
  gap: 8px;
}

.emplacement__icone {
  flex-shrink: 0;
  color: var(--couleur-primaire);
}

.emplacement__choix {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  font-weight: 700;
  color: var(--couleur-texte);
  appearance: none;
  cursor: pointer;
}

.emplacement__chevron {
  flex-shrink: 0;
  color: var(--couleur-texte-secondaire);
}

.emplacement:focus-within {
  border-color: var(--couleur-primaire);
}
</style>
