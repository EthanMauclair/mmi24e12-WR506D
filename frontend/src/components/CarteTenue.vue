<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'
import { ilYA } from '@/utils/dates'
import { imageTenue } from '@/utils/imageTenue'

const props = defineProps({
  tenue: { type: Object, required: true },
  // Sur la page de la tenue, les commentaires sont affichés en entier : pas d'aperçu
  apercuCommentaires: { type: Boolean, default: true },
})
const emit = defineEmits(['supprimee'])

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const menu = ref(false)
const partage = ref(false)
const messagePartage = ref('')
const erreur = ref('')

const pieces = computed(() => props.tenue.articles.slice(0, 3))
const lienTenue = computed(() => ({ name: 'tenue', params: { id: props.tenue.id } }))
const peutPartagerFichier = typeof navigator.canShare === 'function'

// Le cœur change tout de suite, puis revient en arrière si l'API refuse
async function basculerLike() {
  if (!session.utilisateur) {
    router.push({ name: 'connexion', query: { redirection: route.fullPath } })
    return
  }
  const tenue = props.tenue
  const aime = !tenue.aime
  Object.assign(tenue, { aime, nbLikes: tenue.nbLikes + (aime ? 1 : -1) })
  erreur.value = ''
  try {
    Object.assign(tenue, await api(`/tenues/${tenue.id}/like`, { methode: aime ? 'PUT' : 'DELETE' }))
  } catch (e) {
    Object.assign(tenue, { aime: !aime, nbLikes: tenue.nbLikes + (aime ? -1 : 1) })
    erreur.value = e.message
  }
}

async function supprimer() {
  menu.value = false
  if (!window.confirm(`Supprimer la tenue « ${props.tenue.titre} » ?`)) {
    return
  }
  try {
    await api(`/tenues/${props.tenue.id}`, { methode: 'DELETE' })
    emit('supprimee', props.tenue)
  } catch (e) {
    erreur.value = e.message
  }
}

/**
 * Instagram n'accepte pas de publication depuis un site : on génère une image de la tenue, que le téléphone
 * partage (vers Instagram, une story…) ou que l'ordinateur télécharge, avec le lien de la tenue.
 */
async function partagerImage() {
  messagePartage.value = 'Préparation de l\'image…'
  const fichier = new File([await imageTenue(props.tenue)], `tenue-${props.tenue.id}.png`, { type: 'image/png' })
  const lien = new URL(router.resolve(lienTenue.value).href, window.location.origin).href

  if (navigator.canShare?.({ files: [fichier] })) {
    try {
      await navigator.share({ files: [fichier], title: props.tenue.titre, text: `${props.tenue.titre} · ${lien}` })
      messagePartage.value = ''
    } catch {
      // Partage annulé par l'utilisateur
      messagePartage.value = ''
    }
    return
  }

  const adresse = URL.createObjectURL(fichier)
  const telechargement = Object.assign(document.createElement('a'), { href: adresse, download: fichier.name })
  telechargement.click()
  URL.revokeObjectURL(adresse)
  messagePartage.value = 'Image téléchargée : publiez-la depuis Instagram.'
}

async function copierLien() {
  const lien = new URL(router.resolve(lienTenue.value).href, window.location.origin).href
  try {
    await navigator.clipboard.writeText(lien)
    messagePartage.value = 'Lien copié.'
  } catch {
    messagePartage.value = lien
  }
}
</script>

