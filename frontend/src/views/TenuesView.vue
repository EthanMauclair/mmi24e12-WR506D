<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import CarteTenue from '@/components/CarteTenue.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { idLogementActif } from '@/utils/logementActif'
import { lirePosition } from '@/utils/position'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const onglet = ref(route.query.onglet === 'fil' ? 'fil' : 'generer')
// null tant que le fil n'a pas été chargé
const fil = ref(null)
const suiteFil = ref(false)
const chargementFil = ref(false)
const erreurFil = ref('')
// Le générateur ne se lance qu'à la première ouverture de son onglet (il demande la position)
let generateurDemarre = false
const logementId = ref(null)
const etiquettes = ref([])
const peutCreerEtiquette = ref(false)
const etiquetteId = ref(null)

// 'recherche' (en attente de la position), 'indisponible' (refusée ou sans réponse), 'ok'
const etatMeteo = ref('recherche')
const position = ref(null)
const meteo = ref(null)

const tenue = ref([])
const chargement = ref(true)
const erreur = ref('')
const pasDAutreIdee = ref(false)

const creationEtiquette = ref(false)
const nomEtiquette = ref('')
const erreurEtiquette = ref('')

const partage = ref(false)
const titre = ref('')
const description = ref('')
const estPrivee = ref(false)
const envoi = ref(false)
const erreurPartage = ref('')

const articlesProposes = computed(() => tenue.value.filter((ligne) => ligne.article).map((ligne) => ligne.article))
const etiquetteChoisie = computed(() => etiquettes.value.find((etiquette) => etiquette.id === etiquetteId.value))
const iconeMeteo = computed(() => meteo.value?.icone ?? 'nuage')

/**
 * @param {boolean} autreIdee évite les vêtements de la proposition affichée
 */
