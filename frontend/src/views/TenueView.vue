<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import BlocCommentaires from '@/components/BlocCommentaires.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { formatDate } from '@/utils/dates'
import { imageTenue } from '@/utils/imageTenue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const tenue = ref(null)
const erreur = ref('')
const erreurAction = ref('')
const messagePartage = ref('')

const lien = computed(() => new URL(router.resolve({ name: 'tenue', params: { id: tenue.value.id } }).href, window.location.origin).href)
const peutPartagerFichier = typeof navigator.canShare === 'function'

async function charger(id) {
  erreur.value = ''
  try {
    tenue.value = await api(`/tenues/${id}`)
  } catch (e) {
    erreur.value = e.statut === 404 ? t('tenues.inexistante') : e.message
  }
}

// Le cœur change tout de suite, puis revient en arrière si l'API refuse
async function basculerLike() {
  if (!session.utilisateur) {
    router.push({ name: 'connexion', query: { redirection: route.fullPath } })
    return
  }
  const aime = !tenue.value.aime
  Object.assign(tenue.value, { aime, nbLikes: tenue.value.nbLikes + (aime ? 1 : -1) })
  erreurAction.value = ''
  try {
    Object.assign(tenue.value, await api(`/tenues/${tenue.value.id}/like`, { methode: aime ? 'PUT' : 'DELETE' }))
  } catch (e) {
    Object.assign(tenue.value, { aime: !aime, nbLikes: tenue.value.nbLikes + (aime ? -1 : 1) })
    erreurAction.value = e.message
  }
}

/**
 * Instagram n'accepte pas de publication depuis un site : on génère une image de la tenue (1080 × 1350),
 * que le téléphone partage (vers Instagram, une story…) ou que l'ordinateur télécharge.
 */
async function partagerImage() {
  messagePartage.value = t('carteTenue.preparation')
  const fichier = new File([await imageTenue(tenue.value)], `tenue-${tenue.value.id}.png`, { type: 'image/png' })
  if (navigator.canShare?.({ files: [fichier] })) {
    try {
      await navigator.share({ files: [fichier], title: tenue.value.titre, text: `${tenue.value.titre} · ${lien.value}` })
    } catch {
      // Partage annulé par l'utilisateur
    }
    messagePartage.value = ''
    return
  }
  const adresse = URL.createObjectURL(fichier)
  Object.assign(document.createElement('a'), { href: adresse, download: fichier.name }).click()
  URL.revokeObjectURL(adresse)
  messagePartage.value = t('carteTenue.telechargee')
}

async function copierLien() {
  try {
    await navigator.clipboard.writeText(lien.value)
    messagePartage.value = t('carteTenue.lienCopie')
  } catch {
    messagePartage.value = lien.value
  }
}

// Bouton « partager » de l'en-tête sur téléphone : partage natif du lien, sinon copie
async function partagerLien() {
  if (navigator.share) {
    try {
      await navigator.share({ title: tenue.value.titre, url: lien.value })
    } catch {
      // Partage annulé par l'utilisateur
    }
    return
  }
  copierLien()
}

watch(() => route.params.id, charger, { immediate: true })
</script>

