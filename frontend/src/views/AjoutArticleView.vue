<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import ChoixEmplacement from '@/components/ChoixEmplacement.vue'
import { api } from '@/services/api'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { useReferentielsStore } from '@/stores/referentiels'
import { idLogementActif } from '@/utils/logementActif'

// Champs du détail selon la famille de la catégorie choisie (libellés et exemples : ajout.champs et ajout.exemples)
const CHAMPS_PAR_FAMILLE = {
  VETEMENT: [
    { cle: 'couleurId', type: 'couleur' },
    { cle: 'marque', type: 'texte' },
    { cle: 'taille', type: 'texte' },
    { cle: 'matiere', type: 'texte' },
  ],
  LIVRE: [
    { cle: 'auteur', type: 'texte' },
    { cle: 'editeur', type: 'texte' },
    { cle: 'nbPages', type: 'nombre' },
    { cle: 'anneeParution', type: 'nombre' },
  ],
  VINYLE: [
    { cle: 'artiste', type: 'texte' },
    { cle: 'label', type: 'texte' },
    { cle: 'anneeSortie', type: 'nombre' },
    { cle: 'format', type: 'format' },
  ],
}
CHAMPS_PAR_FAMILLE.ACCESSOIRE = CHAMPS_PAR_FAMILLE.VETEMENT
const DETAIL_PAR_FAMILLE = { VETEMENT: 'vetement', ACCESSOIRE: 'vetement', LIVRE: 'livre', VINYLE: 'vinyle' }

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ajout = useAjoutArticleStore()
const referentiels = useReferentielsStore()

// Le même formulaire sert à l'ajout (après un scan ou en saisie manuelle) et à la modification d'un article
const idArticle = route.name === 'article-modifier' ? route.params.id : null
const origine = idArticle ? 'modification' : route.query.origine === 'vetement' ? 'vetement' : 'manuel'
// Objet réactif du store : il se complète pendant que la fiche est affichée (photo envoyée, suggestions)
const scan = origine === 'vetement' ? ajout.vetement : null
const codeInconnu = origine === 'manuel' ? ajout.codeInconnu : null

// Article modifié : sa photo, ses droits et les valeurs absentes du formulaire (ISBN…)
const articleModifie = ref(null)
const titreEcran = t(`ajout.titres.${origine}`)
const retour = idArticle ? { name: 'article', params: { id: idArticle } } : { name: 'scan' }
const peutChangerConfidentialite = computed(() => !idArticle || articleModifie.value?.peutChangerConfidentialite)

const logementId = ref(null)
const stockages = ref([])
const etiquettesDisponibles = ref([])
const chargement = ref(true)
const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})

const valeurs = reactive({
  titre: '',
  description: '',
  categorieId: null,
  marque: '',
  couleurId: null,
  taille: '',
  matiere: '',
  auteur: '',
  editeur: '',
  nbPages: '',
  anneeParution: '',
  artiste: '',
  label: '',
  anneeSortie: '',
  format: null,
})
const stockageId = ref(null)
const etat = ref(null)
const etiquettesChoisies = ref([])
const estPersonnel = ref(false)

// Photo affichée à gauche : celle du scan, ou celle de l'article modifié
const photo = computed(() => (scan ? scan.apercu ?? scan.photo : articleModifie.value?.photos[0] ?? null))

// Valeurs proposées par l'IA : le champ garde son marqueur tant qu'on ne le modifie pas
const valeursIa = reactive({})

function estRempliParIa(cle) {
  return valeursIa[cle] !== undefined && valeursIa[cle] !== null && valeurs[cle] === valeursIa[cle]
}

const nbChampsIa = computed(() => Object.keys(valeursIa).filter(estRempliParIa).length)
// L'analyse se poursuit après l'ouverture de la fiche : envoi de la photo, puis réponse de l'IA
const analyseEnCours = computed(() => scan && scan.envoi !== 'erreur' && scan.analyse !== 'termine' && scan.analyse !== 'erreur')
const etatIa = computed(() => {
  if (!scan) {
    return ''
  }
  if (scan.envoi === 'erreur') {
    return scan.erreur
  }
  if (analyseEnCours.value) {
    return `${t('ajout.analyseEnCours')} ${t('ajout.remplirPendantAnalyse')}`
  }
  return nbChampsIa.value ? t('ajout.champsPreRemplis', nbChampsIa.value) : scan.ia?.message ?? t('ajout.rienReconnu')
})

/**
 * Applique les suggestions de l'IA, sans écraser ce que l'utilisateur a déjà saisi.
 */
