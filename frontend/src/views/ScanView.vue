<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { lireCodeBarres } from '@/services/codeBarres'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { reduireEnJpeg } from '@/utils/image'
import { idLogementActif } from '@/utils/logementActif'

const INTERVALLE_LECTURE_MS = 300

const { t } = useI18n()
const router = useRouter()
const ajout = useAjoutArticleStore()

const modes = ['vetement', 'code-barres']
const mode = ref('vetement')

const video = ref(null)
const champFichier = ref(null)
const cameraDisponible = ref(false)
const messageCamera = ref(t('scan.activation'))
const flashDisponible = ref(false)
const flashActif = ref(false)
const traitement = ref('')
const erreur = ref('')
const confirmation = ref(ajout.confirmation)
// Nouvelle session de scan : on oublie le résultat précédent (code inconnu, photo…)
ajout.confirmation = ''
ajout.reinitialiser()

let flux = null
let minuterieLecture = null
let lectureEnCours = false

async function demarrerCamera() {
  // getUserMedia n'existe que sur une page sécurisée (https ou localhost)
  if (!navigator.mediaDevices?.getUserMedia) {
    messageCamera.value = t('scan.cameraIndisponible')
    return
  }
  try {
    flux = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } },
      audio: false,
    })
    video.value.srcObject = flux
    await video.value.play()
    cameraDisponible.value = true
    const piste = flux.getVideoTracks()[0]
    flashDisponible.value = Boolean(piste.getCapabilities?.().torch)
    surveillerCodeBarres()
  } catch {
    messageCamera.value = t('scan.cameraRefusee')
  }
}

function arreterCamera() {
  clearInterval(minuterieLecture)
  flux?.getTracks().forEach((piste) => piste.stop())
  flux = null
}

async function basculerFlash() {
  flashActif.value = !flashActif.value
  await flux?.getVideoTracks()[0].applyConstraints({ advanced: [{ torch: flashActif.value }] })
}

// En mode code-barres, l'image de la caméra est lue en continu
function surveillerCodeBarres() {
  clearInterval(minuterieLecture)
  if (mode.value !== 'code-barres' || !cameraDisponible.value) {
    return
  }
  minuterieLecture = setInterval(async () => {
    if (lectureEnCours || traitement.value) {
      return
    }
    lectureEnCours = true
    try {
      const code = await lireCodeBarres(video.value)
      if (code) {
        await rechercherCode(code)
      }
    } finally {
      lectureEnCours = false
    }
  }, INTERVALLE_LECTURE_MS)
}

watch(mode, () => {
  erreur.value = ''
  surveillerCodeBarres()
})

async function rechercherCode(code) {
  clearInterval(minuterieLecture)
  traitement.value = t('scan.codeLu', { code })
  ajout.reinitialiser()
  try {
    ajout.fiche = await api(`/scans/code-barres/${code}`)
    router.push({ name: 'scan-code-barres' })
  } catch (e) {
    if (e.statut === 404) {
      ajout.codeInconnu = code
      router.push({ name: 'article-nouveau', query: { origine: 'manuel' } })
      return
    }
    erreur.value = e.message
    traitement.value = ''
    surveillerCodeBarres()
  }
}

/**
 * Ouvre tout de suite la fiche de l'article : l'envoi de la photo et l'analyse par l'IA
 * se poursuivent pendant que l'utilisateur la remplit (voir le store ajoutArticle).
 */
async function ouvrirFiche(image) {
  erreur.value = ''
  try {
    ajout.lancerScanVetement(await reduireEnJpeg(image))
    router.push({ name: 'article-nouveau', query: { origine: 'vetement' } })
  } catch {
    erreur.value = t('scan.imageIllisible')
  }
}

async function prendrePhoto() {
  if (traitement.value) {
    return
  }
  if (cameraDisponible.value) {
    await ouvrirFiche(video.value)
  } else {
    champFichier.value.click()
  }
}

async function importer(evenement) {
  const fichier = evenement.target.files[0]
  evenement.target.value = ''
  if (!fichier) {
    return
  }
  if (mode.value === 'vetement') {
    await ouvrirFiche(fichier)
    return
  }
  const code = await lireCodeBarres(await createImageBitmap(fichier)).catch(() => null)
  if (code) {
    await rechercherCode(code)
  } else {
    erreur.value = t('scan.codeIntrouvable')
  }
}

onMounted(async () => {
  // Il faut un logement où ranger l'article : sinon, retour au dressing pour en créer un
  if ((await idLogementActif()) === null) {
    router.replace({ name: 'dressing' })
    return
  }
  demarrerCamera()
})

onBeforeUnmount(arreterCamera)
</script>

