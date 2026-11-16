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
const nouvelleCategorie = reactive({ parentId: '', zoneTenue: '', nom: '' })
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
    () => api('/admin/categories', { methode: 'POST', corps: { parentId: Number(nouvelleCategorie.parentId), nom: nouvelleCategorie.nom, ...(nouvelleCategorie.zoneTenue ? { zoneTenue: nouvelleCategorie.zoneTenue } : {}) } }),
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
  <div class="pile referentiels">
    <header class="entete entete--onglets">
      <h1 class="titre-page seulement-ordinateur">{{ t('admin.referentiels.titre') }}</h1>
      <div role="tablist" class="segments onglets" :aria-label="t('admin.referentiels.titre')">
        <button
          v-for="nom in ['categories', 'couleurs', 'marques']"
          :key="nom"
          type="button"
          role="tab"
          class="segment"
          :class="{ 'segment--actif': onglet === nom }"
          :aria-selected="onglet === nom"
          @click="changerOnglet(nom)"
        >
          {{ t(`admin.referentiels.${nom}`) }}
        </button>
      </div>
    </header>

    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="bandeau-info" role="status">{{ succes }}</p>
    <p v-if="!donnees" class="etat">{{ t('commun.chargement') }}</p>

    <div v-else class="colonne-droite disposition">
      <section class="pile">
        <div class="ligne-titre">
          <h2 class="titre-section">{{ t(`admin.referentiels.${onglet}`) }} · {{ donnees[onglet].length }}</h2>
          <a v-if="onglet !== 'marques'" href="#nouveau" class="lien seulement-mobile">+ {{ t('commun.ajouter') }}</a>
        </div>
        <input v-model="filtre" type="search" class="saisie filtre" :placeholder="t('admin.referentiels.filtrer')" :aria-label="t('admin.referentiels.filtrer')" />

        <!-- Catégories : racines en gras, sous-catégories en retrait -->
        <div v-if="onglet === 'categories'" class="defilement">
          <table class="tableau-admin tableau-adaptable">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.referentiels.categorie') }}</th>
                <th scope="col">{{ t('admin.referentiels.zone') }}</th>
                <th scope="col">{{ t('admin.referentiels.articles') }}</th>
                <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="categorie in categories" :key="categorie.id">
                <td class="cellule-nom" :style="{ paddingLeft: `${10 + profondeur(categorie) * 18}px` }">
                  <form v-if="enEdition('categorie', categorie)" class="edition" @submit.prevent="enregistrerCategorie(categorie, { nom: edition.nom })">
                    <input v-model="edition.nom" class="saisie saisie--compacte" maxlength="80" required :aria-label="t('admin.referentiels.nom')" />
                    <button type="submit" class="bouton bouton--petit">{{ t('commun.enregistrer') }}</button>
                    <button type="button" class="bouton bouton--contour bouton--petit" @click="edition = null">{{ t('commun.annuler') }}</button>
                  </form>
                  <component :is="categorie.parentId ? 'span' : 'b'" v-else>{{ categorie.nom }}</component>
                </td>
                <td>
                  <select
                    class="saisie saisie--compacte"
                    :value="categorie.zoneTenue ?? ''"
                    :aria-label="t('admin.referentiels.zoneDe', { nom: categorie.nom })"
                    @change="enregistrerCategorie(categorie, { zoneTenue: $event.target.value || null })"
                  >
                    <option value="">—</option>
                    <option v-for="zone in ZONES" :key="zone" :value="zone">{{ t(`tenues.zones.${zone}`) }}</option>
                  </select>
                </td>
                <td class="nombre texte-secondaire">{{ categorie.nbArticles }}</td>
                <td class="actions">
                  <button type="button" class="badge" :aria-label="t('admin.referentiels.renommerNom', { nom: categorie.nom })" @click="editer('categorie', categorie)">✎</button>
                  <button
                    v-if="categorie.nbArticles === 0 && categorie.nbEnfants === 0"
                    type="button"
                    class="badge"
                    :aria-label="t('admin.referentiels.supprimerNom', { nom: categorie.nom })"
                    @click="supprimerCategorie(categorie)"
                  >
                    ✕
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Couleurs -->
        <div v-else-if="onglet === 'couleurs'" class="defilement">
          <table class="tableau-admin tableau-adaptable">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.referentiels.couleur') }}</th>
                <th scope="col">{{ t('admin.referentiels.codeHex') }}</th>
                <th scope="col">{{ t('admin.referentiels.articles') }}</th>
                <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="couleur in couleurs" :key="couleur.id">
                <template v-if="enEdition('couleur', couleur)">
                  <td class="cellule-nom">
                    <span class="teinte">
                      <input v-model="edition.codeHex" type="color" class="teinte__choix" :aria-label="t('admin.referentiels.teinte')" />
                      <input v-model="edition.nom" class="saisie saisie--compacte" maxlength="30" required :aria-label="t('admin.referentiels.nom')" />
                    </span>
                  </td>
                  <td><input v-model="edition.codeHex" class="saisie saisie--compacte code" maxlength="7" :aria-label="t('admin.referentiels.codeHex')" /></td>
                  <td class="nombre texte-secondaire">{{ couleur.nbArticles }}</td>
                  <td class="actions">
                    <button type="button" class="bouton bouton--petit" @click="enregistrerCouleur">{{ t('commun.enregistrer') }}</button>
                    <button type="button" class="bouton bouton--contour bouton--petit" @click="edition = null">{{ t('commun.annuler') }}</button>
                  </td>
                </template>
                <template v-else>
                  <td class="cellule-nom">
                    <span class="teinte">
                      <span class="pastille-couleur" :style="{ background: couleur.codeHex }" aria-hidden="true"></span>
                      {{ couleur.nom }}
                    </span>
                  </td>
                  <td class="texte-secondaire">{{ couleur.codeHex }}</td>
                  <td class="nombre texte-secondaire">{{ couleur.nbArticles }}</td>
                  <td class="actions">
                    <button type="button" class="badge" :aria-label="t('admin.referentiels.renommerNom', { nom: couleur.nom })" @click="editer('couleur', couleur)">✎</button>
                    <button v-if="couleur.nbArticles === 0" type="button" class="badge" :aria-label="t('admin.referentiels.supprimerNom', { nom: couleur.nom })" @click="supprimerCouleur(couleur)">✕</button>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Marques : créées par les membres (saisie ou IA), l'administrateur corrige ou supprime -->
        <template v-else>
          <p v-if="marques.length === 0" class="etat">{{ t('admin.referentiels.aucuneMarque') }}</p>
          <div v-else class="defilement">
            <table class="tableau-admin tableau-adaptable">
              <thead>
                <tr>
                  <th scope="col">{{ t('admin.referentiels.marque') }}</th>
                  <th scope="col">{{ t('admin.referentiels.articles') }}</th>
                  <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="marque in marques" :key="marque.id">
                  <td class="cellule-nom">
                    <form v-if="enEdition('marque', marque)" class="edition" @submit.prevent="enregistrerMarque">
                      <input v-model="edition.nom" class="saisie saisie--compacte" maxlength="80" required :aria-label="t('admin.referentiels.nom')" />
                      <button type="submit" class="bouton bouton--petit">{{ t('commun.enregistrer') }}</button>
                      <button type="button" class="bouton bouton--contour bouton--petit" @click="edition = null">{{ t('commun.annuler') }}</button>
                    </form>
                    <template v-else>{{ marque.nom }}</template>
                  </td>
                  <td class="nombre texte-secondaire">{{ marque.nbArticles }}</td>
                  <td class="actions">
                    <button type="button" class="badge" :aria-label="t('admin.referentiels.renommerNom', { nom: marque.nom })" @click="editer('marque', marque)">✎</button>
                    <button type="button" class="badge" :aria-label="t('admin.referentiels.supprimerNom', { nom: marque.nom })" @click="supprimerMarque(marque)">✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>

      <!-- Colonne de droite (en bas sur téléphone) : ajout et rappel des règles -->
      <div id="nouveau" class="pile">
        <form v-if="onglet === 'categories'" class="carte carte--grise pile" @submit.prevent="ajouterCategorie">
          <b>{{ t('admin.referentiels.nouvelleCategorie') }}</b>
          <div class="champs-ajout">
            <label class="champ">
              <span class="champ__libelle">{{ t('admin.referentiels.parent') }}</span>
              <select v-model="nouvelleCategorie.parentId" class="saisie" required>
                <option value="" disabled>{{ t('admin.referentiels.choisirParent') }}</option>
                <option v-for="categorie in donnees.categories" :key="categorie.id" :value="categorie.id">{{ categorie.chemin }}</option>
              </select>
            </label>
            <label class="champ">
              <span class="champ__libelle">{{ t('admin.referentiels.zone') }}</span>
              <select v-model="nouvelleCategorie.zoneTenue" class="saisie">
                <option value="">{{ t('admin.referentiels.zoneParent') }}</option>
                <option v-for="zone in ZONES" :key="zone" :value="zone">{{ t(`tenues.zones.${zone}`) }}</option>
              </select>
            </label>
          </div>
          <label class="champ">
            <span class="champ__libelle">{{ t('admin.referentiels.nomFrancais') }}</span>
            <input v-model="nouvelleCategorie.nom" class="saisie" maxlength="80" :placeholder="t('admin.referentiels.exempleCategorie')" required />
          </label>
          <button type="submit" class="bouton bouton--large">{{ t('commun.ajouter') }}</button>
        </form>

        <form v-else-if="onglet === 'couleurs'" class="carte carte--grise pile" @submit.prevent="ajouterCouleur">
          <b>{{ t('admin.referentiels.nouvelleCouleur') }}</b>
          <label class="champ">
            <span class="champ__libelle">{{ t('admin.referentiels.nomFrancais') }}</span>
            <input v-model="nouvelleCouleur.nom" class="saisie" maxlength="30" required />
          </label>
          <label class="champ">
            <span class="champ__libelle">{{ t('admin.referentiels.teinte') }}</span>
            <span class="teinte">
              <input v-model="nouvelleCouleur.codeHex" type="color" class="teinte__choix" :aria-label="t('admin.referentiels.teinte')" />
              <input v-model="nouvelleCouleur.codeHex" class="saisie code" maxlength="7" required :aria-label="t('admin.referentiels.codeHex')" />
            </span>
          </label>
          <button type="submit" class="bouton bouton--large">{{ t('commun.ajouter') }}</button>
        </form>

        <p class="bandeau-info">{{ t(onglet === 'marques' ? 'admin.referentiels.aideMarques' : 'admin.referentiels.regles') }}</p>
        <p class="texte-secondaire">{{ t('admin.referentiels.langues') }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.referentiels {
  gap: 14px;
}

.entete--onglets {
  flex-wrap: wrap;
}

.onglets {
  flex-basis: 100%;
}

.disposition {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filtre {
  height: 40px;
}

.edition {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.teinte {
  display: flex;
  align-items: center;
  gap: 8px;
}

.teinte__choix {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  padding: 2px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 10px;
  background: var(--couleur-surface);
  cursor: pointer;
}

.code {
  width: 110px;
  text-transform: uppercase;
}

.pastille-couleur {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 20, 22, 0.15);
}

.champs-ajout {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

@media (min-width: 1024px) {
  .entete--onglets {
    flex-wrap: nowrap;
  }

  .entete--onglets .titre-page {
    flex: 1;
  }

  .onglets {
    flex-basis: 360px;
    flex-shrink: 0;
  }

  .disposition {
    display: grid;
    --largeur-colonne-fixe: 320px;
    gap: 28px;
  }

  .champs-ajout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
