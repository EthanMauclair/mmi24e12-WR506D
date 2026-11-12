<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { ilYA } from '@/utils/dates'

const props = defineProps({
  // Chemin de l'élément commenté dans l'API : '/articles/12' ou '/tenues/5'
  source: { type: String, required: true },
  peutCommenter: { type: Boolean, default: false },
  // Pseudo de l'auteur de l'article ou de la tenue, pour expliquer qui peut commenter
  auteur: { type: String, required: true },
})
const emit = defineEmits(['nombre'])

const route = useRoute()
const session = useSessionStore()

const commentaires = ref([])
const chargement = ref(true)
const nouveau = ref('')
const envoi = ref(false)
const erreur = ref('')

function changer(liste) {
  commentaires.value = liste
  emit('nombre', liste.length)
}

async function publier() {
  erreur.value = ''
  envoi.value = true
  try {
    const commentaire = await api(`${props.source}/commentaires`, { methode: 'POST', corps: { contenu: nouveau.value } })
    changer([...commentaires.value, commentaire])
    nouveau.value = ''
  } catch (e) {
    erreur.value = e.violations.contenu ?? e.message
  } finally {
    envoi.value = false
  }
}

async function supprimer(commentaire) {
  erreur.value = ''
  try {
    await api(`/commentaires/${commentaire.id}`, { methode: 'DELETE' })
    changer(commentaires.value.filter((autre) => autre.id !== commentaire.id))
  } catch (e) {
    erreur.value = e.message
  }
}

onMounted(async () => {
  try {
    changer(await api(`${props.source}/commentaires`))
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <section class="commentaires" aria-labelledby="titre-commentaires">
    <h2 id="titre-commentaires" class="commentaires__titre">
      Commentaires<template v-if="commentaires.length"> · {{ commentaires.length }}</template>
    </h2>
    <p v-if="chargement" class="commentaires__vide">Chargement…</p>
    <ul v-else-if="commentaires.length" class="commentaires__liste">
      <li v-for="commentaire in commentaires" :key="commentaire.id" class="commentaire">
        <RouterLink :to="{ name: 'membre', params: { pseudo: commentaire.auteur.pseudo } }" :aria-label="commentaire.auteur.pseudo">
          <AvatarMembre :membre="commentaire.auteur" :taille="32" />
        </RouterLink>
        <div class="commentaire__corps">
          <p class="commentaire__texte">
            <RouterLink :to="{ name: 'membre', params: { pseudo: commentaire.auteur.pseudo } }" class="commentaire__auteur">{{ commentaire.auteur.pseudo }}</RouterLink>
            {{ commentaire.contenu }}
          </p>
          <span class="commentaire__date">{{ ilYA(commentaire.createdAt) }}</span>
        </div>
        <button
          v-if="commentaire.peutSupprimer"
          type="button"
          class="commentaire__supprimer"
          :aria-label="`Supprimer le commentaire de ${commentaire.auteur.pseudo}`"
          @click="supprimer(commentaire)"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
        </button>
      </li>
    </ul>
    <p v-else class="commentaires__vide">Aucun commentaire pour le moment.</p>

    <form v-if="peutCommenter" class="nouveau-commentaire" @submit.prevent="publier">
      <textarea
        v-model="nouveau"
        class="saisie nouveau-commentaire__saisie"
        rows="1"
        maxlength="1000"
        placeholder="Ajouter un commentaire…"
        aria-label="Votre commentaire"
      ></textarea>
      <button type="submit" class="nouveau-commentaire__envoyer" :disabled="envoi || !nouveau.trim()">Publier</button>
    </form>
    <p v-else-if="!session.utilisateur" class="commentaires__aide">
      <RouterLink :to="{ name: 'connexion', query: { redirection: route.fullPath } }">Connectez-vous</RouterLink>
      pour suivre {{ auteur }}.
    </p>
    <p v-else class="commentaires__aide">Seuls les amis de {{ auteur }} peuvent commenter.</p>
    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
  </section>
</template>

<style scoped>
.commentaires {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 8px;
}

.commentaires__titre {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.commentaires__liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.commentaire {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.commentaire__corps {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.commentaire__texte {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.commentaire__auteur {
  font-weight: 800;
  color: var(--couleur-texte);
}

.commentaire__date {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.commentaire__supprimer {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--couleur-texte-secondaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.commentaires__vide,
.commentaires__aide {
  margin: 0;
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
}

.nouveau-commentaire {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.nouveau-commentaire__saisie {
  flex: 1;
  min-height: 44px;
  padding: 11px 14px;
  resize: vertical;
  line-height: 1.4;
}

.nouveau-commentaire__envoyer {
  height: 44px;
  padding: 0 14px;
  border: 0;
  border-radius: 14px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.nouveau-commentaire__envoyer:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