<template>
  <div class="ecran ecran--scan">
    <main class="page scan">
      <header class="entete">
        <RouterLink to="/dressing" class="bouton-icone bouton-scan" :aria-label="t('scan.fermer')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </RouterLink>
        <h1 class="titre-page scan__titre">{{ t('scan.titre') }}</h1>
        <div class="segments seulement-ordinateur scan__segments" role="tablist" :aria-label="t('scan.typeScan')">
          <button
            v-for="element in modes"
            :key="element"
            type="button"
            role="tab"
            class="segment"
            :class="{ 'segment--actif': element === mode }"
            :aria-selected="element === mode"
            @click="mode = element"
          >
            {{ t(`scan.modes.${element}`) }}
          </button>
        </div>
        <button
          v-if="flashDisponible"
          type="button"
          class="bouton-icone bouton-scan seulement-mobile"
          :class="{ 'bouton-scan--actif': flashActif }"
          :aria-label="t(flashActif ? 'scan.eteindreFlash' : 'scan.activerFlash')"
          :aria-pressed="flashActif"
          @click="basculerFlash"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9z" /></svg>
        </button>
        <span v-else class="entete__vide seulement-mobile"></span>
      </header>

      <div class="puces scan__modes seulement-mobile" role="tablist" :aria-label="t('scan.typeScan')">
        <button
          v-for="element in modes"
          :key="element"
          type="button"
          role="tab"
          class="puce"
          :class="{ 'puce--active': element === mode }"
          :aria-selected="element === mode"
          @click="mode = element"
        >
          {{ t(`scan.modes.${element}`) }}
        </button>
      </div>

      <div class="disposition">
        <!-- Aperçu de la caméra dans le cadre, avec le guide du mode choisi -->
        <div class="camera">
          <video ref="video" class="camera__flux" :class="{ 'camera__flux--visible': cameraDisponible }" playsinline muted></video>
          <div class="cadre">
            <p v-if="!cameraDisponible" class="cadre__message">{{ messageCamera }}</p>
            <template v-if="mode === 'vetement'">
              <span class="coin coin--haut-gauche"></span>
              <span class="coin coin--haut-droite"></span>
              <span class="coin coin--bas-gauche"></span>
              <span class="coin coin--bas-droite"></span>
            </template>
            <div v-else class="guide-code">
              <span class="guide-code__laser"></span>
            </div>
            <span v-if="traitement" class="traitement" role="status">{{ traitement }}</span>
          </div>
        </div>

        <div class="aide">
          <h2 class="titre-section seulement-ordinateur">{{ t('scan.commentScanner') }}</h2>
          <p v-if="confirmation" class="confirmation" role="status">{{ confirmation }}</p>
          <p class="consigne">{{ t(mode === 'vetement' ? 'scan.consigneVetement' : 'scan.consigneCode') }}</p>
          <p v-if="erreur" class="erreur" role="alert">{{ erreur }}</p>

          <!-- Ordinateur : boutons dans la colonne d'aide -->
          <div class="pile seulement-ordinateur">
            <button v-if="mode === 'vetement'" type="button" class="bouton bouton--large" :disabled="Boolean(traitement)" @click="prendrePhoto">{{ t('scan.prendrePhoto') }}</button>
            <button type="button" class="bouton bouton--contour bouton--large" @click="champFichier.click()">
              {{ t(mode === 'vetement' ? 'scan.importer' : 'scan.importerPhotoCode') }}
            </button>
            <RouterLink :to="{ name: 'article-nouveau', query: { origine: 'manuel' } }" class="lien">{{ t('scan.saisieManuelle') }}</RouterLink>
          </div>
        </div>
      </div>

      <!-- Téléphone : galerie, déclencheur et saisie manuelle en bas de l'écran -->
      <div class="commandes seulement-mobile">
        <button type="button" class="lien lien--clair" @click="champFichier.click()">{{ t(mode === 'vetement' ? 'scan.galerie' : 'scan.importerPhoto') }}</button>
        <button
          v-if="mode === 'vetement'"
          type="button"
          class="declencheur"
          :aria-label="t('scan.prendrePhoto')"
          :disabled="Boolean(traitement)"
          @click="prendrePhoto"
        >
          <span class="declencheur__centre"></span>
        </button>
        <RouterLink :to="{ name: 'article-nouveau', query: { origine: 'manuel' } }" class="lien lien--clair commandes__droite">{{ t('scan.saisieManuelle') }}</RouterLink>
      </div>

      <input ref="champFichier" type="file" accept="image/*" class="visuellement-cache" tabindex="-1" @change="importer" />
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
/* Téléphone et tablette : écran sombre façon appareil photo */
.ecran--scan {
  background: var(--couleur-scan-fond);
  color: var(--couleur-sur-primaire);
}

.scan {
  gap: 16px;
}

.scan__titre {
  font-size: 16px;
  text-align: center;
}

.entete__vide {
  width: 44px;
  flex-shrink: 0;
}

.bouton-scan {
  border: 1.5px solid var(--couleur-donnee);
  background: transparent;
  color: var(--couleur-sur-primaire);
}