<template>
  <article class="carte">
    <header class="carte__entete">
      <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" :aria-label="tenue.createur.pseudo">
        <AvatarMembre :membre="tenue.createur" :taille="40" />
      </RouterLink>
      <span class="carte__auteur">
        <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" class="carte__pseudo">{{ tenue.createur.pseudo }}</RouterLink>
        <span class="carte__sous-titre">
          {{ tenue.titre }} · {{ ilYA(tenue.createdAt) }}<template v-if="tenue.estPrivee"> · privée</template>
        </span>
      </span>
      <div v-if="tenue.peutSupprimer" class="carte__options">
        <button type="button" class="carte__bouton-options" aria-label="Plus d'options" :aria-expanded="menu" @click="menu = !menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="19" cy="12" r="2" /></svg>
        </button>
        <div v-if="menu" class="menu">
          <button type="button" class="menu__element" @click="supprimer">Supprimer la tenue</button>
        </div>
      </div>
    </header>

    <div class="pieces" :class="`pieces--${pieces.length}`">
      <RouterLink
        v-for="article in pieces"
        :key="article.id"
        :to="{ name: 'article', params: { id: article.id } }"
        class="piece"
        :class="{ 'piece--claire': estCouleurClaire(article.couleur) }"
        :style="{ background: fondVignette(article.couleur) }"
        :aria-label="article.titre"
      >
        <img v-if="article.photo" :src="article.photo" alt="" class="piece__photo" />
        <svg v-else width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
      </RouterLink>
    </div>

    <div class="carte__actions">
      <button
        type="button"
        class="action"
        :class="{ 'action--aime': tenue.aime }"
        :aria-pressed="tenue.aime"
        aria-label="J'aime"
        :disabled="session.utilisateur && !tenue.peutAimer"
        @click="basculerLike"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" :fill="tenue.aime ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></svg>
        {{ tenue.nbLikes }}
      </button>
      <RouterLink :to="lienTenue" class="action" aria-label="Commentaires">
        <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></svg>
        {{ tenue.nbCommentaires }}
      </RouterLink>
      <span class="carte__espace"></span>
      <button v-if="!tenue.estPrivee" type="button" class="instagram" :aria-expanded="partage" @click="partage = !partage">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" /><path d="m16 6-4-4-4 4M12 2v13" /></svg>
        Instagram
      </button>
    </div>

    <div v-if="partage" class="partage">
      <button type="button" class="partage__choix" @click="partagerImage">
        {{ peutPartagerFichier ? "Partager l'image" : "Télécharger l'image" }}
      </button>
      <button type="button" class="partage__choix" @click="copierLien">Copier le lien</button>
      <span v-if="messagePartage" class="partage__message" role="status">{{ messagePartage }}</span>
    </div>

    <div class="carte__textes">
      <p v-if="tenue.description" class="carte__legende">
        <strong>{{ tenue.createur.pseudo }}</strong> {{ tenue.description }}
      </p>
      <template v-if="apercuCommentaires && tenue.dernierCommentaire">
        <p class="carte__commentaire">
          <strong>{{ tenue.dernierCommentaire.auteur.pseudo }}</strong> {{ tenue.dernierCommentaire.contenu }}
        </p>
        <RouterLink v-if="tenue.nbCommentaires > 1" :to="lienTenue" class="carte__voir">
          Voir les {{ tenue.nbCommentaires }} commentaires
        </RouterLink>
      </template>
      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
    </div>
  </article>
</template>

<style scoped>
.carte {
  display: flex;
  flex-direction: column;
  border-radius: 22px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.carte__entete {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
}

.carte__auteur {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.carte__pseudo {
  font-size: 14px;
  font-weight: 800;
  color: var(--couleur-texte);
}

.carte__sous-titre {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.carte__options {
  position: relative;
}

.carte__bouton-options {
  width: 40px;
  height: 40px;
  border: 0;
  background: none;
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.menu {
  position: absolute;
  right: 0;
  top: 42px;
  z-index: 2;
  min-width: 180px;
  padding: 6px;
  border-radius: 14px;
  background: var(--couleur-surface);
  box-shadow: var(--ombre-menu);
}

.menu__element {
  width: 100%;
  padding: 10px 12px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--couleur-erreur);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

/* Une grande pièce à gauche, les suivantes empilées à droite */
.pieces {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  grid-template-rows: repeat(2, 1fr);
  gap: 3px;
  height: 300px;
}

.pieces--1 {
  grid-template-columns: 1fr;
}

.pieces--1 .piece,
.pieces--2 .piece,
.pieces--3 .piece:first-child {
  grid-row: span 2;
}

.piece {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone-grande);
}

.piece--claire {
  color: var(--couleur-photo-icone-sombre);
}

.piece__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.carte__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 8px 0;
}

.action {
  height: 44px;
  padding: 0 8px;
  border: 0;
  background: none;
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  gap: 6px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.action:disabled {
  opacity: 0.4;
  cursor: default;
}

.action--aime {
  color: var(--couleur-primaire);
}

.carte__espace {
  flex: 1;
}

.instagram {
  height: 36px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  gap: 6px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.partage {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px 16px 0;
}

.partage__choix {
  height: 34px;
  padding: 0 12px;
  border: 0;
  border-radius: 10px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.partage__message {
  width: 100%;
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  overflow-wrap: anywhere;
}

.carte__textes {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 4px 16px 16px;
}

.carte__legende,
.carte__commentaire {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.carte__commentaire {
  font-size: 13.5px;
}

.carte__voir {
  align-self: flex-start;
  padding: 4px 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--couleur-texte-secondaire);
}
</style>