function appliquerSuggestions(proposees) {
  const valeursProposees = {
    titre: proposees.titre,
    categorieId: proposees.categorie?.id,
    marque: proposees.marque,
    couleurId: proposees.couleur?.id,
  }
  for (const [cle, valeur] of Object.entries(valeursProposees)) {
    if (valeur === null || valeur === undefined) {
      continue
    }
    valeursIa[cle] = valeur
    if (valeurs[cle] === '' || valeurs[cle] === null) {
      valeurs[cle] = valeur
    }
  }
}

watch(
  () => scan?.suggestions,
  (proposees) => proposees && appliquerSuggestions(proposees),
  { immediate: true },
)

const famille = computed(() => referentiels.famille(valeurs.categorieId))
// Après la photo d'un vêtement, couleur et marque s'affichent tout de suite, sans attendre la catégorie
const champsDetail = computed(
  () => CHAMPS_PAR_FAMILLE[famille.value ?? (origine === 'vetement' ? 'VETEMENT' : null)] ?? [],
)
const groupesCategories = computed(() =>
  referentiels.categoriesParRacine(origine === 'vetement' ? ['VETEMENT', 'ACCESSOIRE'] : null),
)

function basculerEtiquette(id) {
  etiquettesChoisies.value = etiquettesChoisies.value.includes(id)
    ? etiquettesChoisies.value.filter((autre) => autre !== id)
    : [...etiquettesChoisies.value, id]
}

function nombre(valeur) {
  return valeur === '' || valeur === null ? null : Number(valeur)
}

/**
 * Pré-remplit le formulaire avec l'article à modifier.
 */
function preremplir(article) {
  const formulaire = article.formulaire
  const detail = formulaire.vetement ?? formulaire.livre ?? formulaire.vinyle ?? {}
  for (const cle of Object.keys(valeurs)) {
    if (cle in detail) {
      valeurs[cle] = detail[cle] ?? (typeof valeurs[cle] === 'string' ? '' : null)
    }
  }
  valeurs.titre = formulaire.titre
  valeurs.description = formulaire.description ?? ''
  valeurs.categorieId = formulaire.categorieId
  stockageId.value = formulaire.stockageId
  etat.value = formulaire.etat
  etiquettesChoisies.value = [...formulaire.etiquetteIds]
  estPersonnel.value = article.estPersonnel
}

async function enregistrer() {
  erreur.value = ''
  erreurs.value = {}
  envoiEnCours.value = true

  const detail = DETAIL_PAR_FAMILLE[famille.value]
  // En modification, on repart des valeurs enregistrées pour ne pas effacer ce que le formulaire n'affiche pas
  const origineFormulaire = articleModifie.value?.formulaire ?? {}
  const corps = {
    stockageId: stockageId.value,
    categorieId: valeurs.categorieId,
    titre: valeurs.titre,
    etiquetteIds: etiquettesChoisies.value,
    description: valeurs.description,
    etat: etat.value,
  }
  if (peutChangerConfidentialite.value) {
    corps.estPersonnel = estPersonnel.value
  }
  if (!idArticle) {
    Object.assign(corps, {
      logementId: logementId.value,
      modeAjout: scan ? 'SCAN_IMAGE' : codeInconnu ? 'SCAN_CODE_BARRE' : 'MANUEL',
      photo: scan?.photo ?? null,
    })
  }
  if (detail === 'vetement') {
    corps.vetement = { marque: valeurs.marque, couleurId: valeurs.couleurId, taille: valeurs.taille, matiere: valeurs.matiere }
  } else if (detail === 'livre') {
    corps.livre = {
      ...origineFormulaire.livre,
      isbn: codeInconnu ?? origineFormulaire.livre?.isbn ?? null,
      auteur: valeurs.auteur,
      editeur: valeurs.editeur,
      nbPages: nombre(valeurs.nbPages),
      anneeParution: nombre(valeurs.anneeParution),
    }
  } else if (detail === 'vinyle') {
    corps.vinyle = {
      ...origineFormulaire.vinyle,
      ean: codeInconnu ?? origineFormulaire.vinyle?.ean ?? null,
      artiste: valeurs.artiste,
      label: valeurs.label,
      anneeSortie: nombre(valeurs.anneeSortie),
      format: valeurs.format,
    }
  }

  try {
    const article = idArticle
      ? await api(`/articles/${idArticle}`, { methode: 'PUT', corps })
      : await api('/articles', { methode: 'POST', corps })
    ajout.reinitialiser()
    router.replace({ name: 'article', params: { id: article.id } })
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = Object.keys(e.violations).length ? t('commun.verifierChamps') : e.message
  } finally {
    envoiEnCours.value = false
  }
}

