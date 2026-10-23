<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '@/services/api'
import { lireCodeBarres } from '@/services/codeBarres'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { reduireEnJpeg } from '@/utils/image'
import { idLogementActif } from '@/utils/logementActif'

const INTERVALLE_LECTURE_MS = 300

const router = useRouter()
const ajout = useAjoutArticleStore()

const modes = [
  { id: 'vetement', libelle: 'Vêtement · IA' },
  { id: 'code-barres', libelle: 'Code-barres' },
]
const mode = ref('vetement')

const video = ref(null)
const champFichier = ref(null)
const cameraDisponible = ref(false)
const messageCamera = ref('Activation de la caméra…')
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
    messageCamera.value = 'Caméra indisponible sur cette page : importez une photo.'
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
    messageCamera.value = "Accès à la caméra refusé ou impossible : importez une photo, ou autorisez la caméra dans le navigateur."
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
  traitement.value = `Code ${code} lu, recherche…`
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

async function analyserVetement(image) {
  traitement.value = 'Analyse en cours, environ une minute…'
  erreur.value = ''
  ajout.reinitialiser()
  try {
    const jpeg = await reduireEnJpeg(image)
    const formulaire = new FormData()
    formulaire.append('photo', jpeg, 'scan.jpg')
    const resultat = await api('/scans/vetement', { methode: 'POST', formulaire })
    ajout.vetement = { ...resultat, apercu: URL.createObjectURL(jpeg) }
    router.push({ name: 'article-nouveau', query: { origine: 'vetement' } })
  } catch (e) {
    erreur.value = e.violations?.photo ?? e.message
    traitement.value = ''
  }
}

