<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
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

const { t } = useI18n()
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
    <div class="ligne-titre">
      <h2 id="titre-commentaires" class="titre-section">
        {{ t('commentaires.titre') }}<template v-if="commentaires.length"> · {{ commentaires.length }}</template>
      </h2>
      <span class="texte-secondaire">{{ t('commentaires.amisUniquement') }}</span>
    </div>

    <p v-if="chargement" class="texte-secondaire">{{ t('commun.chargement') }}</p>
    <ul v-else-if="commentaires.length" class="commentaires__liste">
      <li v-for="commentaire in commentaires" :key="commentaire.id" class="commentaire">
        <RouterLink :to="{ name: 'membre', params: { pseudo: commentaire.auteur.pseudo } }" :aria-label="commentaire.auteur.pseudo">
          <AvatarMembre :membre="commentaire.auteur" :taille="32" />
        </RouterLink>
        <div class="commentaire__corps">
          <span class="texte-secondaire">
            <RouterLink :to="{ name: 'membre', params: { pseudo: commentaire.auteur.pseudo } }" class="commentaire__auteur">{{ commentaire.auteur.pseudo }}</RouterLink>
            · {{ ilYA(commentaire.createdAt) }}
          </span>
          <p class="commentaire__texte">{{ commentaire.contenu }}</p>
        </div>
        <button
          v-if="commentaire.peutSupprimer"
          type="button"
          class="commentaire__supprimer"
          :aria-label="t('commentaires.supprimer', { pseudo: commentaire.auteur.pseudo })"
          @click="supprimer(commentaire)"
        >
          ✕
        </button>
      </li>
    </ul>
    <p v-else class="texte-secondaire">{{ t('commentaires.aucun') }}</p>

    <form v-if="peutCommenter" class="nouveau-commentaire" @submit.prevent="publier">
      <input v-model="nouveau" class="saisie nouveau-commentaire__saisie" type="text" maxlength="1000" :placeholder="t('commentaires.ajouter')" :aria-label="t('commentaires.votre')" />
      <button type="submit" class="bouton bouton--petit" :disabled="envoi || !nouveau.trim()">{{ t('commun.publier') }}</button>
    </form>
    <p v-else-if="!session.utilisateur" class="texte-secondaire">
      <i18n-t keypath="commentaires.connectezVous" scope="global">
        <template #lien><RouterLink :to="{ name: 'connexion', query: { redirection: route.fullPath } }" class="lien">{{ t('commentaires.seConnecter') }}</RouterLink></template>
        <template #pseudo>{{ auteur }}</template>
      </i18n-t>
    </p>
    <p v-else class="texte-secondaire">{{ t('commentaires.reserveAmis', { pseudo: auteur }) }}</p>
    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
  </section>
</template>

<style scoped>
.commentaires {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.commentaires__liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.commentaire {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.commentaire__corps {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.commentaire__auteur {
  font-weight: 700;
  color: var(--couleur-texte);
}

.commentaire__texte {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.commentaire__supprimer {
  flex-shrink: 0;
  padding: 2px 4px;
  border: 0;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
}

.commentaire__supprimer:hover {
  color: var(--couleur-erreur);
}

.nouveau-commentaire {
  display: flex;
  align-items: center;
  gap: 8px;
}

.nouveau-commentaire__saisie {
  flex: 1;
  min-width: 0;
  height: 44px;
}
</style>