.bouton-scan--actif {
  background: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
}

.scan__modes {
  justify-content: center;
}

.scan__modes .puce {
  background: transparent;
  border-color: var(--couleur-donnee);
  color: var(--couleur-sur-primaire);
}

.scan__modes .puce--active {
  background: var(--couleur-sur-primaire);
  border-color: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
}

.disposition {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.camera {
  position: relative;
  flex: 1;
  min-height: 360px;
  overflow: hidden;
  border: 2px dashed var(--couleur-sur-primaire);
  border-radius: 18px;
  background: var(--couleur-scan-cadre);
}

.camera__flux {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
}

.camera__flux--visible {
  opacity: 1;
}

.cadre {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cadre__message {
  max-width: 240px;
  margin: 0;
  text-align: center;
  font-size: 13px;
  line-height: 1.5;
  color: var(--couleur-scan-texte);
}

.coin {
  position: absolute;
  width: 40px;
  height: 40px;
  border: 0 solid var(--couleur-sur-primaire);
}

.coin--haut-gauche {
  top: 16px;
  left: 16px;
  border-top-width: 4px;
  border-left-width: 4px;
  border-radius: 14px 0 0 0;
}

.coin--haut-droite {
  top: 16px;
  right: 16px;
  border-top-width: 4px;
  border-right-width: 4px;
  border-radius: 0 14px 0 0;
}

.coin--bas-gauche {
  bottom: 16px;
  left: 16px;
  border-bottom-width: 4px;
  border-left-width: 4px;
  border-radius: 0 0 0 14px;
}

.coin--bas-droite {
  bottom: 16px;
  right: 16px;
  border-bottom-width: 4px;
  border-right-width: 4px;
  border-radius: 0 0 14px 0;
}

.guide-code {
  position: relative;
  width: min(250px, 80%);
  height: 110px;
  border: 2px solid var(--couleur-sur-primaire);
  border-radius: 10px;
}

.guide-code__laser {
  position: absolute;
  left: 8px;
  right: 8px;
  top: 50%;
  height: 2px;
  background: var(--couleur-scan-laser);
}

.traitement {
  position: absolute;
  bottom: 18px;
  left: 50%;
  transform: translateX(-50%);
  padding: 7px 12px;
  border-radius: 999px;
  background: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
}

.aide {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.consigne,
.erreur,
.confirmation {
  margin: 0;
  text-align: center;
  font-size: 13px;
  line-height: 1.45;
}

.consigne {
  opacity: 0.9;
}

.erreur {
  color: var(--couleur-primaire-pale);
  font-weight: 700;
}

.confirmation {
  align-self: center;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--couleur-succes-fond);
  color: var(--couleur-succes);
  font-weight: 700;
}

.commandes {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 8px 6px;
}

.lien--clair {
  width: 96px;
  color: var(--couleur-sur-primaire);
  text-align: left;
}

.lien--clair:hover {
  color: var(--couleur-primaire-pale);
}

.commandes__droite {
  text-align: right;
}

.declencheur {
  width: 72px;
  height: 72px;
  padding: 0;
  border-radius: 50%;
  border: 4px solid var(--couleur-sur-primaire);
  background: none;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.declencheur:disabled {
  opacity: 0.5;
  cursor: wait;
}

.declencheur__centre {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--couleur-sur-primaire);
}

/* Tablette : la colonne sombre couvre toute la fenêtre */
@media (min-width: 600px) and (max-width: 1023px) {
  .ecran--scan {
    max-width: none;
  }

  .scan {
    width: 100%;
    max-width: var(--largeur-colonne);
    margin: 0 auto;
  }
}

/* Ordinateur : page claire avec le menu latéral, caméra (webcam) à gauche et aide à droite */
@media (min-width: 1024px) {
  .ecran--scan {
    background: var(--couleur-fond);
    color: var(--couleur-texte);
  }

  .scan__titre {
    font-size: 22px;
    text-align: left;
  }

  .scan__segments {
    width: 300px;
  }

  .bouton-scan {
    border: 1px solid var(--couleur-bordure);
    background: var(--couleur-surface);
    color: var(--couleur-texte);
  }

  .disposition {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 28px;
    align-items: start;
  }

  .camera {
    flex: none;
    height: 560px;
    border: 0;
    border-radius: 14px;
    background: var(--couleur-scan-cadre);
    color: var(--couleur-sur-primaire);
  }

  .cadre {
    inset: 24px;
    border: 2px dashed var(--couleur-sur-primaire);
    border-radius: 18px;
  }

  .consigne,
  .erreur,
  .confirmation {
    text-align: left;
  }

  .consigne {
    color: var(--couleur-texte-secondaire);
    opacity: 1;
  }

  .erreur {
    color: var(--couleur-erreur);
  }

  .aide .lien {
    align-self: flex-start;
  }
}
</style>
