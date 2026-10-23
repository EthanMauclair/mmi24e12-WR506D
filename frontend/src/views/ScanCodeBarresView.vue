<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import ChoixEmplacement from '@/components/ChoixEmplacement.vue'
import { api } from '@/services/api'
import { useAjoutArticleStore } from '@/stores/ajoutArticle'
import { useReferentielsStore } from '@/stores/referentiels'
import { idLogementActif } from '@/utils/logementActif'

const LIGNES = {
  VINYLE: [
    { cle: 'artiste', libelle: 'Artiste', type: 'texte' },
    { cle: 'titre', libelle: 'Album', type: 'texte' },
    { cle: 'label', libelle: 'Label', type: 'texte' },
    { cle: 'anneeSortie', libelle: 'Année', type: 'nombre' },
    { cle: 'format', libelle: 'Format', type: 'format' },
    { cle: 'nbDisques', libelle: 'Disques', type: 'nombre' },
  ],
  LIVRE: [
    { cle: 'titre', libelle: 'Titre', type: 'texte' },
    { cle: 'auteur', libelle: 'Auteur', type: 'texte' },
    { cle: 'editeur', libelle: 'Éditeur', type: 'texte' },
    { cle: 'nbPages', libelle: 'Pages', type: 'nombre' },
    { cle: 'anneeParution', libelle: 'Année', type: 'nombre' },
  ],
}

const router = useRouter()
const ajout = useAjoutArticleStore()
const referentiels = useReferentielsStore()

const fiche = ajout.fiche
const estVinyle = fiche?.type === 'VINYLE'

const logementId = ref(null)
const stockages = ref([])
const chargement = ref(true)
const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})
const couvertureVisible = ref(Boolean(fiche?.couverture))

const valeurs = reactive({
  titre: fiche?.titre ?? '',
  categorieId: fiche?.categorie?.id ?? null,
  etat: null,
  ...fiche?.vinyle,
  ...fiche?.livre,
})
const stockageId = ref(null)

const lignes = computed(() => LIGNES[fiche?.type] ?? [])
const groupesCategories = computed(() => referentiels.categoriesParRacine([fiche?.type]))
const sousTitre = computed(() => (estVinyle ? valeurs.artiste : valeurs.auteur))

function nombre(valeur) {
  return valeur === '' || valeur === null || valeur === undefined ? null : Number(valeur)
}

/**
 * @param {boolean} scannerLeSuivant revenir à la caméra au lieu d'ouvrir la fiche de l'article
 */
async function enregistrer(scannerLeSuivant) {
  erreur.value = ''
  erreurs.value = {}
  envoiEnCours.value = true

  const corps = {
    logementId: logementId.value,
    stockageId: stockageId.value,
    categorieId: valeurs.categorieId,
    titre: valeurs.titre,
    etat: valeurs.etat,
    modeAjout: 'SCAN_CODE_BARRE',
    couverture: couvertureVisible.value ? fiche.couverture : null,
  }
  if (estVinyle) {
    corps.vinyle = {
      ean: valeurs.ean,
      artiste: valeurs.artiste,
      label: valeurs.label,
      anneeSortie: nombre(valeurs.anneeSortie),
      format: valeurs.format,
      nbDisques: nombre(valeurs.nbDisques),
    }
  } else {
    corps.livre = {
      isbn: valeurs.isbn,
      auteur: valeurs.auteur,
      editeur: valeurs.editeur,
      nbPages: nombre(valeurs.nbPages),
      anneeParution: nombre(valeurs.anneeParution),
    }
  }

  try {
    const article = await api('/articles', { methode: 'POST', corps })
    ajout.reinitialiser()
    if (scannerLeSuivant) {
      ajout.confirmation = `« ${article.titre} » est ajouté à votre dressing.`
      router.replace({ name: 'scan' })
    } else {
      router.replace({ name: 'article', params: { id: article.id } })
    }
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = Object.keys(e.violations).length ? 'Vérifiez les informations signalées.' : e.message
  } finally {
    envoiEnCours.value = false
  }
}

