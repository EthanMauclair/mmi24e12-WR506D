<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import CarteTenue from '@/components/CarteTenue.vue'
import { api } from '@/services/api'
import { fondVignette } from '@/utils/couleurs'
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

function choisirEtiquette(id) {
  etiquetteId.value = etiquetteId.value === id ? null : id
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
    // Comme sur la maquette, une tenue partagée s'affiche en tête du fil ; une tenue privée, sur le profil
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
    <main class="contenu">
      <div class="segments" role="tablist" :aria-label="t('navigation.tenues')">
        <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'generer' }" :aria-selected="onglet === 'generer'" @click="ouvrirOnglet('generer')">{{ t('tenues.generer') }}</button>
        <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'fil' }" :aria-selected="onglet === 'fil'" @click="ouvrirOnglet('fil')">{{ t('tenues.fil') }}</button>
      </div>

      <template v-if="onglet === 'fil'">
        <p v-if="erreurFil" class="message-erreur" role="alert">{{ erreurFil }}</p>
        <p v-if="fil === null" class="etat">{{ t('tenues.chargementFil') }}</p>
        <div v-else-if="fil.length === 0" class="fil-vide">
          <p class="etat">{{ t('tenues.filVide') }}</p>
          <RouterLink :to="{ name: 'membres' }" class="bouton-principal">{{ t('profil.rechercherMembres') }}</RouterLink>
        </div>
        <template v-else>
          <CarteTenue v-for="tenueDuFil in fil" :key="tenueDuFil.id" :tenue="tenueDuFil" @supprimee="retirerDuFil" />
          <button v-if="suiteFil" type="button" class="voir-plus" :disabled="chargementFil" @click="chargerFil(true)">{{ t('tenues.voirPlus') }}</button>
        </template>
      </template>

      <template v-else-if="logementId === null && !chargement">
        <p class="etat">{{ t('tenues.sansLogement') }}</p>
        <RouterLink :to="{ name: 'dressing' }" class="bouton-principal">{{ t('tenues.allerDressing') }}</RouterLink>
      </template>

      <template v-else>
        <div class="meteo">
          <span class="meteo__icone">
            <svg v-if="etatMeteo === 'ok' && iconeMeteo === 'pluie'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 19v2M8 13v2M16 19v2M16 13v2M12 21v2M12 15v2" /></svg>
            <svg v-else-if="etatMeteo === 'ok' && iconeMeteo === 'soleil'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" /></svg>
          </span>
          <span class="meteo__textes">
            <template v-if="etatMeteo === 'ok'">
              <span class="meteo__titre">{{ meteo.ville ?? t('tenues.votrePosition') }} · {{ meteo.libelle }} · {{ meteo.temperature }} °C</span>
              <span class="meteo__conseil">{{ meteo.conseil }}</span>
            </template>
            <template v-else-if="etatMeteo === 'recherche'">
              <span class="meteo__titre">{{ t('tenues.meteoEnCours') }}</span>
              <span class="meteo__conseil">{{ t('tenues.autoriserLocalisation') }}</span>
            </template>
            <template v-else>
              <span class="meteo__titre">{{ t('tenues.meteoIndisponible') }}</span>
              <span class="meteo__conseil">{{ t('tenues.selonSaison') }}</span>
            </template>
          </span>
          <span v-if="etatMeteo === 'ok'" class="meteo__direct">{{ t('tenues.enDirect') }}</span>
          <button v-else-if="etatMeteo === 'indisponible'" type="button" class="meteo__reessayer" @click="localiser">{{ t('tenues.reessayer') }}</button>
        </div>

        <div class="occasions">
          <span class="occasions__titre">{{ t('tenues.occasion') }}</span>
          <div class="occasions__liste">
            <button
              v-for="etiquette in etiquettes"
              :key="etiquette.id"
              type="button"
              class="occasion"
              :class="{ 'occasion--active': etiquette.id === etiquetteId }"
              :aria-pressed="etiquette.id === etiquetteId"
              @click="choisirEtiquette(etiquette.id)"
            >
              {{ etiquette.nom }}
            </button>
            <button
              v-if="peutCreerEtiquette && !creationEtiquette"
              type="button"
              class="occasion-ajout"
              :aria-label="t('tenues.creerEtiquette')"
              @click="creationEtiquette = true"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            </button>
          </div>
          <form v-if="creationEtiquette" class="nouvelle-etiquette" @submit.prevent="creerEtiquette">
            <input v-model.trim="nomEtiquette" class="saisie nouvelle-etiquette__nom" :class="{ 'saisie--invalide': erreurEtiquette }" type="text" maxlength="30" :placeholder="t('tenues.exempleEtiquette')" :aria-label="t('tenues.nomEtiquette')" autofocus />
            <button type="button" class="nouvelle-etiquette__annuler" @click="creationEtiquette = false">{{ t('commun.annuler') }}</button>
            <button type="submit" class="nouvelle-etiquette__valider" :disabled="!nomEtiquette">{{ t('commun.creer') }}</button>
          </form>
          <span v-if="erreurEtiquette" class="champ__erreur">{{ erreurEtiquette }}</span>
        </div>

        <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
        <p v-else-if="chargement && tenue.length === 0" class="etat">{{ t('tenues.composition') }}</p>
        <p v-else-if="tenue.length && articlesProposes.length === 0" class="etat">
          {{ t('tenues.aucunVetement') }}
        </p>
        <div v-else class="tenue" :class="{ 'tenue--chargement': chargement }" aria-live="polite">
          <template v-for="ligne in tenue" :key="ligne.zone">
            <RouterLink
              v-if="ligne.article"
              :to="{ name: 'article', params: { id: ligne.article.id } }"
              class="piece"
              :style="{ background: fondVignette(ligne.article.couleur) }"
            >
              <img v-if="ligne.article.photo" :src="ligne.article.photo" alt="" class="piece__photo" />
              <span class="piece__zone">{{ t(`tenues.zones.${ligne.zone}`) }}</span>
              <span v-if="ligne.pluie" class="piece__pluie">{{ t('tenues.pluie') }}</span>
              <span class="piece__textes">
                <span class="piece__nom">{{ ligne.article.titre }}</span>
                <span class="piece__emplacement">{{ ligne.article.emplacement ?? t('commun.aRanger') }}</span>
              </span>
            </RouterLink>
            <div v-else class="piece piece--vide">
              <span class="piece__zone">{{ t(`tenues.zones.${ligne.zone}`) }}</span>
              <span class="piece__manque">{{ t('tenues.manque') }}</span>
            </div>
          </template>
        </div>
        <p v-if="pasDAutreIdee" class="info">{{ t('tenues.pasDAutreIdee') }}</p>

        <form v-if="partage" class="partage" @submit.prevent="publier">
          <label class="champ">
            <span class="champ__libelle">{{ t('scanCode.titre') }}</span>
            <input v-model.trim="titre" class="saisie partage__saisie" type="text" maxlength="100" required />
          </label>
          <label class="champ">
            <span class="champ__libelle">{{ t('tenues.legende') }}</span>
            <textarea v-model="description" class="saisie partage__zone" rows="2" maxlength="500" :placeholder="t('tenues.exempleLegende')"></textarea>
          </label>
          <label class="partage__privee">
            <input v-model="estPrivee" type="checkbox" />
            {{ t('tenues.privee') }}
          </label>
          <p v-if="erreurPartage" class="message-erreur" role="alert">{{ erreurPartage }}</p>
          <div class="actions">
            <button type="button" class="action action--secondaire" @click="partage = false">{{ t('commun.annuler') }}</button>
            <button type="submit" class="action action--principale" :disabled="envoi || !titre">{{ t('commun.publier') }}</button>
          </div>
        </form>

        <div v-else class="actions">
          <button type="button" class="action action--secondaire" :disabled="chargement" @click="generer(true)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 4v6h-6" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            {{ t('tenues.autreIdee') }}
          </button>
          <button type="button" class="action action--principale" :disabled="chargement || articlesProposes.length === 0" @click="ouvrirPartage">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4z" /></svg>
            {{ t('tenues.partager') }}
          </button>
        </div>
      </template>
    </main>

    <BarreNavigation actif="tenues" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.fil-vide {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.voir-plus {
  height: 44px;
  border-radius: 14px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.segments {
  display: flex;
  padding: 4px;
  border-radius: 14px;
  background: var(--couleur-info-fond);
}

.segment {
  flex: 1;
  height: 38px;
  border: 0;
  border-radius: 11px;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.segment--actif {
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font-weight: 800;
  box-shadow: var(--ombre-segment);
}

.meteo {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.meteo__icone {
  width: 46px;
  height: 46px;
  flex-shrink: 0;
  border-radius: 14px;
  background: var(--couleur-meteo-fond);
  color: var(--couleur-meteo);
  display: flex;
  align-items: center;
  justify-content: center;
}

.meteo__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meteo__titre {
  font-size: 15px;
  font-weight: 800;
}

.meteo__conseil {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
}

.meteo__direct,
.meteo__reessayer {
  flex-shrink: 0;
  padding: 4px 8px;
  border: 0;
  border-radius: 8px;
  background: var(--couleur-meteo-fond);
  color: var(--couleur-meteo);
  font: inherit;
  font-size: 11px;
  font-weight: 700;
}

.meteo__reessayer {
  cursor: pointer;
}

.occasions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.occasions__titre {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.occasions__liste {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.occasion {
  height: 38px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.occasion--active {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.occasion-ajout {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1.5px dashed var(--couleur-bordure-pointillee);
  background: none;
  color: var(--couleur-texte-secondaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.nouvelle-etiquette {
  display: flex;
  gap: 8px;
}

.nouvelle-etiquette__nom {
  flex: 1;
  min-width: 0;
  height: 40px;
}

.nouvelle-etiquette__annuler,
.nouvelle-etiquette__valider {
  height: 40px;
  padding: 0 12px;
  border-radius: 12px;
  border: 0;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.nouvelle-etiquette__annuler {
  background: none;
  color: var(--couleur-texte-secondaire);
}

.nouvelle-etiquette__valider {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.nouvelle-etiquette__valider:disabled {
  opacity: 0.5;
}

.tenue {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  transition: opacity 0.2s;
}

.tenue--chargement {
  opacity: 0.5;
}

.piece {
  position: relative;
  height: 150px;
  box-sizing: border-box;
  padding: 10px;
  border-radius: 18px;
  /* Un vêtement blanc reste visible sur le fond de l'écran */
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  color: var(--couleur-texte);
}

.piece__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.piece__zone,
.piece__pluie {
  position: absolute;
  top: 10px;
  padding: 3px 8px;
  border-radius: 8px;
  font-size: 11px;
}

.piece__zone {
  left: 10px;
  background: var(--couleur-photo-etiquette);
  color: var(--couleur-texte);
  font-weight: 800;
}

.piece__pluie {
  right: 10px;
  background: var(--couleur-meteo);
  color: var(--couleur-sur-primaire);
  font-weight: 700;
}

.piece__textes {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--couleur-photo-bouton);
}

.piece__nom {
  font-size: 13px;
  font-weight: 800;
}

.piece__emplacement {
  font-size: 11px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
}

.piece--vide {
  justify-content: center;
  align-items: center;
  border: 1.5px dashed var(--couleur-bordure-pointillee);
}

.piece__manque {
  font-size: 12px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
  text-align: center;
}

.info {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--couleur-primaire);
}

.actions {
  display: flex;
  gap: 10px;
}

.action {
  flex: 1;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font: inherit;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
}

.action:disabled {
  opacity: 0.6;
  cursor: default;
}

.action--secondaire {
  border: 1.5px solid var(--couleur-primaire);
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
}

.action--principale {
  border: 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.partage {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.partage .champ {
  gap: 6px;
}

.partage__saisie {
  height: 46px;
}

.partage__zone {
  padding: 10px 16px;
  resize: vertical;
  line-height: 1.5;
}

.partage__privee {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  accent-color: var(--couleur-primaire);
}
</style>
