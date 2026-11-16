<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import ChoixEmplacement from '@/components/ChoixEmplacement.vue'
import { api } from '@/services/api'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { useReferentielsStore } from '@/stores/referentiels'
import { idLogementActif } from '@/utils/logementActif'

// Champs propres au type, après le titre (libellés : clés de traduction)
const CHAMPS = {
  VINYLE: [
    { cle: 'artiste', libelle: 'ajout.champs.artiste', type: 'texte' },
    { cle: 'label', libelle: 'ajout.champs.label', type: 'texte' },
    { cle: 'anneeSortie', libelle: 'ajout.champs.anneeSortie', type: 'nombre' },
    { cle: 'format', libelle: 'ajout.champs.format', type: 'format' },
    { cle: 'nbDisques', libelle: 'scanCode.disques', type: 'nombre' },
  ],
  LIVRE: [
    { cle: 'auteur', libelle: 'ajout.champs.auteur', type: 'texte' },
    { cle: 'editeur', libelle: 'ajout.champs.editeur', type: 'texte' },
    { cle: 'nbPages', libelle: 'ajout.champs.nbPages', type: 'nombre' },
    { cle: 'anneeParution', libelle: 'ajout.champs.anneeSortie', type: 'nombre' },
  ],
}

const { t } = useI18n()
const router = useRouter()
const ajout = useAjoutArticleStore()
const referentiels = useReferentielsStore()

const fiche = ajout.fiche
const estVinyle = fiche?.type === 'VINYLE'

const logementId = ref(null)
const stockages = ref([])
const chargement = ref(true)
const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})
const couvertureVisible = ref(Boolean(fiche?.couverture))

const valeurs = reactive({
  titre: fiche?.titre ?? '',
  categorieId: fiche?.categorie?.id ?? null,
  etat: null,
  ...fiche?.vinyle,
  ...fiche?.livre,
})
const stockageId = ref(null)

// Valeurs venues de la base en ligne : le champ garde son marqueur tant qu'on ne le modifie pas
const valeursBase = { ...valeurs }
const estPreRempli = (cle) => valeursBase[cle] !== null && valeursBase[cle] !== undefined && valeursBase[cle] !== '' && valeurs[cle] === valeursBase[cle]

const champs = computed(() => CHAMPS[fiche?.type] ?? [])
const groupesCategories = computed(() => referentiels.categoriesParRacine([fiche?.type]))

function nombre(valeur) {
  return valeur === '' || valeur === null || valeur === undefined ? null : Number(valeur)
}

/**
 * @param {boolean} scannerLeSuivant revenir à la caméra au lieu d'ouvrir la fiche de l'article
 */
async function enregistrer(scannerLeSuivant) {
  erreur.value = ''
  erreurs.value = {}
  envoiEnCours.value = true

  const corps = {
    logementId: logementId.value,
    stockageId: stockageId.value,
    categorieId: valeurs.categorieId,
    titre: valeurs.titre,
    etat: valeurs.etat,
    modeAjout: 'SCAN_CODE_BARRE',
    couverture: couvertureVisible.value ? fiche.couverture : null,
  }
  if (estVinyle) {
    corps.vinyle = {
      ean: valeurs.ean,
      artiste: valeurs.artiste,
      label: valeurs.label,
      anneeSortie: nombre(valeurs.anneeSortie),
      format: valeurs.format,
      nbDisques: nombre(valeurs.nbDisques),
    }
  } else {
    corps.livre = {
      isbn: valeurs.isbn,
      auteur: valeurs.auteur,
      editeur: valeurs.editeur,
      nbPages: nombre(valeurs.nbPages),
      anneeParution: nombre(valeurs.anneeParution),
    }
  }

  try {
    const article = await api('/articles', { methode: 'POST', corps })
    ajout.reinitialiser()
    if (scannerLeSuivant) {
      ajout.confirmation = t('scanCode.ajoute', { titre: article.titre })
      router.replace({ name: 'scan' })
    } else {
      router.replace({ name: 'article', params: { id: article.id } })
    }
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = Object.keys(e.violations).length ? t('commun.verifierChamps') : e.message
  } finally {
    envoiEnCours.value = false
  }
}

