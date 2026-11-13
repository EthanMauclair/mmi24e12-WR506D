<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { fondVignette } from '@/utils/couleurs'
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
    router.replace({ name: 'article', params: { id: article.value.id } })
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
      router.replace({ name: 'article', params: { id: route.params.id } })
    }
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <RouterLink :to="{ name: 'article', params: { id: route.params.id } }" class="bouton-icone" :aria-label="t('preter.retourArticle')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="entete__titre">{{ t('article.preter') }}</h1>
      </header>

      <p v-if="!article && !erreur" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="!article" class="message-erreur" role="alert">{{ erreur }}</p>

      <form v-else class="formulaire" novalidate @submit.prevent="preter">
        <div class="article">
          <span class="article__vignette" :style="{ background: fondVignette(article.couleur?.codeHex) }">
            <img v-if="article.photos.length" :src="article.photos[0]" alt="" class="article__photo" />
          </span>
          <span class="article__textes">
            <span class="article__titre">{{ article.titre }}</span>
            <span class="article__infos">{{ article.infos }}</span>
          </span>
        </div>

        <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

        <div class="champ">
          <span class="champ__libelle">{{ t('preter.aQui') }}</span>
          <div class="segments" role="radiogroup" :aria-label="t('preter.typeEmprunteur')">
            <button type="button" role="radio" class="segment" :class="{ 'segment--actif': type === 'nom' }" :aria-checked="type === 'nom'" @click="type = 'nom'">{{ t('preter.unNom') }}</button>
            <button type="button" role="radio" class="segment" :class="{ 'segment--actif': type === 'membre' }" :aria-checked="type === 'membre'" @click="type = 'membre'">{{ t('preter.unMembre') }}</button>
          </div>
        </div>

        <label v-if="type === 'nom'" class="champ">
          <span class="visuellement-cache">{{ t('preter.nomEmprunteur') }}</span>
          <input v-model.trim="valeurs.emprunteurNom" class="saisie" :class="{ 'saisie--invalide': erreurs.emprunteurNom }" type="text" maxlength="100" :placeholder="t('preter.exempleNom')" />
          <span v-if="erreurs.emprunteurNom" class="champ__erreur">{{ erreurs.emprunteurNom }}</span>
        </label>
        <div v-else class="champ">
          <label class="saisie saisie--groupe" :class="{ 'saisie--invalide': erreurs.emprunteur }">
            <span aria-hidden="true">@</span>
            <input v-model="recherche" type="search" :placeholder="t('preter.pseudoMembre')" :aria-label="t('preter.pseudoMembre')" autocomplete="off" />
          </label>
          <ul v-if="suggestions.length" class="suggestions">
            <li v-for="membre in suggestions" :key="membre.id">
              <button type="button" class="suggestion" @click="choisir(membre)">
                <AvatarMembre :membre="membre" :taille="32" />
                <span class="suggestion__pseudo">{{ membre.pseudo }}</span>
                <span v-if="membre.nomComplet" class="suggestion__nom">{{ membre.nomComplet }}</span>
              </button>
            </li>
          </ul>
          <span v-if="membreChoisi" class="choisi">{{ t('article.preteA', { nom: `@${membreChoisi.pseudo}` }) }}</span>
          <span v-if="erreurs.emprunteur" class="champ__erreur">{{ erreurs.emprunteur }}</span>
        </div>

        <div class="dates">
          <label class="champ">
            <span class="champ__libelle">{{ t('prets.preteLe') }}</span>
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
          <textarea v-model="valeurs.note" class="saisie saisie--zone" rows="2" maxlength="500" :placeholder="t('preter.exempleNote')"></textarea>
        </label>

        <button type="submit" class="bouton-principal" :disabled="envoi">{{ t('preter.preterArticle') }}</button>
      </form>
    </main>
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.entete__titre {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.article {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.article__vignette {
  position: relative;
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  border-radius: 12px;
  overflow: hidden;
}

.article__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.article__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.article__titre {
  font-size: 15px;
  font-weight: 800;
}

.article__infos {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.champ {
  gap: 6px;
  min-width: 0;
}

.champ .saisie {
  height: 50px;
}

.champ .saisie--zone {
  height: auto;
  padding: 12px 16px;
  resize: vertical;
  line-height: 1.5;
}

.saisie--groupe {
  gap: 6px;
  color: var(--couleur-texte-secondaire);
  font-weight: 700;
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

.suggestions {
  margin: 0;
  padding: 6px;
  list-style: none;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
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

.suggestion__nom {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
}

.choisi {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-primaire);
}

.dates {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
</style>
