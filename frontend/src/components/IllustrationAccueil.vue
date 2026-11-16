<script setup>
/**
 * Illustration décorative de la connexion et de l'inscription : quelques articles rangés
 * (vêtements, vinyle, livre) aux couleurs de l'application, à la place d'une photo.
 */
const ARTICLES = [
  { fond: 'var(--couleur-vignette-bordeaux)', trace: 'M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z' },
  { fond: 'var(--couleur-pochette)', trace: 'M9 18V5l12-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM21 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z' },
  { fond: 'var(--couleur-vignette-denim)', trace: 'M12 6a2 2 0 1 1 2 2c-1 0-2 .7-2 1.6v.9M12 10.5 2.6 17a1.4 1.4 0 0 0 .8 2.5h17.2a1.4 1.4 0 0 0 .8-2.5z' },
  { fond: 'var(--couleur-vignette-kaki)', trace: 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5zM6.5 17A2.5 2.5 0 0 0 4 19.5 2.5 2.5 0 0 0 6.5 22H20v-5' },
  { fond: 'var(--couleur-vignette-grege)', trace: 'M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z' },
  { fond: 'var(--couleur-primaire)', trace: 'M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01' },
]
</script>

<template>
  <div class="illustration" aria-hidden="true">
    <span v-for="(article, index) in ARTICLES" :key="index" class="illustration__article" :style="{ background: article.fond }">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path :d="article.trace" /></svg>
    </span>
  </div>
</template>

<style scoped>
.illustration {
  min-height: 150px;
  padding: 14px;
  box-sizing: border-box;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  border-radius: 14px;
  background: var(--couleur-primaire-pale);
}

.illustration__article {
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
}
</style>
