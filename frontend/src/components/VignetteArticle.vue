<script setup>
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'

/**
 * Vignette d'un article : sa photo, ou à défaut sa couleur principale avec une icône.
 * La taille vient du parent (classe ou style) ; « carree » impose un format carré.
 */
defineProps({
  photo: { type: String, default: null },
  couleur: { type: String, default: null },
  carree: { type: Boolean, default: false },
  icone: { type: Number, default: 40 },
})
</script>

<template>
  <span
    class="vignette"
    :class="{ 'vignette--carree': carree, 'vignette--claire': estCouleurClaire(couleur) }"
    :style="{ background: photo ? 'var(--couleur-pastel-sable)' : fondVignette(couleur) }"
  >
    <img v-if="photo" :src="photo" alt="" class="vignette__photo" loading="lazy" />
    <svg v-else :width="icone" :height="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
    <slot />
  </span>
</template>

<style scoped>
.vignette {
  position: relative;
  flex-shrink: 0;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 10px;
  color: var(--couleur-photo-icone);
}

.vignette--carree {
  aspect-ratio: 1 / 1;
}

.vignette--claire {
  border: 1.5px solid var(--couleur-bordure);
  color: var(--couleur-photo-icone-sombre);
}

.vignette__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
