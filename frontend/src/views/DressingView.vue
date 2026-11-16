<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { logementMemorise, memoriserLogementActif } from '@/utils/logementActif'
import { positionDejaAutorisee } from '@/utils/position'

const FONDS_PIECES = ['rose', 'sable', 'ardoise', 'lavande'].map((teinte) => `var(--couleur-pastel-${teinte})`)

const { t } = useI18n()
const session = useSessionStore()
const router = useRouter()

const logements = ref([])
const logement = ref(null)
const chargement = ref(true)
const erreur = ref('')
const menuOuvert = ref(false)
const meteo = ref(null)

const creationLogement = ref(false)
const nomLogement = ref('')
const erreurLogement = ref('')

const ajoutPiece = ref(false)
const nomPiece = ref('')
const erreurPiece = ref('')

const recherche = ref('')
const resultats = ref([])
const rechercheEnCours = ref(false)
const enRecherche = computed(() => recherche.value.trim().length >= 2)
// Lu via un computed : l'utilisateur devient null pendant la déconnexion, avant la redirection
const estMineur = computed(() => session.utilisateur?.estMineur ?? false)

const libelleRole = computed(() => {
  const nb = logement.value.nbCoproprietaires
  const coproprietaires = nb === 0 ? '' : ` · ${t('dressing.coproprietaires', nb)}`
  return t(`dressing.roles.${logement.value.role}`) + coproprietaires
})

// « Lyon · Pluie · 12 °C » quand la position est déjà autorisée, sinon une invitation générale
const ligneMeteo = computed(() =>
  meteo.value ? [meteo.value.ville, meteo.value.libelle, `${Math.round(meteo.value.temperature)} °C`].filter(Boolean).join(' · ') : t('dressing.selonMeteo'),
)

async function chargerLogement(id) {
  logement.value = await api(`/logements/${id}`)
  memoriserLogementActif(id)
}