onMounted(async () => {
  if (!fiche) {
    router.replace({ name: 'scan' })
    return
  }
  try {
    logementId.value = await idLogementActif()
    if (logementId.value === null) {
      router.replace({ name: 'dressing' })
      return
    }
    const [rangement] = await Promise.all([api(`/logements/${logementId.value}/rangement`), referentiels.charger()])
    stockages.value = rangement.stockages
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div v-if="fiche" class="ecran">
    <header class="entete">
      <RouterLink :to="{ name: 'scan' }" class="bouton-icone" aria-label="Scanner à nouveau">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <h1 class="entete__titre">{{ estVinyle ? 'Vinyle trouvé' : 'Livre trouvé' }}</h1>
    </header>

    <form id="formulaire-code-barres" class="contenu" novalidate @submit.prevent="enregistrer(false)">
      <div class="resume">
        <div class="visuel" :class="{ 'visuel--livre': !estVinyle }">
          <div v-if="estVinyle" class="visuel__disque">
            <span class="visuel__centre"></span>
          </div>
          <div class="visuel__pochette">
            <img v-if="couvertureVisible" :src="fiche.couverture" alt="" class="visuel__image" @error="couvertureVisible = false" />
            <svg v-else-if="estVinyle" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" /></svg>
            <svg v-else width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z" /><path d="M6.5 17A2.5 2.5 0 0 0 4 19.5 2.5 2.5 0 0 0 6.5 22H20v-5" /></svg>
          </div>
        </div>
        <div class="resume__textes">
          <span class="badge-code">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6v12M8 6v12M12 6v12M16 6v12M20 6v12" /></svg>
            {{ estVinyle ? 'EAN' : 'ISBN' }} {{ fiche.code }}
          </span>
          <span class="resume__titre">{{ valeurs.titre || 'Sans titre' }}</span>
          <span v-if="sousTitre" class="resume__sous-titre">{{ sousTitre }}</span>
        </div>
      </div>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <dl class="fiche">
        <div v-for="ligne in lignes" :key="ligne.cle" class="fiche__ligne" :class="{ 'fiche__ligne--invalide': erreurs[ligne.cle] }">
          <dt class="fiche__libelle"><label :for="`champ-${ligne.cle}`">{{ ligne.libelle }}</label></dt>
          <dd class="fiche__valeur">
            <select v-if="ligne.type === 'format'" :id="`champ-${ligne.cle}`" v-model="valeurs[ligne.cle]" class="fiche__saisie">
              <option :value="null">—</option>
              <option v-for="format in referentiels.formatsVinyle" :key="format.valeur" :value="format.valeur">{{ format.libelle }}</option>
            </select>
            <input
              v-else
              :id="`champ-${ligne.cle}`"
              v-model.trim="valeurs[ligne.cle]"
              class="fiche__saisie"
              :type="ligne.type === 'nombre' ? 'number' : 'text'"
              :inputmode="ligne.type === 'nombre' ? 'numeric' : undefined"
              placeholder="—"
            />
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="fiche__crayon"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
          </dd>
          <span v-if="erreurs[ligne.cle]" class="champ__erreur fiche__erreur">{{ erreurs[ligne.cle] }}</span>
        </div>
        <div class="fiche__ligne" :class="{ 'fiche__ligne--invalide': erreurs.categorieId }">
          <dt class="fiche__libelle"><label for="champ-categorie">Catégorie</label></dt>
          <dd class="fiche__valeur">
            <select id="champ-categorie" v-model="valeurs.categorieId" class="fiche__saisie">
              <option :value="null" disabled>Choisir</option>
              <optgroup v-for="groupe in groupesCategories" :key="groupe.racine" :label="groupe.racine">
                <option v-for="categorie in groupe.elements" :key="categorie.id" :value="categorie.id">{{ categorie.libelle }}</option>
              </optgroup>
            </select>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="fiche__crayon"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
          </dd>
          <span v-if="erreurs.categorieId" class="champ__erreur fiche__erreur">{{ erreurs.categorieId }}</span>
        </div>
        <div class="fiche__ligne">
          <dt class="fiche__libelle"><label for="champ-etat">État</label></dt>
          <dd class="fiche__valeur">
            <select id="champ-etat" v-model="valeurs.etat" class="fiche__saisie">
              <option :value="null">—</option>
              <option v-for="etat in referentiels.etats" :key="etat.valeur" :value="etat.valeur">{{ etat.libelle }}</option>
            </select>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="fiche__crayon"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
          </dd>
        </div>
      </dl>

      <ChoixEmplacement v-model="stockageId" libelle="Où le ranger ?" accentue :stockages="stockages" :erreur="erreurs.stockageId" />
    </form>

    <div class="pied">
      <button type="button" class="pied__suivant" :disabled="envoiEnCours || chargement" @click="enregistrer(true)">Scanner le suivant</button>
      <button type="submit" form="formulaire-code-barres" class="pied__enregistrer" :disabled="envoiEnCours || chargement">
        {{ envoiEnCours ? 'Enregistrement…' : 'Enregistrer' }}
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
  padding: 18px 20px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.resume {
  display: flex;
  gap: 16px;
  align-items: center;
}

.visuel {
  position: relative;
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.visuel--livre {
  width: 110px;
}

.visuel__disque {
  position: absolute;
  top: 10px;
  left: 34px;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: var(--couleur-disque);
  display: flex;
  align-items: center;
  justify-content: center;
}

.visuel__centre {
  width: 30px;
  height: 30px;
  box-sizing: border-box;
  border-radius: 50%;
  background: var(--couleur-primaire);
  border: 6px solid var(--couleur-disque-centre-bord);
}

.visuel__pochette {
  position: absolute;
  top: 0;
  left: 0;
  width: 110px;
  height: 120px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--couleur-pochette);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
  box-shadow: var(--ombre-pochette);
}

.visuel__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.resume__textes {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.badge-code {
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

.resume__titre {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.resume__sous-titre {
  font-size: 14px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
}

.fiche {
  margin: 0;
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.fiche__ligne {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 48px;
  padding: 0 14px;
  border-bottom: 1px solid var(--couleur-separateur);
}

.fiche__ligne:last-child {
  border-bottom: 0;
}

.fiche__ligne--invalide {
  background: var(--couleur-erreur-fond);
}

.fiche__libelle {
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.fiche__valeur {
  flex: 1;
  min-width: 0;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.fiche__saisie {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  color: var(--couleur-texte);
  text-align: right;
  text-align-last: right;
  appearance: none;
  cursor: pointer;
}

.fiche__crayon {
  flex-shrink: 0;
  color: var(--couleur-chevron);
}

.fiche__erreur {
  flex-basis: 100%;
  padding-bottom: 8px;
}

.pied {
  position: sticky;
  bottom: 0;
  display: flex;
  gap: 10px;
  padding: 12px 20px 22px;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.pied__suivant,
.pied__enregistrer {
  height: 54px;
  border-radius: 16px;
  font: inherit;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
}

.pied__suivant {
  flex: 1;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
}

.pied__enregistrer {
  flex: 1.2;
  border: 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.pied__suivant:disabled,
.pied__enregistrer:disabled {
  opacity: 0.6;
  cursor: wait;
}
</style>
