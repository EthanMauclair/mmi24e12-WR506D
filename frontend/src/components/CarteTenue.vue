<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { ilYA } from '@/utils/dates'

/**
 * Tenue dans le fil ou sur un profil : auteur, trois premières pièces, titre, j'aime,
 * nombre de commentaires et aperçu du dernier.
 */
const props = defineProps({
  tenue: { type: Object, required: true },
})
const emit = defineEmits(['supprimee'])

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const menu = ref(false)
const erreur = ref('')

const pieces = computed(() => props.tenue.articles.slice(0, 3))
const lienTenue = computed(() => ({ name: 'tenue', params: { id: props.tenue.id } }))

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
  if (!window.confirm(t('carteTenue.confirmerSuppression', { titre: props.tenue.titre }))) {
    return
  }
  try {
    await api(`/tenues/${props.tenue.id}`, { methode: 'DELETE' })
    emit('supprimee', props.tenue)
  } catch (e) {
    erreur.value = e.message
  }
}
</script>

<template>
  <article class="carte carte-tenue">
    <header class="carte-tenue__entete">
      <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" :aria-label="tenue.createur.pseudo">
        <AvatarMembre :membre="tenue.createur" :taille="32" />
      </RouterLink>
      <span class="entete__textes">
        <RouterLink :to="{ name: 'membre', params: { pseudo: tenue.createur.pseudo } }" class="carte-tenue__pseudo">{{ tenue.createur.pseudo }}</RouterLink>
        <span class="texte-secondaire">{{ ilYA(tenue.createdAt) }}<template v-if="tenue.estPrivee"> · {{ t('carteTenue.privee') }}</template></span>
      </span>
      <div v-if="tenue.peutSupprimer" class="carte-tenue__options">
        <button type="button" class="carte-tenue__bouton-options" :aria-label="t('carteTenue.options')" :aria-expanded="menu" @click="menu = !menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="19" cy="12" r="2" /></svg>
        </button>
        <div v-if="menu" class="menu">
          <button type="button" class="menu__element" @click="supprimer">{{ t('carteTenue.supprimer') }}</button>
        </div>
      </div>
    </header>

    <RouterLink :to="lienTenue" class="pieces" :aria-label="tenue.titre">
      <VignetteArticle v-for="article in pieces" :key="article.id" class="piece" :photo="article.photo" :couleur="article.couleur" :icone="32" />
    </RouterLink>

    <RouterLink :to="lienTenue" class="carte-tenue__titre">{{ tenue.titre }}</RouterLink>

    <div class="carte-tenue__actions">
      <button
        type="button"
        class="action"
        :class="{ 'action--aime': tenue.aime }"
        :aria-pressed="tenue.aime"
        :aria-label="t('carteTenue.aime')"
        :disabled="session.utilisateur && !tenue.peutAimer"
        @click="basculerLike"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" :fill="tenue.aime ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></svg>
        {{ tenue.nbLikes }}
      </button>
      <RouterLink v-if="tenue.nbCommentaires" :to="lienTenue" class="texte-secondaire">{{ t('carteTenue.commentaires', tenue.nbCommentaires) }}</RouterLink>
    </div>

    <p v-if="tenue.description" class="texte-secondaire">
      <b class="carte-tenue__auteur">{{ tenue.createur.pseudo }}</b> {{ tenue.description }}
    </p>
    <p v-if="tenue.dernierCommentaire" class="texte-secondaire">
      <b class="carte-tenue__auteur">{{ tenue.dernierCommentaire.auteur.pseudo }}</b> : {{ tenue.dernierCommentaire.contenu }}
    </p>
    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
  </article>
</template>

<style scoped>
.carte-tenue {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.carte-tenue__entete {
  display: flex;
  align-items: center;
  gap: 10px;
}

.carte-tenue__pseudo {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.carte-tenue__options {
  position: relative;
}

.carte-tenue__bouton-options {
  width: 36px;
  height: 36px;
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
  top: 38px;
  z-index: 2;
  min-width: 180px;
  padding: 6px;
  border-radius: 12px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  box-shadow: var(--ombre-menu);
}

.menu__element {
  width: 100%;
  padding: 10px 12px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--couleur-erreur);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.menu__element:hover {
  background: var(--couleur-erreur-fond);
}

.pieces {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}

.piece {
  width: 100%;
  aspect-ratio: 3 / 4;
}

.carte-tenue__titre {
  font-weight: 700;
  color: var(--couleur-texte);
}

.carte-tenue__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}

.action--aime {
  color: var(--couleur-primaire);
}

.action:disabled {
  cursor: default;
}

.carte-tenue__auteur {
  color: var(--couleur-texte);
}
</style>
