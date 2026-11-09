<script setup>
import { computed } from 'vue'

const props = defineProps({
  membre: { type: Object, required: true },
  taille: { type: Number, default: 40 },
  anneau: { type: Boolean, default: false },
})

// Sans photo, l'initiale du pseudo sur un fond choisi d'après le pseudo : chaque membre garde sa couleur
const FONDS = ['primaire', 'avatar-ardoise', 'vignette-kaki', 'vignette-denim', 'vignette-bordeaux'].map((nom) => `var(--couleur-${nom})`)

const fond = computed(() => {
  const somme = [...props.membre.pseudo].reduce((total, lettre) => total + lettre.charCodeAt(0), 0)
  return FONDS[somme % FONDS.length]
})
</script>

<template>
  <span
    class="avatar"
    :class="{ 'avatar--anneau': anneau }"
    :style="{ width: `${taille}px`, height: `${taille}px`, fontSize: `${Math.round(taille * 0.38)}px`, background: fond }"
    aria-hidden="true"
  >
    <img v-if="membre.avatar" :src="membre.avatar" alt="" class="avatar__image" />
    <template v-else>{{ membre.pseudo.charAt(0).toUpperCase() }}</template>
  </span>
</template>

<style scoped>
.avatar {
  flex-shrink: 0;
  border-radius: 50%;
  overflow: hidden;
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
}

.avatar--anneau {
  box-shadow: 0 0 0 3px var(--couleur-fond), 0 0 0 5px var(--couleur-anneau-avatar);
}

.avatar__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
