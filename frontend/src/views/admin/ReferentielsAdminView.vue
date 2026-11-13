<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/services/api'

const { t } = useI18n()

const ZONES = ['HAUT', 'BAS', 'UNE_PIECE', 'COUCHE_EXTERIEURE', 'CHAUSSURES', 'ACCESSOIRE']

const donnees = ref(null)
const onglet = ref('categories')
const erreur = ref('')
const succes = ref('')
const filtre = ref('')
// Ligne en cours de modification : { type, id, nom, codeHex }
const edition = ref(null)
const nouvelleCategorie = reactive({ parentId: '', nom: '' })
const nouvelleCouleur = reactive({ nom: '', codeHex: '#6E1A33' })

const profondeur = (categorie) => categorie.chemin.split(' › ').length - 1

const filtrer = (liste, texte) => {
  const recherche = filtre.value.trim().toLocaleLowerCase()
  return recherche ? liste.filter((element) => texte(element).toLocaleLowerCase().includes(recherche)) : liste
}
const categories = computed(() => filtrer(donnees.value?.categories ?? [], (c) => c.chemin))
const couleurs = computed(() => filtrer(donnees.value?.couleurs ?? [], (c) => c.nom))
const marques = computed(() => filtrer(donnees.value?.marques ?? [], (m) => m.nom))

async function charger() {
  try {
    donnees.value = await api('/admin/referentiels')
  } catch (e) {
    erreur.value = e.message
  }
}

/** Appel à l'API puis rechargement des listes (les compteurs et l'ordre changent) */
async function executer(appel, message) {
  erreur.value = ''
  succes.value = ''
  try {
    await appel()
    succes.value = message
    edition.value = null
    await charger()
    return true
  } catch (e) {
    erreur.value = Object.values(e.violations ?? {}).join(' ') || e.message
    // Remet les listes d'aplomb (une zone refusée ne doit pas rester affichée)
    await charger()
    return false
  }
}

function changerOnglet(nom) {
  onglet.value = nom
  filtre.value = ''
  edition.value = null
}

async function ajouterCategorie() {
  const ok = await executer(
    () => api('/admin/categories', { methode: 'POST', corps: { parentId: Number(nouvelleCategorie.parentId), nom: nouvelleCategorie.nom } }),
    t('admin.referentiels.categorieAjoutee', { nom: nouvelleCategorie.nom }),
  )
  if (ok) {
    nouvelleCategorie.nom = ''
  }
}

function enregistrerCategorie(categorie, modifications) {
  return executer(
    () => api(`/admin/categories/${categorie.id}`, { methode: 'PUT', corps: { nom: categorie.nom, zoneTenue: categorie.zoneTenue, ...modifications } }),
    t('admin.referentiels.enregistre'),
  )
}

function supprimerCategorie(categorie) {
  if (window.confirm(t('admin.referentiels.confirmerSuppression', { nom: categorie.chemin }))) {
    executer(() => api(`/admin/categories/${categorie.id}`, { methode: 'DELETE' }), t('admin.referentiels.supprime', { nom: categorie.nom }))
  }
}

async function ajouterCouleur() {
  const ok = await executer(() => api('/admin/couleurs', { methode: 'POST', corps: { ...nouvelleCouleur } }), t('admin.referentiels.couleurAjoutee', { nom: nouvelleCouleur.nom }))
  if (ok) {
    nouvelleCouleur.nom = ''
  }
}

function enregistrerCouleur() {
  const { id, nom, codeHex } = edition.value
  executer(() => api(`/admin/couleurs/${id}`, { methode: 'PUT', corps: { nom, codeHex } }), t('admin.referentiels.enregistre'))
}

function supprimerCouleur(couleur) {
  if (window.confirm(t('admin.referentiels.confirmerSuppression', { nom: couleur.nom }))) {
    executer(() => api(`/admin/couleurs/${couleur.id}`, { methode: 'DELETE' }), t('admin.referentiels.supprime', { nom: couleur.nom }))
  }
}

function enregistrerMarque() {
  const { id, nom } = edition.value
  executer(() => api(`/admin/marques/${id}`, { methode: 'PUT', corps: { nom } }), t('admin.referentiels.enregistre'))
}

function supprimerMarque(marque) {
  const message = marque.nbArticles ? t('admin.referentiels.confirmerSuppressionMarque', { nom: marque.nom, n: marque.nbArticles }) : t('admin.referentiels.confirmerSuppression', { nom: marque.nom })
  if (window.confirm(message)) {
    executer(() => api(`/admin/marques/${marque.id}`, { methode: 'DELETE' }), t('admin.referentiels.supprime', { nom: marque.nom }))
  }
}

const enEdition = (type, element) => edition.value?.type === type && edition.value.id === element.id
const editer = (type, element) => {
  edition.value = { type, id: element.id, nom: element.nom, codeHex: element.codeHex }
}

onMounted(charger)
</script>

