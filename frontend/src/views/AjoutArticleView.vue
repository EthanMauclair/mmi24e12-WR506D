<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import ChoixEmplacement from '@/components/ChoixEmplacement.vue'
import { api } from '@/services/api'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { useReferentielsStore } from '@/stores/referentiels'
import { idLogementActif } from '@/utils/logementActif'

// Champs du détail selon la famille de la catégorie choisie
const CHAMPS_PAR_FAMILLE = {
  VETEMENT: [
    { cle: 'marque', libelle: 'Marque', type: 'texte', placeholder: 'Ex. COS' },
    { cle: 'couleurId', libelle: 'Couleur', type: 'couleur' },
    { cle: 'taille', libelle: 'Taille', type: 'texte', placeholder: 'Ex. M' },
    { cle: 'matiere', libelle: 'Matière', type: 'texte', placeholder: 'Ex. Laine' },
  ],
  LIVRE: [
    { cle: 'auteur', libelle: 'Auteur', type: 'texte', placeholder: 'Ex. Albert Camus' },
    { cle: 'editeur', libelle: 'Éditeur', type: 'texte', placeholder: 'Ex. Gallimard' },
    { cle: 'nbPages', libelle: 'Pages', type: 'nombre', placeholder: 'Ex. 194' },
    { cle: 'anneeParution', libelle: 'Année', type: 'nombre', placeholder: 'Ex. 1942' },
  ],
  VINYLE: [
    { cle: 'artiste', libelle: 'Artiste', type: 'texte', placeholder: 'Ex. Daft Punk' },
    { cle: 'label', libelle: 'Label', type: 'texte', placeholder: 'Ex. Virgin' },
    { cle: 'anneeSortie', libelle: 'Année', type: 'nombre', placeholder: 'Ex. 2001' },
    { cle: 'format', libelle: 'Format', type: 'format' },
  ],
}
CHAMPS_PAR_FAMILLE.ACCESSOIRE = CHAMPS_PAR_FAMILLE.VETEMENT
const DETAIL_PAR_FAMILLE = { VETEMENT: 'vetement', ACCESSOIRE: 'vetement', LIVRE: 'livre', VINYLE: 'vinyle' }

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

// Article modifié : sa photo, ses droits et les valeurs absentes du formulaire (ISBN, état…)
const articleModifie = ref(null)
const titreEcran = { vetement: "Vérifiez l'article", manuel: 'Nouvel article', modification: "Modifier l'article" }[origine]
const retour = idArticle ? { name: 'article', params: { id: idArticle } } : { name: 'scan' }
const peutChangerConfidentialite = computed(() => !idArticle || articleModifie.value?.peutChangerConfidentialite)

const logementId = ref(null)
const stockages = ref([])
const etiquettes = ref([])
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
const etiquettesChoisies = ref([])
const estPersonnel = ref(false)

// Valeurs proposées par l'IA : le champ garde son marqueur tant qu'on ne le modifie pas
const valeursIa = reactive({})

function estRempliParIa(cle) {
  return valeursIa[cle] !== undefined && valeursIa[cle] !== null && valeurs[cle] === valeursIa[cle]
}

const nbChampsIa = computed(() => Object.keys(valeursIa).filter(estRempliParIa).length)
// L'analyse se poursuit après l'ouverture de la fiche : envoi de la photo, puis réponse de l'IA
const analyseEnCours = computed(() => scan && scan.envoi !== 'erreur' && scan.analyse !== 'termine' && scan.analyse !== 'erreur')

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
const champsDetail = computed(() => CHAMPS_PAR_FAMILLE[famille.value] ?? [])
const groupesCategories = computed(() =>
  referentiels.categoriesParRacine(origine === 'vetement' ? ['VETEMENT', 'ACCESSOIRE'] : null),
)

