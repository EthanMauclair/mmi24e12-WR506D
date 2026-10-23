import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/api'

/**
 * Utilisateur connecté. La session elle-même est un cookie géré par Symfony.
 */
export const useSessionStore = defineStore('session', () => {
  const utilisateur = ref(null)
  const verifiee = ref(false)

  async function verifier() {
    if (verifiee.value) {
      return
    }
    try {
      utilisateur.value = await api('/moi')
    } catch (erreur) {
      if (erreur.statut !== 401) {
        throw erreur
      }
      utilisateur.value = null
    }
    verifiee.value = true
  }

  async function connexion(email, motDePasse) {
    utilisateur.value = await api('/connexion', { methode: 'POST', corps: { email, motDePasse } })
    verifiee.value = true
  }

  async function inscription(donnees) {
    utilisateur.value = await api('/inscription', { methode: 'POST', corps: donnees })
    verifiee.value = true
  }

  async function deconnexion() {
    await api('/deconnexion', { methode: 'POST' })
    oublier()
  }

  function oublier() {
    utilisateur.value = null
    verifiee.value = true
  }

  return { utilisateur, verifier, connexion, inscription, deconnexion, oublier }
})