onMounted(async () => {
  if (!fiche) {
    router.replace({ name: 'scan' })
    return
  }
  try {
    logementId.value = await idLogementActif()
    if (logementId.value === null) {
      router.replace({ name: 'dressing' })
      return
    }
    const [rangement] = await Promise.all([api(`/logements/${logementId.value}/rangement`), referentiels.charger()])
    stockages.value = rangement.stockages
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div v-if="fiche" class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="{ name: 'scan' }" class="bouton-icone" :aria-label="t('scanCode.scannerANouveau')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t(estVinyle ? 'scanCode.vinyleTrouve' : 'scanCode.livreTrouve') }}</h1>
        <span class="bandeau-info source seulement-ordinateur">{{ t('scanCode.preRempliDepuis', { source: fiche.source }) }}</span>
      </header>

      <div class="colonne-gauche">
        <!-- Pochette (et disque pour un vinyle) : visuels de la base en ligne -->
        <div class="visuel" :class="{ 'visuel--livre': !estVinyle }">
          <div class="visuel__pochette">
            <img v-if="couvertureVisible" :src="fiche.couverture" alt="" class="visuel__image" @error="couvertureVisible = false" />
            <svg v-else-if="estVinyle" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" /></svg>
            <svg v-else width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z" /><path d="M6.5 17A2.5 2.5 0 0 0 4 19.5 2.5 2.5 0 0 0 6.5 22H20v-5" /></svg>
          </div>
          <div v-if="estVinyle" class="visuel__disque" aria-hidden="true">
            <span class="visuel__centre"></span>
          </div>
          <p class="texte-secondaire visuel__aide seulement-ordinateur">{{ t('scanCode.visuelsBase', { source: fiche.source }) }}</p>
        </div>

        <form class="formulaire" novalidate @submit.prevent="enregistrer(false)">
          <p class="bandeau-info seulement-mobile">{{ t('scanCode.preRempliDepuis', { source: fiche.source }) }} · {{ estVinyle ? 'EAN' : 'ISBN' }} {{ fiche.code }}</p>
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('scanCode.titre') }}</span>
            <input v-model.trim="valeurs.titre" class="saisie" :class="{ 'saisie--suggeree': estPreRempli('titre'), 'saisie--invalide': erreurs.titre }" type="text" />
            <span v-if="erreurs.titre" class="champ__erreur">{{ erreurs.titre }}</span>
          </label>

          <div class="champs">
            <label v-for="champ in champs" :key="champ.cle" class="champ">
              <span class="champ__libelle">{{ t(champ.libelle) }}</span>
              <select v-if="champ.type === 'format'" v-model="valeurs[champ.cle]" class="saisie" :class="{ 'saisie--suggeree': estPreRempli(champ.cle) }">
                <option :value="null">—</option>
                <option v-for="format in referentiels.formatsVinyle" :key="format.valeur" :value="format.valeur">{{ format.libelle }}</option>
              </select>
              <input
                v-else
                v-model.trim="valeurs[champ.cle]"
                class="saisie"
                :class="{ 'saisie--suggeree': estPreRempli(champ.cle), 'saisie--invalide': erreurs[champ.cle] }"
                :type="champ.type === 'nombre' ? 'number' : 'text'"
                :inputmode="champ.type === 'nombre' ? 'numeric' : undefined"
              />
              <span v-if="erreurs[champ.cle]" class="champ__erreur">{{ erreurs[champ.cle] }}</span>
            </label>

            <label class="champ">
              <span class="champ__libelle">{{ t('scanCode.etat') }}</span>
              <select v-model="valeurs.etat" class="saisie">
                <option :value="null">—</option>
                <option v-for="etat in referentiels.etats" :key="etat.valeur" :value="etat.valeur">{{ etat.libelle }}</option>
              </select>
            </label>

            <label class="champ">
              <span class="champ__libelle">{{ t('article.categorie') }}</span>
              <select v-model="valeurs.categorieId" class="saisie" :class="{ 'saisie--suggeree': estPreRempli('categorieId'), 'saisie--invalide': erreurs.categorieId }">
                <option :value="null" disabled>{{ t('scanCode.choisir') }}</option>
                <optgroup v-for="groupe in groupesCategories" :key="groupe.racine" :label="groupe.racine">
                  <option v-for="categorie in groupe.elements" :key="categorie.id" :value="categorie.id">{{ categorie.libelle }}</option>
                </optgroup>
              </select>
              <span v-if="erreurs.categorieId" class="champ__erreur">{{ erreurs.categorieId }}</span>
            </label>

            <ChoixEmplacement v-model="stockageId" :stockages="stockages" :erreur="erreurs.stockageId" />
          </div>

          <div class="barre-actions barre-actions--dans-page barre-actions--droite">
            <RouterLink :to="{ name: 'scan' }" class="bouton bouton--contour seulement-ordinateur">{{ t('commun.annuler') }}</RouterLink>
            <button type="submit" class="bouton enregistrer" :disabled="envoiEnCours || chargement">
              {{ t(envoiEnCours ? 'ajout.enregistrement' : 'ajout.enregistrerArticle') }}
            </button>
          </div>
          <button type="button" class="lien suivant" :disabled="envoiEnCours || chargement" @click="enregistrer(true)">{{ t('scanCode.suivant') }}</button>
        </form>
      </div>
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
.source {
  padding: 6px 10px;
  font-size: 12px;
}

/* Pochette et disque côte à côte, le disque glissé derrière la pochette */
.visuel {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 6px 0 14px;
}

.visuel__pochette {
  position: relative;
  z-index: 1;
  width: 130px;
  height: 130px;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 10px;
  background: var(--couleur-pochette);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
  box-shadow: var(--ombre-pochette);
}

.visuel--livre .visuel__pochette {
  width: 110px;
  height: 150px;
}

.visuel__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.visuel__disque {
  width: 118px;
  height: 118px;
  flex-shrink: 0;
  margin-left: -34px;
  border-radius: 50%;
  background: var(--couleur-disque);
  display: flex;
  align-items: center;
  justify-content: center;
}

.visuel__centre {
  width: 34px;
  height: 34px;
  box-sizing: border-box;
  border-radius: 50%;
  background: var(--couleur-primaire);
  border: 6px solid var(--couleur-disque-centre-bord);
}

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.champs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.enregistrer {
  flex: 1;
}

.suivant {
  align-self: center;
  margin-top: 6px;
}

@media (min-width: 1024px) {
  .colonne-gauche {
    --largeur-colonne-fixe: 300px;
  }

  .visuel {
    flex-direction: column;
    gap: 14px;
    padding: 0;
  }

  .visuel__pochette {
    width: 240px;
    height: 240px;
  }

  .visuel--livre .visuel__pochette {
    width: 200px;
    height: 280px;
  }

  .visuel__disque {
    width: 150px;
    height: 150px;
    margin: 0;
  }

  .visuel__aide {
    text-align: center;
  }

  .formulaire {
    gap: 12px;
  }

  .champs {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .enregistrer {
    flex: none;
  }

  .suivant {
    align-self: flex-end;
  }
}
</style>
