import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/api'

/**
 * Résultat du dernier scan, transmis de l'écran caméra à l'écran de vérification.
 */
export const useAjoutArticleStore = defineStore('ajoutArticle', () => {
  // Scan d'un vêtement : la fiche s'ouvre tout de suite, l'envoi de la photo puis l'analyse
  // se poursuivent ici et remplissent cet objet au fur et à mesure :
  // { apercu, envoi, photo, analyse, ia, suggestions, erreur }
  // envoi et analyse valent 'en-cours', 'termine' ou 'erreur'
  const vetement = ref(null)
  // Code-barres reconnu : fiche renvoyée par l'API (type LIVRE ou VINYLE)
  const fiche = ref(null)
  // Code-barres lu mais introuvable dans les bases
  const codeInconnu = ref(null)
  // Message affiché au retour sur la caméra (« Discovery ajouté »)
  const confirmation = ref('')

  /**
   * Envoie la photo puis demande l'analyse à l'IA, sans bloquer l'écran.
   *
   * @param {Blob} jpeg photo déjà réduite et convertie
   */
  async function lancerScanVetement(jpeg) {
    reinitialiser()
    const scan = { apercu: URL.createObjectURL(jpeg), envoi: 'en-cours', photo: null, analyse: null, ia: null, suggestions: null, erreur: '' }
    vetement.value = scan
    // Passer par vetement.value rend les modifications réactives ; on vérifie que ce scan est toujours le courant
    const courant = () => vetement.value?.apercu === scan.apercu ? vetement.value : null

    try {
      const formulaire = new FormData()
      formulaire.append('photo', jpeg, 'scan.jpg')
      const { photo } = await api('/scans/photo', { methode: 'POST', formulaire })
      if (!courant()) {
        return
      }
      Object.assign(courant(), { photo, envoi: 'termine', analyse: 'en-cours' })
    } catch (e) {
      if (courant()) {
        Object.assign(courant(), { envoi: 'erreur', erreur: e.violations?.photo ?? e.message })
      }
      return
    }

    try {
      const { ia, suggestions } = await api('/scans/analyse', { methode: 'POST', corps: { photo: courant().photo } })
      if (courant()) {
        Object.assign(courant(), { analyse: 'termine', ia, suggestions })
      }
    } catch (e) {
      if (courant()) {
        Object.assign(courant(), { analyse: 'erreur', ia: { message: e.message } })
      }
    }
  }

  function reinitialiser() {
    if (vetement.value?.apercu) {
      URL.revokeObjectURL(vetement.value.apercu)
    }
    vetement.value = null
    fiche.value = null
    codeInconnu.value = null
  }

  return { vetement, fiche, codeInconnu, confirmation, lancerScanVetement, reinitialiser }
})