async function generer(autreIdee = false) {
  const precedents = articlesProposes.value.map((article) => article.id)
  const parametres = new URLSearchParams()
  if (etiquetteId.value) {
    parametres.set('etiquette', etiquetteId.value)
  }
  if (position.value) {
    parametres.set('lat', position.value.lat.toFixed(4))
    parametres.set('lon', position.value.lon.toFixed(4))
  }
  if (autreIdee && precedents.length) {
    parametres.set('eviter', precedents.join(','))
  }

  chargement.value = true
  erreur.value = ''
  try {
    const reponse = await api(`/logements/${logementId.value}/tenue-proposee?${parametres}`)
    meteo.value = reponse.meteo
    etatMeteo.value = reponse.meteo ? 'ok' : 'indisponible'
    tenue.value = reponse.tenue
    // Avec peu de vêtements, la nouvelle proposition peut être identique
    const nouveaux = articlesProposes.value.map((article) => article.id)
    pasDAutreIdee.value = autreIdee && nouveaux.length > 0 && nouveaux.every((id) => precedents.includes(id))
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
}

async function localiser() {
  etatMeteo.value = 'recherche'
  // Sans réponse à la demande de localisation, on génère quand même au bout de quelques secondes
  position.value = await Promise.race([lirePosition(), new Promise((resoudre) => setTimeout(() => resoudre(null), 8000))])
  await generer()
}

/**
 * @param {number|null} id étiquette choisie, ou null pour « Toutes »
 */
function choisirEtiquette(id) {
  etiquetteId.value = id === null || etiquetteId.value === id ? null : id
  generer()
}

async function creerEtiquette() {
  erreurEtiquette.value = ''
  try {
    const etiquette = await api(`/logements/${logementId.value}/etiquettes`, { methode: 'POST', corps: { nom: nomEtiquette.value } })
    etiquettes.value.push(etiquette)
    creationEtiquette.value = false
    nomEtiquette.value = ''
    choisirEtiquette(etiquette.id)
  } catch (e) {
    erreurEtiquette.value = e.violations.nom ?? e.message
  }
}

function ouvrirPartage() {
  titre.value = etiquetteChoisie.value ? t('tenues.titreOccasion', { occasion: etiquetteChoisie.value.nom }) : t('tenues.titreDuJour')
  erreurPartage.value = ''
  partage.value = true
}

async function publier() {
  erreurPartage.value = ''
  envoi.value = true
  try {
    const tenue = await api('/tenues', {
      methode: 'POST',
      corps: { titre: titre.value, description: description.value, estPrivee: estPrivee.value, articleIds: articlesProposes.value.map((article) => article.id) },
    })
    partage.value = false
    description.value = ''
    // Une tenue partagée s'affiche en tête du fil ; une tenue privée, sur le profil
    if (tenue.estPrivee) {
      router.push({ name: 'profil', query: { onglet: 'tenues' } })
    } else {
      fil.value = fil.value === null ? null : [tenue, ...fil.value]
      ouvrirOnglet('fil')
    }
  } catch (e) {
    erreurPartage.value = Object.values(e.violations)[0] ?? e.message
  } finally {
    envoi.value = false
  }
}

/**
 * @param {boolean} suite charge les tenues plus anciennes que la dernière affichée
 */
async function chargerFil(suite = false) {
  chargementFil.value = true
  erreurFil.value = ''
  try {
    const avant = suite ? `?avant=${fil.value.at(-1).id}` : ''
    const page = await api(`/tenues/fil${avant}`)
    fil.value = suite ? [...fil.value, ...page.tenues] : page.tenues
    suiteFil.value = page.suite
  } catch (e) {
    erreurFil.value = e.message
    fil.value ??= []
  } finally {
    chargementFil.value = false
  }
}

function retirerDuFil(tenue) {
  fil.value = fil.value.filter((autre) => autre.id !== tenue.id)
}

function ouvrirOnglet(nom) {
  onglet.value = nom
  router.replace({ query: nom === 'fil' ? { onglet: 'fil' } : {} })
  if (nom === 'fil' && fil.value === null) {
    chargerFil()
  }
  if (nom === 'generer' && !generateurDemarre) {
    demarrerGenerateur()
  }
}

async function demarrerGenerateur() {
  generateurDemarre = true
  try {
    logementId.value = await idLogementActif()
    if (logementId.value === null) {
      chargement.value = false
      return
    }
    const rangement = await api(`/logements/${logementId.value}/rangement`)
    etiquettes.value = rangement.etiquettes
    peutCreerEtiquette.value = rangement.peutCreerEtiquette
  } catch (e) {
    erreur.value = e.message
    chargement.value = false
    return
  }
  await localiser()
}

onMounted(() => ouvrirOnglet(onglet.value))
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete entete--onglets">
        <h1 class="titre-page">{{ t('navigation.tenues') }}</h1>
        <div class="segments onglets" role="tablist" :aria-label="t('navigation.tenues')">
          <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'generer' }" :aria-selected="onglet === 'generer'" @click="ouvrirOnglet('generer')">{{ t('tenues.generer') }}</button>
          <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'fil' }" :aria-selected="onglet === 'fil'" @click="ouvrirOnglet('fil')">{{ t('tenues.fil') }}</button>
        </div>
      </header>

      <!-- Fil des tenues : une carte sous l'autre, deux colonnes sur ordinateur -->
      <template v-if="onglet === 'fil'">
        <p v-if="erreurFil" class="message-erreur" role="alert">{{ erreurFil }}</p>
        <p v-if="fil === null" class="etat">{{ t('tenues.chargementFil') }}</p>
        <div v-else-if="fil.length === 0" class="pile">
          <p class="etat">{{ t('tenues.filVide') }}</p>
          <RouterLink :to="{ name: 'membres' }" class="bouton bouton--large">{{ t('profil.rechercherMembres') }}</RouterLink>
        </div>
        <template v-else>
          <div class="fil-cartes">
            <CarteTenue v-for="tenueDuFil in fil" :key="tenueDuFil.id" :tenue="tenueDuFil" @supprimee="retirerDuFil" />
          </div>
          <button v-if="suiteFil" type="button" class="bouton bouton--contour voir-plus" :disabled="chargementFil" @click="chargerFil(true)">{{ t('tenues.voirPlus') }}</button>
        </template>
      </template>

      <template v-else-if="logementId === null && !chargement">
        <p class="etat">{{ t('tenues.sansLogement') }}</p>
        <RouterLink :to="{ name: 'dressing' }" class="bouton bouton--large">{{ t('tenues.allerDressing') }}</RouterLink>
      </template>

      <!-- Générateur : météo et occasion à gauche, tenue composée à droite (sur ordinateur) -->
      <div v-else class="colonne-gauche generateur">
        <div class="reglages">
          <div class="carte carte--rangee meteo">
            <span class="meteo__icone">
              <svg v-if="etatMeteo === 'ok' && iconeMeteo === 'pluie'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 19v2M8 13v2M16 19v2M16 13v2M12 21v2M12 15v2" /></svg>
              <svg v-else-if="etatMeteo === 'ok' && iconeMeteo === 'soleil'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
              <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" /></svg>
            </span>
            <span class="entete__textes">
              <template v-if="etatMeteo === 'ok'">
                <b class="meteo__titre">{{ meteo.ville ?? t('tenues.votrePosition') }} · {{ meteo.libelle }} · {{ meteo.temperature }} °C</b>
                <span class="texte-secondaire">{{ meteo.conseil }}</span>
              </template>
              <template v-else-if="etatMeteo === 'recherche'">
                <b class="meteo__titre">{{ t('tenues.meteoEnCours') }}</b>
                <span class="texte-secondaire">{{ t('tenues.autoriserLocalisation') }}</span>
              </template>
              <template v-else>
                <b class="meteo__titre">{{ t('tenues.meteoIndisponible') }}</b>
                <span class="texte-secondaire">{{ t('tenues.selonSaison') }}</span>
              </template>
            </span>
            <span v-if="etatMeteo === 'ok'" class="badge">{{ t('tenues.enDirect') }}</span>
            <button v-else-if="etatMeteo === 'indisponible'" type="button" class="badge" @click="localiser">{{ t('tenues.reessayer') }}</button>
          </div>

          <div class="pile occasions">
            <span class="champ__libelle">{{ t('tenues.occasion') }}</span>
            <div class="puces puces--retour">
              <button type="button" class="puce" :class="{ 'puce--active': etiquetteId === null }" :aria-pressed="etiquetteId === null" @click="choisirEtiquette(null)">{{ t('tenues.toutes') }}</button>
              <button
                v-for="etiquette in etiquettes"
                :key="etiquette.id"
                type="button"
                class="puce"
                :class="{ 'puce--active': etiquette.id === etiquetteId }"
                :aria-pressed="etiquette.id === etiquetteId"
                @click="choisirEtiquette(etiquette.id)"
              >
                {{ etiquette.nom }}
              </button>
              <button v-if="peutCreerEtiquette && !creationEtiquette" type="button" class="puce" :aria-label="t('tenues.creerEtiquette')" @click="creationEtiquette = true">
                +<span class="seulement-ordinateur creer">{{ t('commun.creer') }}</span>
              </button>
            </div>
            <form v-if="creationEtiquette" class="nouvelle-etiquette" @submit.prevent="creerEtiquette">
              <input v-model.trim="nomEtiquette" class="saisie nouvelle-etiquette__nom" :class="{ 'saisie--invalide': erreurEtiquette }" type="text" maxlength="30" :placeholder="t('tenues.exempleEtiquette')" :aria-label="t('tenues.nomEtiquette')" autofocus />
              <button type="button" class="bouton bouton--contour bouton--petit" @click="creationEtiquette = false">{{ t('commun.annuler') }}</button>
              <button type="submit" class="bouton bouton--petit" :disabled="!nomEtiquette">{{ t('commun.creer') }}</button>
            </form>
            <span v-if="erreurEtiquette" class="champ__erreur">{{ erreurEtiquette }}</span>
          </div>

          <form v-if="partage" class="carte carte--grise partage" @submit.prevent="publier">
            <label class="champ">
              <span class="champ__libelle">{{ t('scanCode.titre') }}</span>
              <input v-model.trim="titre" class="saisie" type="text" maxlength="100" required />
            </label>
            <label class="champ">
              <span class="champ__libelle">{{ t('tenues.legende') }}</span>
              <textarea v-model="description" class="saisie" rows="2" maxlength="500" :placeholder="t('tenues.exempleLegende')"></textarea>
            </label>
            <label class="partage__privee">
              <input v-model="estPrivee" type="checkbox" />
              {{ t('tenues.privee') }}
            </label>
            <p v-if="erreurPartage" class="message-erreur" role="alert">{{ erreurPartage }}</p>
            <div class="boutons">
              <button type="button" class="bouton bouton--contour bouton--flexible" @click="partage = false">{{ t('commun.annuler') }}</button>
              <button type="submit" class="bouton bouton--flexible" :disabled="envoi || !titre">{{ t('commun.publier') }}</button>
            </div>
          </form>

          <!-- Autre idée et Partager : sous la tenue sur téléphone, dans la colonne de gauche sur ordinateur -->
          <div v-else class="actions">
            <button type="button" class="bouton bouton--contour" :disabled="chargement" @click="generer(true)">{{ t('tenues.autreIdee') }}</button>
            <button type="button" class="bouton" :disabled="chargement || articlesProposes.length === 0" @click="ouvrirPartage">{{ t('tenues.partager') }}</button>
          </div>
        </div>

        <div class="composition">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
          <p v-else-if="chargement && tenue.length === 0" class="etat">{{ t('tenues.composition') }}</p>
          <p v-else-if="tenue.length && articlesProposes.length === 0" class="etat">{{ t('tenues.aucunVetement') }}</p>
          <div v-else class="tenue" :class="{ 'tenue--chargement': chargement }" aria-live="polite">
            <template v-for="ligne in tenue" :key="ligne.zone">
              <RouterLink v-if="ligne.article" :to="{ name: 'article', params: { id: ligne.article.id } }" class="piece">
                <VignetteArticle class="piece__vignette" :photo="ligne.article.photo" :couleur="ligne.article.couleur">
                  <span v-if="ligne.pluie" class="badge badge--blanc piece__pluie">{{ t('tenues.pluie') }}</span>
                </VignetteArticle>
                <span class="texte-secondaire piece__legende"><b>{{ t(`tenues.zones.${ligne.zone}`) }}</b> · {{ ligne.article.titre }}</span>
              </RouterLink>
              <div v-else class="piece">
                <span class="piece__vignette piece__vignette--vide">{{ t('tenues.manque') }}</span>
                <span class="texte-secondaire piece__legende"><b>{{ t(`tenues.zones.${ligne.zone}`) }}</b></span>
              </div>
            </template>
          </div>
          <p v-if="pasDAutreIdee" class="bandeau-info">{{ t('tenues.pasDAutreIdee') }}</p>
        </div>
      </div>
    </main>

    <BarreNavigation actif="tenues" />
  </div>
