import { ref } from 'vue'
import { defineStore } from 'pinia'

/**
 * Résultat du dernier scan, transmis de l'écran caméra à l'écran de vérification.
 */
export const useAjoutArticleStore = defineStore('ajoutArticle', () => {
  // Scan d'un vêtement : { photo, apercu, ia, suggestions }
  const vetement = ref(null)
  // Code-barres reconnu : fiche renvoyée par l'API (type LIVRE ou VINYLE)
  const fiche = ref(null)
  // Code-barres lu mais introuvable dans les bases
  const codeInconnu = ref(null)
  // Message affiché au retour sur la caméra (« Discovery ajouté »)
  const confirmation = ref('')

  function reinitialiser() {
    if (vetement.value?.apercu) {
      URL.revokeObjectURL(vetement.value.apercu)
    }
    vetement.value = null
    fiche.value = null
    codeInconnu.value = null
  }

  return { vetement, fiche, codeInconnu, confirmation, reinitialiser }
})