async function declencher() {
  if (traitement.value) {
    return
  }
  if (mode.value === 'vetement') {
    if (cameraDisponible.value) {
      await analyserVetement(video.value)
    } else {
      champFichier.value.click()
    }
    return
  }
  // Code-barres : lecture immédiate de l'image courante
  const code = cameraDisponible.value ? await lireCodeBarres(video.value) : null
  if (code) {
    await rechercherCode(code)
  } else if (cameraDisponible.value) {
    erreur.value = 'Aucun code-barres lisible : rapprochez-vous et évitez les reflets.'
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
    await analyserVetement(fichier)
    return
  }
  const code = await lireCodeBarres(await createImageBitmap(fichier)).catch(() => null)
  if (code) {
    await rechercherCode(code)
  } else {
    erreur.value = 'Aucun code-barres trouvé sur cette image.'
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
    <header class="entete">
      <RouterLink to="/dressing" class="bouton-voile" aria-label="Fermer le scan">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
      </RouterLink>
      <span class="entete__titre">Ajouter un article</span>
      <button
        v-if="flashDisponible"
        type="button"
        class="bouton-voile"
        :class="{ 'bouton-voile--actif': flashActif }"
        :aria-label="flashActif ? 'Éteindre le flash' : 'Activer le flash'"
        :aria-pressed="flashActif"
        @click="basculerFlash"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9z" /></svg>
      </button>
      <span v-else class="entete__vide"></span>
    </header>

    <div class="visee">
      <p v-if="confirmation" class="confirmation" role="status">{{ confirmation }}</p>

      <div class="cadre">
        <video ref="video" class="cadre__video" :class="{ 'cadre__video--visible': cameraDisponible }" playsinline muted></video>
        <p v-if="!cameraDisponible" class="cadre__message">{{ messageCamera }}</p>

        <template v-if="mode === 'vetement'">
          <span class="coin coin--haut-gauche"></span>
          <span class="coin coin--haut-droite"></span>
          <span class="coin coin--bas-gauche"></span>
          <span class="coin coin--bas-droite"></span>
          <span v-if="traitement" class="badge-ia">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" /></svg>
            {{ traitement }}
          </span>
        </template>

        <div v-else class="guide-code">
          <template v-if="!cameraDisponible">
            <span v-for="(largeur, index) in [4, 2, 6, 2, 3, 6, 2, 4, 2, 6, 3, 2]" :key="index" class="guide-code__barre" :style="{ width: `${largeur}px` }"></span>
          </template>
          <span class="guide-code__laser"></span>
          <span v-if="traitement" class="badge-ia badge-ia--code">{{ traitement }}</span>
        </div>
      </div>

      <p v-if="erreur" class="erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="mode === 'vetement'" class="consigne">
        Posez le vêtement à plat, bien éclairé. L'IA reconnaît la catégorie, la couleur et la marque si l'étiquette est visible.
      </p>
      <p v-else class="consigne">
        Visez le code-barres d'un livre, d'un vinyle ou d'un comics. Les informations se remplissent toutes seules.
      </p>
    </div>

    <div class="commandes">
      <div class="modes" role="tablist" aria-label="Type de scan">
        <button
          v-for="element in modes"
          :key="element.id"
          type="button"
          role="tab"
          class="modes__bouton"
          :class="{ 'modes__bouton--actif': element.id === mode }"
          :aria-selected="element.id === mode"
          @click="mode = element.id"
        >
          {{ element.libelle }}
        </button>
      </div>

      <div class="actions">
        <button type="button" class="bouton-voile bouton-voile--grand" aria-label="Importer depuis la galerie" @click="champFichier.click()">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
        </button>
        <button
          type="button"
          class="declencheur"
          :aria-label="mode === 'vetement' ? 'Prendre la photo' : 'Lire le code-barres'"
          :disabled="Boolean(traitement)"
          @click="declencher"
        >
          <span class="declencheur__centre"></span>
        </button>
        <RouterLink :to="{ name: 'article-nouveau', query: { origine: 'manuel' } }" class="bouton-voile bouton-voile--grand" aria-label="Saisir manuellement">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
        </RouterLink>
      </div>
    </div>

    <input ref="champFichier" type="file" accept="image/*" class="visuellement-cache" tabindex="-1" @change="importer" />
  </div>
</template>

<style scoped>
.ecran--scan {
  background: var(--couleur-scan-fond);
  color: var(--couleur-sur-primaire);
}

.entete {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
}

.entete__titre {
  font-size: 16px;
  font-weight: 700;
}

.entete__vide {
  width: 44px;
}

.bouton-voile {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border: 0;
  border-radius: 14px;
  background: var(--couleur-scan-voile);
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.bouton-voile:hover {
  color: var(--couleur-primaire-pale);
}

.bouton-voile--actif {
  background: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
}

.bouton-voile--grand {
  width: 48px;
  height: 48px;
}

.visee {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 22px;
  padding: 0 20px;
}

.cadre {
  position: relative;
  width: 300px;
  height: 360px;
  overflow: hidden;
  border-radius: 28px;
  background: var(--couleur-scan-cadre);
  display: flex;
  align-items: center;
  justify-content: center;
}

.cadre__video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
}

.cadre__video--visible {
  opacity: 1;
}

.cadre__message {
  max-width: 220px;
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
  top: 14px;
  left: 14px;
  border-top-width: 4px;
  border-left-width: 4px;
  border-radius: 14px 0 0 0;
}

.coin--haut-droite {
  top: 14px;
  right: 14px;
  border-top-width: 4px;
  border-right-width: 4px;
  border-radius: 0 14px 0 0;
}

.coin--bas-gauche {
  bottom: 14px;
  left: 14px;
  border-bottom-width: 4px;
  border-left-width: 4px;
  border-radius: 0 0 0 14px;
}

.coin--bas-droite {
  bottom: 14px;
  right: 14px;
  border-bottom-width: 4px;
  border-right-width: 4px;
  border-radius: 0 0 14px 0;
}

.badge-ia {
  position: absolute;
  top: 34px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  border-radius: 999px;
  background: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
}

.badge-ia--code {
  top: auto;
  bottom: -46px;
}

.guide-code {
  position: relative;
  width: 240px;
  height: 130px;
  box-sizing: border-box;
  border-radius: 16px;
  border: 3px solid var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.guide-code__barre {
  height: 70px;
  background: var(--couleur-scan-code);
}

.guide-code__laser {
  position: absolute;
  left: 12px;
  right: 12px;
  top: 50%;
  height: 2px;
  background: var(--couleur-scan-laser);
}

.consigne,
.erreur,
.confirmation {
  margin: 0;
  max-width: 290px;
  text-align: center;
  font-size: 14px;
  line-height: 1.5;
}

.consigne {
  color: var(--couleur-scan-texte);
}

.erreur {
  color: var(--couleur-primaire-pale);
  font-weight: 700;
}

.confirmation {
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--couleur-succes-fond);
  color: var(--couleur-succes);
  font-size: 13px;
  font-weight: 700;
}

.commandes {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 22px;
  padding: 18px 20px 30px;
}

.modes {
  display: flex;
  padding: 4px;
  border-radius: 999px;
  background: var(--couleur-scan-onglets);
}

.modes__bouton {
  height: 40px;
  padding: 0 18px;
  border-radius: 999px;
  border: 0;
  background: transparent;
  color: var(--couleur-sur-primaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.modes__bouton--actif {
  background: var(--couleur-sur-primaire);
  color: var(--couleur-primaire);
}

.actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 300px;
}

.declencheur {
  width: 78px;
  height: 78px;
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
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--couleur-primaire);
}
</style>