<template>
  <div class="ecran">
    <main class="page">
      <div v-if="erreur" class="pile">
        <p class="message-erreur" role="alert">{{ erreur }}</p>
        <RouterLink :to="{ name: 'tenues' }" class="lien">{{ t('tenues.voirFil') }}</RouterLink>
      </div>
      <p v-else-if="!tenue" class="etat">{{ t('tenues.chargementTenue') }}</p>

      <template v-else>
        <header class="entete">
          <button type="button" class="bouton-icone" :aria-label="t('commun.retour')" @click="router.back()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </button>
          <h1 class="titre-page">{{ tenue.titre }}</h1>
          <template v-if="!tenue.estPrivee">
            <button type="button" class="bouton-icone seulement-mobile" :aria-label="t('tenues.partagerLien')" @click="partagerLien">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10" /></svg>
            </button>
            <button type="button" class="bouton bouton--contour bouton--petit seulement-ordinateur" @click="partagerImage">{{ t(peutPartagerFichier ? 'tenues.instagram' : 'tenues.instagramTelecharger') }}</button>
            <button type="button" class="bouton bouton--contour bouton--petit seulement-ordinateur" @click="copierLien">{{ t('carteTenue.copierLien') }}</button>
          </template>
        </header>
        <p v-if="messagePartage" class="bandeau-info" role="status">{{ messagePartage }}</p>

        <div class="deux-colonnes">
          <div class="pieces">
            <RouterLink v-for="article in tenue.articles.slice(0, 3)" :key="article.id" :to="{ name: 'article', params: { id: article.id } }" class="piece" :aria-label="article.titre">
              <VignetteArticle class="piece__vignette" :photo="article.photo" :couleur="article.couleur" :icone="36" />
            </RouterLink>
          </div>

          <div class="details">
            <div class="auteur">
              <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" :aria-label="tenue.createur.pseudo">
                <AvatarMembre :membre="tenue.createur" :taille="32" />
              </RouterLink>
              <span class="entete__textes">
                <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" class="auteur__pseudo">{{ tenue.createur.pseudo }}</RouterLink>
                <span class="texte-secondaire">{{ formatDate(new Date(tenue.createdAt), { day: 'numeric', month: 'short' }) }}<template v-if="tenue.estPrivee"> · {{ t('carteTenue.privee') }}</template></span>
              </span>
              <button
                type="button"
                class="bouton bouton--contour bouton--petit"
                :class="{ 'bouton--aime': tenue.aime }"
                :aria-pressed="tenue.aime"
                :aria-label="t('carteTenue.aime')"
                :disabled="session.utilisateur && !tenue.peutAimer"
                @click="basculerLike"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" :fill="tenue.aime ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></svg>
                {{ tenue.nbLikes }}
              </button>
            </div>
            <p v-if="tenue.description" class="texte-secondaire">{{ tenue.description }}</p>
            <p v-if="erreurAction" class="message-erreur" role="alert">{{ erreurAction }}</p>

            <div class="lignes">
              <RouterLink v-for="article in tenue.articles" :key="article.id" :to="{ name: 'article', params: { id: article.id } }" class="ligne ligne--compacte">
                <VignetteArticle class="ligne__vignette" :photo="article.photo" :couleur="article.couleur" :icone="18" />
                <span class="ligne__textes">{{ article.titre }}</span>
                <svg class="ligne__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
              </RouterLink>
            </div>

            <BlocCommentaires
              :key="tenue.id"
              :source="`/tenues/${tenue.id}`"
              :peut-commenter="tenue.peutCommenter"
              :auteur="tenue.createur.pseudo"
              @nombre="tenue.nbCommentaires = $event"
            />
          </div>
        </div>

        <!-- Téléphone : partage en bas de l'écran -->
        <div v-if="!tenue.estPrivee" class="barre-actions barre-actions--dans-page seulement-mobile">
          <button type="button" class="bouton bouton--flexible" @click="partagerImage">{{ t(peutPartagerFichier ? 'tenues.instagram' : 'tenues.instagramTelecharger') }}</button>
          <button type="button" class="bouton bouton--contour bouton--flexible" @click="copierLien">{{ t('carteTenue.copierLien') }}</button>
        </div>
      </template>
    </main>

    <BarreNavigation actif="tenues" ordinateur-seulement />
  </div>
</template>

<style scoped>
.pieces {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.piece {
  display: block;
}

.piece__vignette {
  width: 100%;
  aspect-ratio: 3 / 4;
}

.details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.auteur {
  display: flex;
  align-items: center;
  gap: 12px;
}

.auteur__pseudo {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.bouton--aime {
  background: var(--couleur-primaire-pale);
}

.ligne--compacte {
  padding: 8px 0;
}

.ligne__vignette {
  width: 36px;
  height: 36px;
}

.barre-actions--dans-page {
  margin-top: auto;
}

@media (min-width: 1024px) {
  .pieces {
    gap: 8px;
  }
}
</style>