onMounted(async () => {
  // Rechargement de la page après un scan : le résultat n'est plus en mémoire
  if (origine === 'vetement' && !scan) {
    router.replace({ name: 'scan' })
    return
  }
  try {
    if (idArticle) {
      articleModifie.value = await api(`/articles/${idArticle}`)
      if (!articleModifie.value.peutModifier) {
        router.replace(retour)
        return
      }
      preremplir(articleModifie.value)
    }
    logementId.value = idArticle ? articleModifie.value.logementId : await idLogementActif()
    if (logementId.value === null) {
      router.replace({ name: 'dressing' })
      return
    }
    const [rangement] = await Promise.all([api(`/logements/${logementId.value}/rangement`), referentiels.charger()])
    stockages.value = rangement.stockages
    etiquettesDisponibles.value = rangement.etiquettes

    // Code-barres inconnu : on devine la famille d'après le code (ISBN = livre, sinon disque)
    if (codeInconnu && valeurs.categorieId === null) {
      const estIsbn = /^(97[89]\d{10}|\d{9}[\dX])$/.test(codeInconnu)
      const chemin = estIsbn ? 'Livres › Romans › Romans' : 'Musique › Vinyles › Albums (33 tours)'
      valeurs.categorieId = referentiels.categories.find((c) => c.reference === chemin)?.id ?? null
    }
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="retour" class="bouton-icone" :aria-label="t(idArticle ? 'ajout.retourFiche' : scan ? 'ajout.reprendrePhoto' : 'ajout.retourScan')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ titreEcran }}</h1>
        <span v-if="scan" class="bandeau-info etat-ia seulement-ordinateur" aria-live="polite">{{ etatIa }}</span>
      </header>

      <div :class="{ 'colonne-gauche': photo }">
        <!-- Photo : petite carte sur téléphone, grande photo à gauche sur ordinateur -->
        <div v-if="photo" class="photo-article">
          <img :src="photo" :alt="t(scan ? 'ajout.photoScan' : 'ajout.photoArticle')" class="photo-article__image" />
          <div class="photo-article__textes">
            <b class="seulement-mobile photo-article__titre">{{ t(scan ? 'ajout.photoScan' : 'ajout.photoArticle') }}</b>
            <RouterLink v-if="scan" :to="{ name: 'scan' }" class="lien">{{ t('ajout.reprendrePhoto') }}</RouterLink>
            <p class="texte-secondaire seulement-ordinateur">{{ t(scan ? 'ajout.photoConservee' : 'ajout.aideModification') }}</p>
          </div>
        </div>

        <!-- Le formulaire s'affiche tout de suite ; en modification, on attend les valeurs de l'article -->
        <p v-if="chargement && idArticle" class="etat">{{ t('article.chargement') }}</p>

        <form v-else class="formulaire" novalidate @submit.prevent="enregistrer">
          <p v-if="scan" class="bandeau-info seulement-mobile" aria-live="polite">{{ etatIa }}</p>
          <p v-if="codeInconnu" class="bandeau-info">{{ t('ajout.codeInconnu', { code: codeInconnu }) }}</p>
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('ajout.nom') }}</span>
            <input
              v-model.trim="valeurs.titre"
              class="saisie"
              :class="{ 'saisie--suggeree': estRempliParIa('titre'), 'saisie--invalide': erreurs.titre }"
              type="text"
              :placeholder="t('ajout.exempleNom')"
              required
            />
            <span v-if="erreurs.titre" class="champ__erreur">{{ erreurs.titre }}</span>
          </label>

          <div class="champs">
            <label class="champ">
              <span class="champ__libelle">{{ t('article.categorie') }}</span>
              <select v-model="valeurs.categorieId" class="saisie" :class="{ 'saisie--suggeree': estRempliParIa('categorieId'), 'saisie--invalide': erreurs.categorieId }">
                <option :value="null" disabled>{{ t('ajout.choisirCategorie') }}</option>
                <optgroup v-for="groupe in groupesCategories" :key="groupe.racine" :label="groupe.racine">
                  <option v-for="categorie in groupe.elements" :key="categorie.id" :value="categorie.id">{{ categorie.libelle }}</option>
                </optgroup>
              </select>
              <span v-if="erreurs.categorieId" class="champ__erreur">{{ erreurs.categorieId }}</span>
            </label>

            <label v-for="champ in champsDetail" :key="champ.cle" class="champ">
              <span class="champ__libelle">{{ t(`ajout.champs.${champ.cle}`) }}</span>
              <select
                v-if="champ.type === 'couleur'"
                v-model="valeurs[champ.cle]"
                class="saisie"
                :class="{ 'saisie--suggeree': estRempliParIa(champ.cle), 'saisie--invalide': erreurs[champ.cle] }"
              >
                <option :value="null">—</option>
                <option v-for="couleur in referentiels.couleurs" :key="couleur.id" :value="couleur.id">{{ couleur.nom }}</option>
              </select>
              <select v-else-if="champ.type === 'format'" v-model="valeurs[champ.cle]" class="saisie" :class="{ 'saisie--invalide': erreurs[champ.cle] }">
                <option :value="null">—</option>
                <option v-for="format in referentiels.formatsVinyle" :key="format.valeur" :value="format.valeur">{{ format.libelle }}</option>
              </select>
              <input
                v-else
                v-model.trim="valeurs[champ.cle]"
                class="saisie"
                :class="{ 'saisie--suggeree': estRempliParIa(champ.cle), 'saisie--invalide': erreurs[champ.cle] }"
                :type="champ.type === 'nombre' ? 'number' : 'text'"
                :inputmode="champ.type === 'nombre' ? 'numeric' : undefined"
                :placeholder="t(`ajout.exemples.${champ.cle}`)"
              />
              <span v-if="erreurs[champ.cle]" class="champ__erreur">{{ erreurs[champ.cle] }}</span>
            </label>

            <label class="champ">
              <span class="champ__libelle">{{ t('scanCode.etat') }}</span>
              <select v-model="etat" class="saisie" :class="{ 'saisie--invalide': erreurs.etat }">
                <option :value="null">—</option>
                <option v-for="element in referentiels.etats" :key="element.valeur" :value="element.valeur">{{ element.libelle }}</option>
              </select>
            </label>
          </div>

          <ChoixEmplacement v-model="stockageId" :stockages="stockages" :erreur="erreurs.stockageId" />

          <div v-if="etiquettesDisponibles.length" class="etiquettes">
            <span class="champ__libelle">{{ t('article.etiquettes') }}</span>
            <button
              v-for="etiquette in etiquettesDisponibles"
              :key="etiquette.id"
              type="button"
              class="puce puce--petite"
              :class="{ 'puce--active': etiquettesChoisies.includes(etiquette.id) }"
              :aria-pressed="etiquettesChoisies.includes(etiquette.id)"
              @click="basculerEtiquette(etiquette.id)"
            >
              {{ etiquette.nom }}
            </button>
          </div>

          <label class="champ">
            <span class="champ__libelle">{{ t('ajout.note') }}</span>
            <textarea v-model.trim="valeurs.description" class="saisie" :class="{ 'saisie--invalide': erreurs.description }" rows="3" :placeholder="t('ajout.exempleNote')"></textarea>
            <span v-if="erreurs.description" class="champ__erreur">{{ erreurs.description }}</span>
          </label>

          <label v-if="peutChangerConfidentialite" class="carte carte--grise carte--rangee personnel">
            <span class="entete__textes">
              <b class="personnel__titre">{{ t('article.personnel') }}</b>
              <span class="texte-secondaire">{{ t('article.personnelAide') }}</span>
            </span>
            <input v-model="estPersonnel" type="checkbox" role="switch" class="interrupteur" />
          </label>

          <div class="barre-actions barre-actions--dans-page barre-actions--droite">
            <RouterLink :to="retour" class="bouton bouton--contour seulement-ordinateur">{{ t('commun.annuler') }}</RouterLink>
            <!-- On attend seulement que la photo soit enregistrée, pas la réponse de l'IA -->
            <button type="submit" class="bouton enregistrer" :disabled="envoiEnCours || chargement || (scan && scan.envoi !== 'termine')">
              {{
                t(
                  envoiEnCours
                    ? 'ajout.enregistrement'
                    : scan?.envoi === 'en-cours'
                      ? 'ajout.envoiPhoto'
                      : idArticle
                        ? 'ajout.enregistrerModifications'
                        : 'ajout.enregistrerArticle',
                )
              }}
            </button>
          </div>
        </form>
      </div>
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
.etat-ia {
  padding: 6px 10px;
  font-size: 12px;
}

.photo-article {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  margin-bottom: 12px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
}

.photo-article__image {
  width: 64px;
  height: 64px;
  flex-shrink: 0;
  border-radius: 10px;
  object-fit: cover;
  background: var(--couleur-vignette-grege);
}

.photo-article__textes {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
}

.photo-article__titre {
  font-size: 13px;
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

.etiquettes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.personnel {
  cursor: pointer;
}

.personnel__titre {
  font-size: 13px;
}

.enregistrer {
  flex: 1;
}

@media (min-width: 1024px) {
  .colonne-gauche {
    --largeur-colonne-fixe: 300px;
  }

  .photo-article {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    padding: 0;
    margin: 0;
    border: 0;
    background: none;
  }

  .photo-article__image {
    width: 100%;
    height: auto;
    aspect-ratio: 3 / 4;
    border-radius: 14px;
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
}
</style>
