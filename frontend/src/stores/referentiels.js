import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/api'

/**
 * Catégories, couleurs, formats et états : chargés une fois, partagés par les formulaires d'article.
 */
export const useReferentielsStore = defineStore('referentiels', () => {
  const categories = ref([])
  const couleurs = ref([])
  const formatsVinyle = ref([])
  const etats = ref([])
  let chargement = null

  function charger() {
    chargement ??= api('/referentiels')
      .then((donnees) => {
        categories.value = donnees.categories
        couleurs.value = donnees.couleurs
        formatsVinyle.value = donnees.formatsVinyle
        etats.value = donnees.etats
      })
      .catch((erreur) => {
        chargement = null
        throw erreur
      })
    return chargement
  }

  /**
   * Catégories regroupées par racine (Femmes, Hommes, Livres…), pour des <optgroup>.
   */
  function categoriesParRacine(familles = null) {
    const groupes = new Map()
    for (const categorie of categories.value) {
      if (familles && !familles.includes(categorie.famille)) {
        continue
      }
      const [racine, ...reste] = categorie.chemin.split(' › ')
      if (!groupes.has(racine)) {
        groupes.set(racine, [])
      }
      groupes.get(racine).push({ ...categorie, libelle: reste.join(' › ') || racine })
    }
    return [...groupes].map(([racine, elements]) => ({ racine, elements }))
  }

  function famille(idCategorie) {
    return categories.value.find((categorie) => categorie.id === idCategorie)?.famille ?? null
  }

  return { categories, couleurs, formatsVinyle, etats, charger, categoriesParRacine, famille }
})
