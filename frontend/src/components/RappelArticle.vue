<script setup>
import VignetteArticle from '@/components/VignetteArticle.vue'

/**
 * Rappel de l'article concerné par un formulaire (prêt, vente) : petite carte sur téléphone,
 * grande photo carrée avec titre et détails dans la colonne de gauche sur ordinateur.
 */
defineProps({
  article: { type: Object, required: true },
  detail: { type: String, default: '' },
})
</script>

<template>
  <div class="rappel">
    <VignetteArticle class="rappel__vignette" :photo="article.photos?.[0] ?? article.photo ?? null" :couleur="article.couleur?.codeHex ?? article.couleur ?? null" :icone="28" />
    <div class="rappel__textes">
      <b class="rappel__titre">{{ article.titre }}</b>
      <span class="texte-secondaire">{{ detail || article.infos }}</span>
    </div>
  </div>
</template>

<style scoped>
.rappel {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
}

.rappel__vignette {
  width: 56px;
  height: 56px;
}

.rappel__textes {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.rappel__titre {
  overflow-wrap: anywhere;
}

@media (min-width: 1024px) {
  .rappel {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    padding: 0;
    border: 0;
    background: none;
  }

  .rappel__vignette {
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
    border-radius: 14px;
  }

  .rappel__vignette :deep(svg) {
    width: 64px;
    height: 64px;
  }
}
</style>
