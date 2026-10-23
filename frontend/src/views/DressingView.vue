<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { logementMemorise, memoriserLogementActif } from '@/utils/logementActif'

const FONDS_PIECES = ['rose', 'sable', 'ardoise', 'lavande'].map((teinte) => `var(--couleur-pastel-${teinte})`)
const LIBELLES_ROLES = { PROPRIETAIRE: 'Propriétaire', COPROPRIETAIRE: 'Co-propriétaire', MINEUR: 'Membre' }

const session = useSessionStore()
const router = useRouter()

const logements = ref([])
const logement = ref(null)
const chargement = ref(true)
const erreur = ref('')
const menuOuvert = ref(false)

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
  const coproprietaires = nb === 0 ? '' : ` · ${nb} co-propriétaire${nb > 1 ? 's' : ''}`
  return LIBELLES_ROLES[logement.value.role] + coproprietaires
})

function pluriel(nombre, mot) {
  return nombre > 1 ? `${mot}s` : mot
}

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

onMounted(charger)
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <p v-if="chargement" class="etat">Chargement de votre dressing…</p>

      <p v-else-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <section v-else-if="creationLogement" class="nouveau-logement">
        <h1 class="nouveau-logement__titre">{{ logements.length ? 'Nouveau logement' : 'Bienvenue !' }}</h1>
        <p class="nouveau-logement__texte">
          Créez votre logement (ou dressing) pour y ranger vos pièces, vos stockages et vos articles.
        </p>
        <form class="nouveau-logement__formulaire" @submit.prevent="creerLogement">
          <label class="champ">
            <span class="champ__libelle">Nom du logement</span>
            <input v-model="nomLogement" class="saisie saisie--ligne" :class="{ 'saisie--invalide': erreurLogement }" type="text" placeholder="Appartement Lyon" required />
            <span v-if="erreurLogement" class="champ__erreur">{{ erreurLogement }}</span>
          </label>
          <button type="submit" class="bouton-principal">Créer mon logement</button>
          <button v-if="logements.length" type="button" class="lien-bouton" @click="creationLogement = false">Annuler</button>
        </form>
      </section>

      <section v-else-if="!logement" class="nouveau-logement">
        <h1 class="nouveau-logement__titre">Bienvenue !</h1>
        <p class="nouveau-logement__texte">
          Votre compte est rattaché à un compte majeur. Il doit vous ajouter à son logement pour que vous puissiez y ranger vos articles.
        </p>
        <button type="button" class="lien-bouton" @click="seDeconnecter">Se déconnecter</button>
      </section>

      <template v-else>
        <header class="entete">
          <div class="entete__logement">
            <button type="button" class="logement" :aria-expanded="menuOuvert" @click="menuOuvert = !menuOuvert">
              {{ logement.nom }}
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            <span class="role">{{ libelleRole }}</span>

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
              <button v-if="!estMineur" type="button" class="menu__element menu__element--action" @click="ouvrirCreationLogement">+ Créer un logement</button>
              <button type="button" class="menu__element menu__element--action" @click="seDeconnecter">Se déconnecter</button>
            </div>
          </div>
          <button type="button" class="bouton-icone notifications" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
          </button>
        </header>

        <label class="recherche">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" /></svg>
          <span class="visuellement-cache">Rechercher</span>
          <input v-model="recherche" type="search" placeholder="Où est rangé… ? Article, marque, couleur" />
        </label>

        <section v-if="enRecherche" class="pieces" aria-live="polite">
          <h2 class="pieces__titre">Résultats</h2>
          <p v-if="rechercheEnCours" class="etat">Recherche…</p>
          <p v-else-if="resultats.length === 0" class="etat">Aucun article ne correspond à « {{ recherche.trim() }} ».</p>
          <div v-else class="pieces__liste">
            <RouterLink v-for="article in resultats" :key="article.id" :to="`/articles/${article.id}`" class="piece">
              <span class="piece__textes">
                <span class="piece__nom">{{ article.titre }}</span>
                <span class="piece__stockages">{{ article.emplacement ?? 'À ranger' }} · {{ article.infos }}</span>
              </span>
              <svg class="piece__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
          </div>
        </section>

        <template v-else>
          <div class="chiffres">
            <div class="chiffre">
              <span class="chiffre__valeur">{{ logement.nbArticles }}</span>
              <span class="chiffre__libelle">{{ pluriel(logement.nbArticles, 'article') }}</span>
            </div>
            <div class="chiffre">
              <span class="chiffre__valeur">{{ logement.nbPieces }}</span>
              <span class="chiffre__libelle">{{ pluriel(logement.nbPieces, 'pièce') }}</span>
            </div>
            <a href="#" class="chiffre">
              <span class="chiffre__valeur chiffre__valeur--accent">{{ logement.nbPretes }}</span>
              <span class="chiffre__libelle">{{ pluriel(logement.nbPretes, 'prêté') }}</span>
            </a>
          </div>

          <a href="#" class="tenue-du-jour">
            <span class="tenue-du-jour__icone">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25" /><path d="M8 19v2M8 13v2M16 19v2M16 13v2M12 21v2M12 15v2" /></svg>
            </span>
            <span class="tenue-du-jour__textes">
              <span class="tenue-du-jour__meteo">Selon la météo du jour</span>
              <span class="tenue-du-jour__titre">Générer ma tenue du jour</span>
            </span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
          </a>

          <section class="pieces">
            <div class="pieces__entete">
              <h2 class="pieces__titre">Pièces</h2>
              <button v-if="logement.peutGerer && !ajoutPiece" type="button" class="pieces__ajout" @click="ajoutPiece = true">+ Ajouter une pièce</button>
            </div>

            <form v-if="ajoutPiece" class="ajout" @submit.prevent="creerPiece">
              <div class="ajout__ligne">
                <input v-model="nomPiece" class="saisie saisie--ligne" :class="{ 'saisie--invalide': erreurPiece }" type="text" placeholder="Nom de la pièce" aria-label="Nom de la pièce" required />
                <button type="submit" class="ajout__valider">Ajouter</button>
              </div>
              <span v-if="erreurPiece" class="champ__erreur">{{ erreurPiece }}</span>
              <button type="button" class="lien-bouton" @click="ajoutPiece = false">Annuler</button>
            </form>

            <p v-if="logement.pieces.length === 0" class="etat">Aucune pièce pour le moment.</p>
            <div v-else class="pieces__liste">
              <RouterLink v-for="(piece, index) in logement.pieces" :key="piece.id" :to="`/pieces/${piece.id}`" class="piece">
                <span class="piece__icone" :style="{ background: FONDS_PIECES[index % FONDS_PIECES.length] }">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z" /><path d="m3 8 9 5 9-5M12 13v8" /></svg>
                </span>
                <span class="piece__textes">
                  <span class="piece__nom">{{ piece.nom }}</span>
                  <span class="piece__stockages">{{ piece.stockages.length ? piece.stockages.join(' · ') : 'Aucun stockage' }}</span>
                </span>
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
.contenu {
  flex: 1;
  padding: 20px 20px 0;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.entete {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.entete__logement {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.logement {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: var(--couleur-texte);
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
  cursor: pointer;
}

.role {
  display: inline-flex;
  align-self: flex-start;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 12px;
  font-weight: 700;
}

.notifications {
  position: relative;
}


.recherche {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 48px;
  padding: 0 16px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
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

.recherche:focus-within {
  border-color: var(--couleur-primaire);
}

.chiffres {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.chiffre {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  color: var(--couleur-texte);
}

.chiffre__valeur {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.chiffre__valeur--accent {
  color: var(--couleur-primaire);
}

.chiffre__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 500;
}

.tenue-du-jour {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 20px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour:hover {
  color: var(--couleur-sur-primaire);
}

.tenue-du-jour__icone {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border-radius: 14px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tenue-du-jour__textes {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.tenue-du-jour__meteo {
  font-size: 13px;
  font-weight: 600;
  opacity: 0.85;
}

.tenue-du-jour__titre {
  font-size: 16px;
  font-weight: 700;
}

.pieces {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pieces__entete {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.pieces__titre {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.pieces__ajout {
  border: 0;
  background: none;
  padding: 6px 0;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-primaire);
  cursor: pointer;
}

.pieces__liste {
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.piece {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--couleur-separateur);
  color: var(--couleur-texte);
}

.piece__icone {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  border-radius: 12px;
  color: var(--couleur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.piece__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.piece__nom {
  font-size: 15px;
  font-weight: 700;
}

.piece__stockages {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.piece__nombre {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte-secondaire);
}

.piece__chevron {
  color: var(--couleur-chevron);
}

.entete__logement {
  position: relative;
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

.nouveau-logement {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 24px;
}

.nouveau-logement__titre {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.nouveau-logement__texte {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--couleur-texte-secondaire);
}

.nouveau-logement__formulaire {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.nouveau-logement__formulaire .champ {
  gap: 6px;
}

.saisie--ligne {
  height: 48px;
}

.lien-bouton {
  align-self: center;
  padding: 6px 0;
  border: 0;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
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

.ajout__valider {
  padding: 0 16px;
  border: 0;
  border-radius: 14px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>
