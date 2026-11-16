<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import RappelArticle from '@/components/RappelArticle.vue'
import { api } from '@/services/api'
import { aujourdhui } from '@/utils/dates'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const article = ref(null)
const erreur = ref('')
const erreurs = ref({})
const envoi = ref(false)

// L'emprunteur est un membre de l'application ou un simple nom
const type = ref('nom')
const valeurs = reactive({ emprunteurNom: '', datePret: aujourdhui(), dateRetourPrevue: '', note: '' })
const membreChoisi = ref(null)
const recherche = ref('')
const suggestions = ref([])

const retourArticle = { name: 'article', params: { id: route.params.id } }
// « Levi's · Bleu · Chambre › Armoire »
const detail = computed(() => {
  const emplacement = article.value.emplacement
  return [article.value.infos, emplacement ? `${emplacement.piece.nom} › ${emplacement.stockage.nom}` : null].filter(Boolean).join(' · ')
})

let delai = null
watch(recherche, (texte) => {
  clearTimeout(delai)
  // Le texte vient d'être rempli par le choix d'un membre : rien à chercher
  if (texte === membreChoisi.value?.pseudo) {
    return
  }
  membreChoisi.value = null
  if (texte.trim().length < 2) {
    suggestions.value = []
    return
  }
  delai = setTimeout(async () => {
    try {
      suggestions.value = await api(`/membres?q=${encodeURIComponent(texte.trim())}`)
    } catch {
      suggestions.value = []
    }
  }, 250)
})

function choisir(membre) {
  membreChoisi.value = membre
  recherche.value = membre.pseudo
  suggestions.value = []
}

async function preter() {
  erreur.value = ''
  erreurs.value = {}
  if (type.value === 'membre' && !membreChoisi.value) {
    erreurs.value = { emprunteur: t('preter.choisirMembre') }
    return
  }

  envoi.value = true
  try {
    await api(`/articles/${article.value.id}/prets`, {
      methode: 'POST',
      corps: {
        ...(type.value === 'membre' ? { emprunteur: membreChoisi.value.pseudo } : { emprunteurNom: valeurs.emprunteurNom }),
        datePret: valeurs.datePret,
        dateRetourPrevue: valeurs.dateRetourPrevue || null,
        note: valeurs.note,
      },
    })
    router.replace(retourArticle)
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = e.violations.general ?? (Object.keys(e.violations).length ? t('commun.verifierChamps') : e.message)
  } finally {
    envoi.value = false
  }
}