<template>
  <div class="admin-pile">
    <div>
      <h1 class="admin-titre">{{ t('admin.referentiels.titre') }}</h1>
      <p class="admin-sous-titre">{{ t('admin.referentiels.sousTitre') }}</p>
    </div>

    <div class="barre-outils">
      <div role="tablist" class="admin-segments" :aria-label="t('admin.referentiels.titre')">
        <button
          v-for="nom in ['categories', 'couleurs', 'marques']"
          :key="nom"
          type="button"
          role="tab"
          class="admin-segment"
          :class="{ 'admin-segment--actif': onglet === nom }"
          :aria-selected="onglet === nom"
          @click="changerOnglet(nom)"
        >
          {{ t(`admin.referentiels.${nom}`) }}<template v-if="donnees"> · {{ donnees[nom].length }}</template>
        </button>
      </div>
      <input v-model="filtre" type="search" class="admin-saisie" :placeholder="t('admin.referentiels.filtrer')" :aria-label="t('admin.referentiels.filtrer')" />
    </div>

    <p class="admin-message admin-message--info">{{ t('admin.referentiels.langues') }}</p>
    <p v-if="erreur" class="admin-message admin-message--erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="admin-message admin-message--succes" role="status">{{ succes }}</p>
    <p v-if="!donnees" class="admin-etat">{{ t('commun.chargement') }}</p>

    <!-- Catégories -->
    <section v-else-if="onglet === 'categories'" class="admin-carte">
      <form class="admin-formulaire" @submit.prevent="ajouterCategorie">
        <label class="admin-champ">
          {{ t('admin.referentiels.parent') }}
          <select v-model="nouvelleCategorie.parentId" class="admin-saisie" required>
            <option value="" disabled>{{ t('admin.referentiels.choisirParent') }}</option>
            <option v-for="categorie in donnees.categories" :key="categorie.id" :value="categorie.id">{{ categorie.chemin }}</option>
          </select>
        </label>
        <label class="admin-champ">
          {{ t('admin.referentiels.nom') }}
          <input v-model="nouvelleCategorie.nom" class="admin-saisie" maxlength="80" required />
        </label>
        <button type="submit" class="admin-bouton admin-bouton--principal">{{ t('admin.referentiels.ajouterCategorie') }}</button>
      </form>

      <div class="admin-defilement">
        <table class="admin-tableau">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.referentiels.categorie') }}</th>
              <th scope="col">{{ t('admin.referentiels.famille') }}</th>
              <th scope="col">{{ t('admin.referentiels.zone') }}</th>
              <th scope="col" class="nombre">{{ t('admin.referentiels.articles') }}</th>
              <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="categorie in categories" :key="categorie.id">
              <td :style="{ paddingLeft: `${10 + profondeur(categorie) * 18}px` }">
                <form v-if="enEdition('categorie', categorie)" class="edition" @submit.prevent="enregistrerCategorie(categorie, { nom: edition.nom })">
                  <input v-model="edition.nom" class="admin-saisie" maxlength="80" required :aria-label="t('admin.referentiels.nom')" />
                  <button type="submit" class="admin-bouton admin-bouton--principal">{{ t('commun.enregistrer') }}</button>
                  <button type="button" class="admin-bouton" @click="edition = null">{{ t('commun.annuler') }}</button>
                </form>
                <template v-else>
                  <span :class="{ racine: !categorie.parentId }">{{ categorie.nom }}</span>
                  <span v-if="categorie.nbEnfants" class="admin-secondaire"> · {{ t('admin.referentiels.sousCategories', categorie.nbEnfants) }}</span>
                </template>
              </td>
              <td><span class="admin-pastille">{{ t(`stats.familles.${categorie.famille}`) }}</span></td>
              <td>
                <select
                  class="admin-saisie zone"
                  :value="categorie.zoneTenue ?? ''"
                  :aria-label="t('admin.referentiels.zoneDe', { nom: categorie.nom })"
                  @change="enregistrerCategorie(categorie, { zoneTenue: $event.target.value || null })"
                >
                  <option value="">—</option>
                  <option v-for="zone in ZONES" :key="zone" :value="zone">{{ t(`tenues.zones.${zone}`) }}</option>
                </select>
              </td>
              <td class="nombre">{{ categorie.nbArticles }}</td>
              <td class="actions">
                <button type="button" class="admin-bouton" @click="editer('categorie', categorie)">{{ t('admin.referentiels.renommer') }}</button>
                <button
                  type="button"
                  class="admin-bouton admin-bouton--danger"
                  :disabled="categorie.nbArticles > 0 || categorie.nbEnfants > 0"
                  :title="categorie.nbArticles > 0 || categorie.nbEnfants > 0 ? t('admin.referentiels.categorieUtilisee') : ''"
                  @click="supprimerCategorie(categorie)"
                >
                  {{ t('commun.supprimer') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Couleurs -->
    <section v-else-if="onglet === 'couleurs'" class="admin-carte">
      <form class="admin-formulaire" @submit.prevent="ajouterCouleur">
        <label class="admin-champ">
          {{ t('admin.referentiels.nom') }}
          <input v-model="nouvelleCouleur.nom" class="admin-saisie" maxlength="30" required />
        </label>
        <label class="admin-champ">
          {{ t('admin.referentiels.teinte') }}
          <span class="teinte">
            <input v-model="nouvelleCouleur.codeHex" type="color" class="teinte__choix" :aria-label="t('admin.referentiels.teinte')" />
            <input v-model="nouvelleCouleur.codeHex" class="admin-saisie teinte__code" maxlength="7" required :aria-label="t('admin.referentiels.codeHex')" />
          </span>
        </label>
        <button type="submit" class="admin-bouton admin-bouton--principal">{{ t('admin.referentiels.ajouterCouleur') }}</button>
      </form>

      <div class="admin-defilement">
        <table class="admin-tableau">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.referentiels.couleur') }}</th>
              <th scope="col">{{ t('admin.referentiels.codeHex') }}</th>
              <th scope="col" class="nombre">{{ t('admin.referentiels.articles') }}</th>
              <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="couleur in couleurs" :key="couleur.id">
              <template v-if="enEdition('couleur', couleur)">
                <td>
                  <span class="teinte">
                    <input v-model="edition.codeHex" type="color" class="teinte__choix" :aria-label="t('admin.referentiels.teinte')" />
                    <input v-model="edition.nom" class="admin-saisie" maxlength="30" required :aria-label="t('admin.referentiels.nom')" />
                  </span>
                </td>
                <td><input v-model="edition.codeHex" class="admin-saisie teinte__code" maxlength="7" :aria-label="t('admin.referentiels.codeHex')" /></td>
                <td class="nombre">{{ couleur.nbArticles }}</td>
                <td class="actions">
                  <button type="button" class="admin-bouton admin-bouton--principal" @click="enregistrerCouleur">{{ t('commun.enregistrer') }}</button>
                  <button type="button" class="admin-bouton" @click="edition = null">{{ t('commun.annuler') }}</button>
                </td>
              </template>
              <template v-else>
                <td>
                  <span class="teinte">
                    <span class="pastille-couleur" :style="{ background: couleur.codeHex }" aria-hidden="true" />
                    {{ couleur.nom }}
                  </span>
                </td>
                <td class="admin-secondaire">{{ couleur.codeHex }}</td>
                <td class="nombre">{{ couleur.nbArticles }}</td>
                <td class="actions">
                  <button type="button" class="admin-bouton" @click="editer('couleur', couleur)">{{ t('admin.referentiels.modifier') }}</button>
                  <button
                    type="button"
                    class="admin-bouton admin-bouton--danger"
                    :disabled="couleur.nbArticles > 0"
                    :title="couleur.nbArticles > 0 ? t('admin.referentiels.couleurUtilisee') : ''"
                    @click="supprimerCouleur(couleur)"
                  >
                    {{ t('commun.supprimer') }}
                  </button>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Marques : créées par les membres (saisie ou IA), l'administrateur corrige ou supprime -->
    <section v-else class="admin-carte">
      <p class="admin-secondaire aide">{{ t('admin.referentiels.aideMarques') }}</p>
      <p v-if="marques.length === 0" class="admin-etat">{{ t('admin.referentiels.aucuneMarque') }}</p>
      <div v-else class="admin-defilement">
        <table class="admin-tableau">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.referentiels.marque') }}</th>
              <th scope="col" class="nombre">{{ t('admin.referentiels.articles') }}</th>
              <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="marque in marques" :key="marque.id">
              <td>
                <form v-if="enEdition('marque', marque)" class="edition" @submit.prevent="enregistrerMarque">
                  <input v-model="edition.nom" class="admin-saisie" maxlength="80" required :aria-label="t('admin.referentiels.nom')" />
                  <button type="submit" class="admin-bouton admin-bouton--principal">{{ t('commun.enregistrer') }}</button>
                  <button type="button" class="admin-bouton" @click="edition = null">{{ t('commun.annuler') }}</button>
                </form>
                <template v-else>{{ marque.nom }}</template>
              </td>
              <td class="nombre">{{ marque.nbArticles }}</td>
              <td class="actions">
                <button type="button" class="admin-bouton" @click="editer('marque', marque)">{{ t('admin.referentiels.renommer') }}</button>
                <button type="button" class="admin-bouton admin-bouton--danger" @click="supprimerMarque(marque)">{{ t('commun.supprimer') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.barre-outils {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.racine {
  font-weight: 800;
}

.zone {
  height: 32px;
  padding: 0 8px;
  font-size: 13px;
}

.edition {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.edition .admin-bouton + .admin-bouton {
  margin-left: 0;
}

.teinte {
  display: flex;
  align-items: center;
  gap: 8px;
}

.teinte__choix {
  width: 36px;
  height: 36px;
  padding: 2px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 10px;
  background: var(--couleur-surface);
  cursor: pointer;
}

.teinte__code {
  width: 100px;
  text-transform: uppercase;
}

.pastille-couleur {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 20, 22, 0.15);
}

.aide {
  margin: 0;
}
</style>