function basculerEtiquette(id) {
  const index = etiquettesChoisies.value.indexOf(id)
  if (index === -1) {
    etiquettesChoisies.value.push(id)
  } else {
    etiquettesChoisies.value.splice(index, 1)
  }
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
    etat: origineFormulaire.etat ?? null,
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
    erreur.value = Object.keys(e.violations).length ? 'Vérifiez les champs signalés.' : e.message
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
    etiquettes.value = rangement.etiquettes

    // Code-barres inconnu : on devine la famille d'après le code (ISBN = livre, sinon disque)
    if (codeInconnu && valeurs.categorieId === null) {
      const estIsbn = /^(97[89]\d{10}|\d{9}[\dX])$/.test(codeInconnu)
      const chemin = estIsbn ? 'Livres › Romans › Romans' : 'Musique › Vinyles › Albums (33 tours)'
      valeurs.categorieId = referentiels.categories.find((c) => c.chemin === chemin)?.id ?? null
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
    <header class="entete">
      <RouterLink :to="retour" class="bouton-icone" :aria-label="idArticle ? 'Retour à la fiche' : scan ? 'Reprendre la photo' : 'Retour au scan'">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <h1 class="entete__titre">{{ titreEcran }}</h1>
    </header>

    <!-- Affiché tout de suite, sans attendre le chargement du formulaire : photo et avancement de l'analyse -->
    <div v-if="scan" class="resume resume--scan">
      <img :src="scan.apercu ?? scan.photo" alt="Photo du scan" class="resume__photo" />
      <div class="resume__textes" aria-live="polite">
        <template v-if="scan.envoi === 'erreur'">
          <span class="badge-ia badge-ia--neutre">{{ scan.erreur }}</span>
          <RouterLink :to="{ name: 'scan' }" class="resume__lien">Reprendre la photo</RouterLink>
        </template>
        <template v-else-if="analyseEnCours">
          <span class="badge-ia badge-ia--attente">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
            Analyse de la photo par l'IA…
          </span>
          <span class="resume__aide">Vous pouvez déjà remplir la fiche : l'IA complétera les champs restés vides.</span>
        </template>
        <template v-else>
          <span v-if="nbChampsIa" class="badge-ia">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
            {{ nbChampsIa }} champ{{ nbChampsIa > 1 ? 's' : '' }} pré-rempli{{ nbChampsIa > 1 ? 's' : '' }} par l'IA
          </span>
          <span v-else class="badge-ia badge-ia--neutre">{{ scan.ia?.message ?? "L'IA n'a rien reconnu" }}</span>
          <span class="resume__aide">La photo du scan est conservée. Tout reste modifiable.</span>
        </template>
      </div>
    </div>

    <!-- Le formulaire s'affiche tout de suite ; en modification, on attend les valeurs de l'article -->
    <p v-if="chargement && idArticle" class="etat etat--plein">Chargement de l'article…</p>

    <form v-else id="formulaire-article" class="contenu" novalidate @submit.prevent="enregistrer">
      <div v-if="articleModifie?.photos.length" class="resume">
        <img :src="articleModifie.photos[0]" alt="Photo de l'article" class="resume__photo" />
        <span class="resume__aide">Modifiez les informations puis enregistrez. La photo reste la même.</span>
      </div>

      <p v-if="codeInconnu" class="info">
        Le code-barres {{ codeInconnu }} n'est dans aucune base : complétez la fiche, il sera enregistré avec l'article.
      </p>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <div class="champs">
        <label class="carte carte--pleine" :class="{ 'carte--ia': estRempliParIa('titre'), 'carte--invalide': erreurs.titre }">
          <span class="carte__libelle">
            Nom de l'article
            <svg v-if="estRempliParIa('titre')" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="Pré-rempli par l'IA" class="carte__ia"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
          </span>
          <input v-model.trim="valeurs.titre" class="carte__saisie" type="text" placeholder="Ex. Pull col roulé" required />
          <span v-if="erreurs.titre" class="champ__erreur">{{ erreurs.titre }}</span>
        </label>

        <label class="carte carte--pleine" :class="{ 'carte--ia': estRempliParIa('categorieId'), 'carte--invalide': erreurs.categorieId }">
          <span class="carte__libelle">
            Catégorie
            <svg v-if="estRempliParIa('categorieId')" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="Pré-rempli par l'IA" class="carte__ia"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
          </span>
          <select v-model="valeurs.categorieId" class="carte__saisie">
            <option :value="null" disabled>Choisir une catégorie</option>
            <optgroup v-for="groupe in groupesCategories" :key="groupe.racine" :label="groupe.racine">
              <option v-for="categorie in groupe.elements" :key="categorie.id" :value="categorie.id">{{ categorie.libelle }}</option>
            </optgroup>
          </select>
          <span v-if="erreurs.categorieId" class="champ__erreur">{{ erreurs.categorieId }}</span>
        </label>

        <label v-for="champ in champsDetail" :key="champ.cle" class="carte" :class="{ 'carte--ia': estRempliParIa(champ.cle), 'carte--invalide': erreurs[champ.cle] }">
          <span class="carte__libelle">
            {{ champ.libelle }}
            <svg v-if="estRempliParIa(champ.cle)" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="Pré-rempli par l'IA" class="carte__ia"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
          </span>
          <select v-if="champ.type === 'couleur'" v-model="valeurs[champ.cle]" class="carte__saisie">
            <option :value="null">—</option>
            <option v-for="couleur in referentiels.couleurs" :key="couleur.id" :value="couleur.id">{{ couleur.nom }}</option>
          </select>
          <select v-else-if="champ.type === 'format'" v-model="valeurs[champ.cle]" class="carte__saisie">
            <option :value="null">—</option>
            <option v-for="format in referentiels.formatsVinyle" :key="format.valeur" :value="format.valeur">{{ format.libelle }}</option>
          </select>
          <input
            v-else
            v-model.trim="valeurs[champ.cle]"
            class="carte__saisie"
            :type="champ.type === 'nombre' ? 'number' : 'text'"
            :inputmode="champ.type === 'nombre' ? 'numeric' : undefined"
            :placeholder="champ.placeholder"
          />
          <span v-if="erreurs[champ.cle]" class="champ__erreur">{{ erreurs[champ.cle] }}</span>
        </label>
      </div>

      <label class="carte carte--note" :class="{ 'carte--invalide': erreurs.description }">
        <span class="carte__libelle">Note</span>
        <textarea v-model.trim="valeurs.description" class="carte__saisie carte__saisie--note" rows="3" placeholder="Ex. Taille un peu petit, offert par Camille…"></textarea>
        <span v-if="erreurs.description" class="champ__erreur">{{ erreurs.description }}</span>
      </label>

      <ChoixEmplacement v-model="stockageId" :stockages="stockages" :erreur="erreurs.stockageId" />

      <div v-if="etiquettes.length" class="etiquettes">
        <span class="etiquettes__titre">Étiquettes</span>
        <div class="etiquettes__liste">
          <button
            v-for="etiquette in etiquettes"
            :key="etiquette.id"
            type="button"
            class="etiquette"
            :class="{ 'etiquette--active': etiquettesChoisies.includes(etiquette.id) }"
            :aria-pressed="etiquettesChoisies.includes(etiquette.id)"
            @click="basculerEtiquette(etiquette.id)"
          >
            {{ etiquette.nom }}
          </button>
        </div>
      </div>

      <label v-if="peutChangerConfidentialite" class="personnel">
        <input v-model="estPersonnel" type="checkbox" class="personnel__case" />
        Article personnel — visible uniquement par moi
      </label>
    </form>

    <div class="pied">
      <!-- On attend seulement que la photo soit enregistrée, pas la réponse de l'IA -->
      <button
        type="submit"
        form="formulaire-article"
        class="bouton-principal bouton-principal--icone"
        :disabled="envoiEnCours || chargement || (scan && scan.envoi !== 'termine')"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
        {{
          envoiEnCours
            ? 'Enregistrement…'
            : scan?.envoi === 'en-cours'
              ? 'Envoi de la photo…'
              : idArticle
                ? 'Enregistrer les modifications'
                : 'Enregistrer dans le dressing'
        }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.entete {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px 0;
}

.entete__titre {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.contenu {
  flex: 1;
  padding: 16px 20px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.resume {
  display: flex;
  gap: 14px;
  align-items: center;
}

.resume__photo {
  width: 92px;
  height: 108px;
  flex-shrink: 0;
  border-radius: 16px;
  object-fit: cover;
  background: var(--couleur-vignette-grege);
}

.resume__textes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.badge-ia {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 12px;
  font-weight: 800;
}

.badge-ia--neutre {
  background: var(--couleur-info-fond);
  color: var(--couleur-texte-secondaire);
  font-weight: 700;
  line-height: 1.4;
}

/* Analyse en cours : l'étoile pulse */
.badge-ia--attente svg {
  animation: pulsation 1.2s ease-in-out infinite;
}

@keyframes pulsation {
  50% {
    opacity: 0.3;
    transform: scale(0.75);
  }
}

@media (prefers-reduced-motion: reduce) {
  .badge-ia--attente svg {
    animation: none;
  }
}

/* Pendant le chargement, occupe la place du formulaire pour garder le bouton en bas de l'écran */
.etat--plein {
  flex: 1;
}

/* Hors du formulaire : reprend ses marges */
.resume--scan {
  padding: 16px 20px 0;
}

.resume__lien {
  font-size: 13px;
  font-weight: 700;
}

.resume__aide {
  font-size: 13px;
  line-height: 1.45;
  color: var(--couleur-texte-secondaire);
}

.info {
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.45;
}

.champs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.carte {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 10px 12px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1.5px solid var(--couleur-bordure);
}

.carte--pleine {
  grid-column: 1 / -1;
}

.carte--ia {
  border-color: var(--couleur-bordure-ia);
}

.carte--invalide {
  border-color: var(--couleur-erreur);
}

.carte:focus-within {
  border-color: var(--couleur-primaire);
}

.carte__libelle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.carte__ia {
  color: var(--couleur-primaire);
}

.carte--note {
  margin: 0;
}

.carte__saisie--note {
  resize: vertical;
  font-weight: 600;
  line-height: 1.45;
}

.carte__saisie {
  width: 100%;
  min-width: 0;
  padding: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.etiquettes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.etiquettes__titre {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.etiquettes__liste {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.etiquette {
  height: 36px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.etiquette--active {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.personnel {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.personnel__case {
  width: 20px;
  height: 20px;
  margin: 0;
  accent-color: var(--couleur-primaire);
}

.pied {
  position: sticky;
  bottom: 0;
  padding: 12px 20px 22px;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.bouton-principal--icone {
  width: 100%;
  gap: 8px;
}
</style>