onMounted(async () => {
  try {
    article.value = await api(`/articles/${route.params.id}`)
    if (!article.value.peutModifier || article.value.pretEnCours) {
      router.replace(retourArticle)
    }
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="retourArticle" class="bouton-icone" :aria-label="t('preter.retourArticle')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t('preter.preterArticle') }}</h1>
      </header>

      <p v-if="!article && !erreur" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="!article" class="message-erreur" role="alert">{{ erreur }}</p>

      <div v-else class="colonne-gauche disposition">
        <RappelArticle :article="article" :detail="detail" />

        <form class="formulaire colonne-etroite" novalidate @submit.prevent="preter">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <div class="champ">
            <span class="champ__libelle">{{ t('preter.aQui') }}</span>
            <div class="segments choix-type" role="radiogroup" :aria-label="t('preter.typeEmprunteur')">
              <button type="button" role="radio" class="segment" :class="{ 'segment--actif': type === 'nom' }" :aria-checked="type === 'nom'" @click="type = 'nom'">{{ t('preter.unNom') }}</button>
              <button type="button" role="radio" class="segment" :class="{ 'segment--actif': type === 'membre' }" :aria-checked="type === 'membre'" @click="type = 'membre'">{{ t('preter.unMembre') }}</button>
            </div>
          </div>

          <label v-if="type === 'nom'" class="champ">
            <span class="champ__libelle">{{ t('preter.nomEmprunteur') }}</span>
            <input v-model.trim="valeurs.emprunteurNom" class="saisie" :class="{ 'saisie--invalide': erreurs.emprunteurNom }" type="text" maxlength="100" :placeholder="t('preter.exempleNom')" />
            <span v-if="erreurs.emprunteurNom" class="champ__erreur">{{ erreurs.emprunteurNom }}</span>
          </label>
          <div v-else class="champ">
            <label class="champ__libelle" for="pseudo-emprunteur">{{ t('preter.pseudoMembre') }}</label>
            <span class="saisie saisie--groupe" :class="{ 'saisie--invalide': erreurs.emprunteur }">
              <span aria-hidden="true" class="arobase">@</span>
              <input id="pseudo-emprunteur" v-model="recherche" type="search" autocomplete="off" />
            </span>
            <ul v-if="suggestions.length" class="suggestions">
              <li v-for="membre in suggestions" :key="membre.id">
                <button type="button" class="suggestion" @click="choisir(membre)">
                  <AvatarMembre :membre="membre" :taille="32" />
                  <span class="suggestion__pseudo">{{ membre.pseudo }}</span>
                  <span v-if="membre.nomComplet" class="texte-secondaire">{{ membre.nomComplet }}</span>
                </button>
              </li>
            </ul>
            <span v-if="membreChoisi" class="choisi">{{ t('article.preteA', { nom: `@${membreChoisi.pseudo}` }) }}</span>
            <span v-if="erreurs.emprunteur" class="champ__erreur">{{ erreurs.emprunteur }}</span>
          </div>

          <div class="grille-2">
            <label class="champ">
              <span class="champ__libelle">{{ t('preter.datePret') }}</span>
              <input v-model="valeurs.datePret" class="saisie" :class="{ 'saisie--invalide': erreurs.datePret }" type="date" :max="aujourdhui()" required />
              <span v-if="erreurs.datePret" class="champ__erreur">{{ erreurs.datePret }}</span>
            </label>
            <label class="champ">
              <span class="champ__libelle">{{ t('prets.retourPrevu') }}</span>
              <input v-model="valeurs.dateRetourPrevue" class="saisie" :class="{ 'saisie--invalide': erreurs.dateRetourPrevue }" type="date" :min="valeurs.datePret" />
              <span v-if="erreurs.dateRetourPrevue" class="champ__erreur">{{ erreurs.dateRetourPrevue }}</span>
            </label>
          </div>

          <label class="champ">
            <span class="champ__libelle">{{ t('ajout.note') }}</span>
            <textarea v-model="valeurs.note" class="saisie" rows="3" maxlength="500" :placeholder="t('preter.exempleNote')"></textarea>
          </label>

          <div class="barre-actions barre-actions--dans-page barre-actions--droite">
            <RouterLink :to="retourArticle" class="bouton bouton--contour seulement-ordinateur">{{ t('commun.annuler') }}</RouterLink>
            <button type="submit" class="bouton valider" :disabled="envoi">{{ t('preter.preterArticle') }}</button>
          </div>
        </form>
      </div>
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
.formulaire {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.disposition {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.arobase {
  color: var(--couleur-texte-secondaire);
  font-weight: 700;
  margin-right: 4px;
}

.suggestions {
  margin: 0;
  padding: 6px;
  list-style: none;
  border-radius: 12px;
  background: var(--couleur-surface);
  border: 1.5px solid var(--couleur-bordure);
}

.suggestion {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border: 0;
  border-radius: 10px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.suggestion:hover {
  background: var(--couleur-primaire-pale);
}

.suggestion__pseudo {
  font-size: 14px;
  font-weight: 800;
}

.choisi {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-primaire);
}

.valider {
  flex: 1;
}

@media (min-width: 1024px) {
  .disposition {
    display: grid;
    --largeur-colonne-fixe: 320px;
    gap: 40px;
  }

  .colonne-etroite {
    --largeur-etroite: 560px;
    gap: 14px;
  }

  .choix-type {
    width: 280px;
  }

  .valider {
    flex: none;
  }
}
</style>