</template>

<style scoped>
.entete--onglets {
  flex-wrap: wrap;
}

.onglets {
  flex-basis: 100%;
}

.fil-cartes {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.voir-plus {
  width: 100%;
}

.generateur {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.reglages {
  display: contents;
}

.meteo {
  padding: 10px;
}

.meteo__icone {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 10px;
  background: var(--couleur-meteo-fond);
  color: var(--couleur-meteo);
  display: flex;
  align-items: center;
  justify-content: center;
}

.meteo__titre {
  font-size: 14px;
}

.occasions {
  gap: 8px;
}

.creer {
  margin-left: 4px;
}

.nouvelle-etiquette {
  display: flex;
  align-items: center;
  gap: 6px;
}

.nouvelle-etiquette__nom {
  flex: 1;
  min-width: 0;
  height: 36px;
}

.partage {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.partage__privee {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
}

/* Les boutons passent après la tenue sur téléphone */
.actions {
  order: 3;
  display: flex;
  gap: 10px;
}

.actions .bouton {
  flex: 1;
}

.composition {
  order: 2;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tenue {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  transition: opacity 0.2s;
}

.tenue--chargement {
  opacity: 0.5;
}

.piece {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--couleur-texte);
}

.piece:hover {
  color: var(--couleur-texte);
}

.piece__vignette {
  width: 100%;
  height: 118px;
}

.piece__vignette--vide {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  border: 1.5px dashed var(--couleur-bordure-pointillee);
  border-radius: 10px;
  padding: 8px;
  font-size: 12px;
  text-align: center;
  color: var(--couleur-texte-secondaire);
}

.piece__pluie {
  position: absolute;
  top: 6px;
  left: 6px;
}

.piece__legende {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.piece__legende b {
  color: var(--couleur-texte);
}

@media (min-width: 1024px) {
  .entete--onglets {
    flex-wrap: nowrap;
  }

  .onglets {
    flex-basis: 320px;
    flex-shrink: 0;
  }

  .fil-cartes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
  }

  .voir-plus {
    width: auto;
    align-self: center;
  }

  .generateur {
    display: grid;
    --largeur-colonne-fixe: 300px;
    gap: 32px;
  }

  .reglages {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .actions {
    flex-direction: column;
  }

  .actions .bouton {
    flex: none;
  }

  .tenue {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }

  .piece__vignette {
    height: auto;
    aspect-ratio: 3 / 4;
  }
}
</style>