async function charger() {
  chargement.value = true
  erreur.value = ''
  try {
    logements.value = await api('/logements')
    if (logements.value.length === 0) {
      logement.value = null
      creationLogement.value = !estMineur.value
      return
    }
    const memorise = logements.value.find((l) => l.id === logementMemorise())
    await chargerLogement((memorise ?? logements.value[0]).id)
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
}

// Sans redemander la localisation : la question n'est posée que par le générateur de tenue
async function chargerMeteo() {
  const position = await positionDejaAutorisee()
  if (position) {
    try {
      meteo.value = (await api(`/meteo?lat=${position.lat}&lon=${position.lon}`)).meteo
    } catch {
      meteo.value = null
    }
  }
}

async function changerLogement(id) {
  menuOuvert.value = false
  recherche.value = ''
  try {
    await chargerLogement(id)
  } catch (e) {
    erreur.value = e.message
  }
}

function ouvrirCreationLogement() {
  menuOuvert.value = false
  nomLogement.value = ''
  erreurLogement.value = ''
  creationLogement.value = true
}

async function creerLogement() {
  erreurLogement.value = ''
  try {
    const nouveau = await api('/logements', { methode: 'POST', corps: { nom: nomLogement.value } })
    logements.value.push(nouveau)
    creationLogement.value = false
    await chargerLogement(nouveau.id)
  } catch (e) {
    erreurLogement.value = e.violations.nom ?? e.violations.general ?? e.message
  }
}

async function creerPiece() {
  erreurPiece.value = ''
  try {
    await api(`/logements/${logement.value.id}/pieces`, { methode: 'POST', corps: { nom: nomPiece.value } })
    nomPiece.value = ''
    ajoutPiece.value = false
    await chargerLogement(logement.value.id)
  } catch (e) {
    erreurPiece.value = e.violations.nom ?? e.message
  }
}

async function seDeconnecter() {
  await session.deconnexion()
  router.push({ name: 'connexion' })
}

// Recherche « Où est rangé… ? » : on attend une courte pause dans la frappe avant d'interroger l'API
let minuterieRecherche
watch(recherche, (texte) => {
  clearTimeout(minuterieRecherche)
  if (texte.trim().length < 2) {
    resultats.value = []
    return
  }
  rechercheEnCours.value = true
  minuterieRecherche = setTimeout(async () => {
    try {
      resultats.value = await api(`/logements/${logement.value.id}/recherche?q=${encodeURIComponent(texte.trim())}`)
    } catch (e) {
      erreur.value = e.message
    } finally {
      rechercheEnCours.value = false
    }
  }, 300)
})

onMounted(() => {
  charger()
  chargerMeteo()
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <p v-if="chargement" class="etat">{{ t('dressing.chargement') }}</p>

      <p v-else-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <section v-else-if="creationLogement" class="nouveau-logement">
        <h1 class="titre-page">{{ t(logements.length ? 'dressing.nouveauLogement' : 'dressing.bienvenue') }}</h1>
        <p class="texte-secondaire">{{ t('dressing.creezLogement') }}</p>
        <form class="pile" @submit.prevent="creerLogement">
          <label class="champ">
            <span class="champ__libelle">{{ t('dressing.nomLogement') }}</span>
            <input v-model="nomLogement" class="saisie" :class="{ 'saisie--invalide': erreurLogement }" type="text" :placeholder="t('dressing.exempleLogement')" required />
            <span v-if="erreurLogement" class="champ__erreur">{{ erreurLogement }}</span>
          </label>
          <button type="submit" class="bouton bouton--large">{{ t('dressing.creerLogement') }}</button>
          <button v-if="logements.length" type="button" class="lien lien--centre" @click="creationLogement = false">{{ t('commun.annuler') }}</button>
        </form>
      </section>

      <section v-else-if="!logement" class="nouveau-logement">
        <h1 class="titre-page">{{ t('dressing.bienvenue') }}</h1>
        <p class="texte-secondaire">{{ t('dressing.mineurSansLogement') }}</p>
        <button type="button" class="lien lien--centre" @click="seDeconnecter">{{ t('dressing.seDeconnecter') }}</button>
      </section>

      <template v-else>
        <header class="entete-dressing">
          <div class="entete-dressing__logement">
            <button type="button" class="logement" :aria-expanded="menuOuvert" @click="menuOuvert = !menuOuvert">
              {{ logement.nom }}
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            <span class="badge role">{{ libelleRole }}</span>

            <div v-if="menuOuvert" class="menu">
              <button
                v-for="element in logements"
                :key="element.id"
                type="button"
                class="menu__element"
                :class="{ 'menu__element--actif': element.id === logement.id }"
                @click="changerLogement(element.id)"
              >
                {{ element.nom }}
              </button>
              <button v-if="!estMineur" type="button" class="menu__element menu__element--action" @click="ouvrirCreationLogement">{{ t('dressing.ajouterLogement') }}</button>
              <button type="button" class="menu__element menu__element--action" @click="seDeconnecter">{{ t('dressing.seDeconnecter') }}</button>
            </div>
          </div>

          <label class="recherche" :class="{ 'recherche--active': enRecherche }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" /></svg>
            <span class="visuellement-cache">{{ t('dressing.rechercher') }}</span>
            <input v-model="recherche" type="search" :placeholder="t('dressing.recherche')" />
          </label>

          <button type="button" class="bouton-icone notifications" :aria-label="t('dressing.notifications')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
          </button>
        </header>

        <!-- Recherche « Où est rangé… ? » : remplace la liste des pièces pendant la saisie -->
        <section v-if="enRecherche" class="pile" aria-live="polite">
          <div class="ligne-titre">
            <h2 class="titre-section">{{ t('dressing.resultats') }}</h2>
            <span v-if="!rechercheEnCours && resultats.length" class="texte-secondaire seulement-ordinateur">{{ t('dressing.articlesTrouves', resultats.length) }}</span>
          </div>
          <p v-if="rechercheEnCours" class="etat">{{ t('dressing.rechercheEnCours') }}</p>
          <p v-else-if="resultats.length === 0" class="etat">{{ t('dressing.aucunResultat', { texte: recherche.trim() }) }}</p>
          <div v-else class="resultats">
            <RouterLink v-for="article in resultats" :key="article.id" :to="`/articles/${article.id}`" class="resultat">
              <VignetteArticle class="resultat__vignette" :photo="article.photo" :couleur="article.couleur" :icone="26" />
              <span class="ligne__textes">
                <span class="ligne__titre">{{ article.titre }}</span>
                <span class="texte-secondaire">
                  {{ article.emplacement ?? t('commun.aRanger') }}<span class="seulement-mobile"> · {{ article.infos }}</span>
                </span>
                <span class="texte-secondaire seulement-ordinateur">{{ article.infos }}</span>
              </span>
              <svg class="ligne__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
          </div>
        </section>

        <template v-else>
          <div class="apercu">
            <div class="grille-3">
              <div class="chiffre">
                <span class="chiffre__valeur">{{ logement.nbArticles }}</span>
                <span class="chiffre__libelle">{{ t('dressing.articles', logement.nbArticles) }}</span>
              </div>
              <div class="chiffre">
                <span class="chiffre__valeur">{{ logement.nbPieces }}</span>
                <span class="chiffre__libelle">{{ t('dressing.pieces', logement.nbPieces) }}</span>
              </div>
              <RouterLink :to="{ name: 'prets' }" class="chiffre">
                <span class="chiffre__valeur chiffre__valeur--accent">{{ logement.nbPretes }}</span>
                <span class="chiffre__libelle">{{ t('dressing.pretes', logement.nbPretes) }}</span>
              </RouterLink>
            </div>

            <RouterLink :to="{ name: 'tenues' }" class="tenue-du-jour">
              <span class="tenue-du-jour__icone">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 19v2M8 13v2M16 19v2M16 13v2M12 21v2M12 15v2" /></svg>
              </span>
              <span class="tenue-du-jour__textes">
                <span class="tenue-du-jour__meteo">{{ ligneMeteo }}</span>
                <span class="tenue-du-jour__titre">{{ t('dressing.genererTenue') }}</span>
              </span>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
          </div>

          <section class="pile">
            <div class="ligne-titre">
              <h2 class="titre-section">{{ t('dressing.titrePieces') }}</h2>
              <button v-if="logement.peutGerer && !ajoutPiece" type="button" class="lien" @click="ajoutPiece = true">{{ t('dressing.ajouterPiece') }}</button>
            </div>

            <form v-if="ajoutPiece" class="ajout" @submit.prevent="creerPiece">
              <div class="ajout__ligne">
                <input v-model="nomPiece" class="saisie" :class="{ 'saisie--invalide': erreurPiece }" type="text" :placeholder="t('dressing.nomPiece')" :aria-label="t('dressing.nomPiece')" required />
                <button type="submit" class="bouton">{{ t('commun.ajouter') }}</button>
              </div>
              <span v-if="erreurPiece" class="champ__erreur">{{ erreurPiece }}</span>
              <button type="button" class="lien lien--centre" @click="ajoutPiece = false">{{ t('commun.annuler') }}</button>
            </form>

            <p v-if="logement.pieces.length === 0" class="etat">{{ t('dressing.aucunePiece') }}</p>
            <div v-else class="pieces">
              <RouterLink v-for="(piece, index) in logement.pieces" :key="piece.id" :to="`/pieces/${piece.id}`" class="piece">
                <span class="piece__visuel" :style="{ background: FONDS_PIECES[index % FONDS_PIECES.length] }">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z" /><path d="m3 8 9 5 9-5M12 13v8" /></svg>
                </span>
                <span class="piece__nom">{{ piece.nom }}</span>
                <span class="piece__stockages">{{ piece.stockages.length ? piece.stockages.join(' · ') : t('dressing.aucunStockage') }}</span>
                <span class="piece__nombre">{{ piece.nbArticles }}</span>
                <svg class="piece__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
              </RouterLink>
            </div>
          </section>
        </template>
      </template>
    </main>

    <BarreNavigation actif="dressing" />
  </div>
</template>

<style scoped>
/* En-tête : logement et cloche, puis la recherche sur téléphone ; tout sur une ligne sur ordinateur */
.entete-dressing {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas:
    'logement cloche'
    'recherche recherche';
  align-items: center;
  gap: 14px 12px;
}

.entete-dressing__logement {
  grid-area: logement;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.notifications {
  grid-area: cloche;
}

.logement {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  background: none;
  font-family: inherit;
  color: var(--couleur-texte);
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.01em;
  text-align: left;
  cursor: pointer;
}

.role {
  align-self: flex-start;
}

.recherche {
  grid-area: recherche;
  display: flex;
  align-items: center;
  gap: 10px;
  height: 46px;
  padding: 0 14px;
  box-sizing: border-box;
  border-radius: 12px;
  background: var(--couleur-surface);
  border: 1.5px solid var(--couleur-bordure);
  color: var(--couleur-texte-secondaire);
}

.recherche input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--couleur-texte);
}

.recherche:focus-within,
.recherche--active {
  border-color: var(--couleur-primaire);
}

@media (min-width: 1024px) {
  .entete-dressing {
    grid-template-columns: auto minmax(0, 1fr) auto;
    grid-template-areas: 'logement recherche cloche';
    gap: 16px;
  }

  .recherche {
    margin-left: 12px;
  }
}

.menu {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 10;
  min-width: 240px;
  display: flex;
  flex-direction: column;
  padding: 6px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  box-shadow: var(--ombre-menu);
}

.menu__element {
  padding: 10px 12px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--couleur-texte);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.menu__element:hover {
  background: var(--couleur-fond);
}

.menu__element--actif {
  color: var(--couleur-primaire);
  font-weight: 800;
}

.menu__element--action {
  color: var(--couleur-texte-secondaire);
  border-top: 1px solid var(--couleur-separateur);
  border-radius: 0;
}

/* Chiffres clés et tenue du jour : l'un sous l'autre, côte à côte sur ordinateur */
.apercu {
  display: grid;
  gap: 14px;
}

@media (min-width: 1024px) {
  .apercu {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
  }
}

a.chiffre:hover {
  border-color: var(--couleur-primaire);
  color: var(--couleur-texte);
}

.chiffre__valeur--accent {
  color: var(--couleur-primaire);
}

.tenue-du-jour {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour:hover {
  background: var(--couleur-primaire-fonce);
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour__icone {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tenue-du-jour__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tenue-du-jour__meteo {
  font-size: 12px;
  font-weight: 600;
  opacity: 0.9;
}

.tenue-du-jour__titre {
  font-size: 15px;
  font-weight: 700;
}

/* Pièces : une liste sur téléphone, une grille de cartes sur ordinateur */
.pieces {
  display: flex;
  flex-direction: column;
  padding: 0 12px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
}

.piece {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto auto;
  grid-template-areas:
    'visuel nom nombre chevron'
    'visuel stockages nombre chevron';
  align-items: center;
  column-gap: 12px;
  row-gap: 2px;
  padding: 12px 0;
  border-bottom: 1px solid var(--couleur-separateur);
  color: var(--couleur-texte);
}

.piece:last-child {
  border-bottom: 0;
}

.piece:hover {
  color: var(--couleur-texte);
}

.piece__visuel {
  grid-area: visuel;
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-primaire);
}

.piece__nom {
  grid-area: nom;
  font-size: 15px;
  font-weight: 700;
}

.piece__stockages {
  grid-area: stockages;
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
}

.piece__nombre {
  grid-area: nombre;
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.piece__chevron {
  grid-area: chevron;
  color: var(--couleur-chevron);
}

@media (min-width: 1024px) {
  .pieces {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    padding: 0;
    border: 0;
    background: none;
  }

  .piece {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
      'visuel visuel'
      'nom nombre'
      'stockages stockages';
    row-gap: 6px;
    padding: 12px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 14px;
    background: var(--couleur-surface);
  }

  .piece:last-child {
    border-bottom: 1.5px solid var(--couleur-bordure);
  }

  .piece:hover {
    border-color: var(--couleur-primaire);
  }

  .piece__visuel {
    width: 100%;
    height: 110px;
    margin-bottom: 4px;
  }

  .piece__visuel svg {
    width: 32px;
    height: 32px;
  }

  .piece__chevron {
    display: none;
  }
}

/* Résultats de recherche : liste sur téléphone, deux colonnes de cartes sur ordinateur */
.resultats {
  display: flex;
  flex-direction: column;
  padding: 0 12px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
}

.resultat {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--couleur-separateur);
  color: var(--couleur-texte);
}

.resultat:last-child {
  border-bottom: 0;
}

.resultat:hover {
  color: var(--couleur-texte);
}

.resultat__vignette {
  width: 52px;
  height: 52px;
}

@media (min-width: 1024px) {
  .resultats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    padding: 0;
    border: 0;
    background: none;
  }

  .resultat,
  .resultat:last-child {
    padding: 12px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 14px;
    background: var(--couleur-surface);
  }

  .resultat:hover {
    border-color: var(--couleur-primaire);
  }

  .resultat__vignette {
    width: 72px;
    height: 72px;
  }
}

.nouveau-logement {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 560px;
  padding-top: 24px;
}

.lien--centre {
  align-self: center;
  color: var(--couleur-texte-secondaire);
  text-decoration: none;
}

.ajout {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ajout__ligne {
  display: flex;
  gap: 8px;
}

.ajout__ligne .saisie {
  flex: 1;
  min-width: 0;
}
</style>
