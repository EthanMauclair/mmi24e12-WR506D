<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { formatDate } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

// Les nouveaux messages sont récupérés toutes les 5 secondes tant que la conversation est ouverte
const INTERVALLE = 5000
const FORMAT_HEURE = { hour: '2-digit', minute: '2-digit' }
const FORMAT_JOUR = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }

const { t } = useI18n()
const route = useRoute()

const conversation = ref(null)
const erreur = ref('')
const brouillon = ref('')
const envoi = ref(false)
const erreurEnvoi = ref('')
const fil = ref(null)

let minuterie = null

function heure(dateIso) {
  const date = new Date(dateIso)
  return formatDate(date, date.toDateString() === new Date().toDateString() ? FORMAT_HEURE : FORMAT_JOUR)
}

// « 18 € · Réservée »
function resumeAnnonce(annonce) {
  return [formatPrix(annonce.prix), annonce.statut ? t(`annonce.statuts.${annonce.statut}`) : null].filter(Boolean).join(' · ')
}

async function descendre() {
  await nextTick()
  fil.value?.scrollTo({ top: fil.value.scrollHeight })
}

async function charger() {
  try {
    const nombreAvant = conversation.value?.messages.length
    conversation.value = await api(`/conversations/${route.params.id}`)
    erreur.value = ''
    if (conversation.value.messages.length !== nombreAvant) {
      descendre()
    }
  } catch (e) {
    erreur.value = e.message
  }
}

async function envoyer() {
  erreurEnvoi.value = ''
  envoi.value = true
  try {
    const message = await api(`/conversations/${conversation.value.id}/messages`, { methode: 'POST', corps: { contenu: brouillon.value } })
    conversation.value.messages.push(message)
    brouillon.value = ''
    descendre()
  } catch (e) {
    erreurEnvoi.value = e.violations.contenu ?? e.message
  } finally {
    envoi.value = false
  }
}

watch(
  () => route.params.id,
  async () => {
    clearInterval(minuterie)
    conversation.value = null
    await charger()
    minuterie = setInterval(charger, INTERVALLE)
  },
  { immediate: true },
)
onBeforeUnmount(() => clearInterval(minuterie))
</script>

<template>
  <div class="ecran ecran--conversation">
    <p v-if="!conversation && !erreur" class="etat">{{ t('messages.chargement') }}</p>
    <div v-else-if="!conversation" class="page">
      <p class="message-erreur" role="alert">{{ erreur }}</p>
      <RouterLink :to="{ name: 'messages' }" class="lien">{{ t('messages.retour') }}</RouterLink>
    </div>

    <main v-else class="page conversation">
      <header class="entete">
        <RouterLink :to="{ name: 'messages' }" class="bouton-icone" :aria-label="t('messages.retour')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <RouterLink :to="{ name: 'membre', params: { pseudo: conversation.interlocuteur.pseudo } }" class="interlocuteur">
          <AvatarMembre :membre="conversation.interlocuteur" :taille="32" />
          <span class="entete__textes">
            <b>@{{ conversation.interlocuteur.pseudo }}</b>
            <span class="texte-secondaire">{{ t(conversation.estVendeur ? 'messages.acheteurInteresse' : 'messages.vendeur') }}</span>
          </span>
        </RouterLink>
      </header>

      <div class="disposition">
        <!-- Rappel de l'annonce : carte en haut sur téléphone, colonne de droite sur ordinateur -->
        <RouterLink :to="{ name: 'annonce', params: { id: conversation.annonce.id } }" class="carte annonce">
          <VignetteArticle class="annonce__vignette" :photo="conversation.annonce.article.photo" :couleur="conversation.annonce.article.couleur" :icone="22" />
          <span class="ligne__textes">
            <b class="annonce__titre">{{ conversation.annonce.article.titre }}</b>
            <span class="texte-secondaire">{{ resumeAnnonce(conversation.annonce) }}</span>
          </span>
          <svg class="ligne__chevron seulement-mobile" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
          <span class="bouton bouton--contour bouton--petit seulement-ordinateur">{{ t('article.voirAnnonce') }}</span>
        </RouterLink>

        <div class="discussion">
          <div ref="fil" class="fil" aria-live="polite">
            <p class="avertissement">{{ t('annonce.avertissement') }}</p>
            <div v-for="message in conversation.messages" :key="message.id" class="message" :class="{ 'message--moi': message.deMoi }">
              <span class="message__bulle">{{ message.contenu }}</span>
              <span class="message__heure">{{ heure(message.envoyeAt) }}<template v-if="message.deMoi && message.lu"> · {{ t('messages.lu') }}</template></span>
            </div>
          </div>

          <form class="barre-actions saisie-message" @submit.prevent="envoyer">
            <p v-if="erreurEnvoi" class="message-erreur" role="alert">{{ erreurEnvoi }}</p>
            <div class="saisie-message__ligne">
              <input v-model="brouillon" class="saisie saisie-message__champ" type="text" :aria-label="t('messages.votreMessage')" :placeholder="t('messages.ecrire')" maxlength="2000" />
              <button type="submit" class="bouton" :disabled="envoi || !brouillon.trim()">{{ t('messages.envoyer') }}</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <BarreNavigation actif="marche" ordinateur-seulement />
  </div>
</template>

<style scoped>
/* L'écran tient dans la hauteur de la fenêtre : seuls les messages défilent */
.ecran--conversation {
  height: 100dvh;
}

.conversation {
  min-height: 0;
  padding-bottom: 0;
  gap: 10px;
}

.interlocuteur {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--couleur-texte);
}

.interlocuteur:hover {
  color: var(--couleur-texte);
}

.disposition {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.annonce {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  color: var(--couleur-texte);
}

.annonce:hover {
  border-color: var(--couleur-primaire);
  color: var(--couleur-texte);
}

.annonce__vignette {
  width: 44px;
  height: 44px;
}

.annonce__titre {
  font-size: 13px;
}

.discussion {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.fil {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 10px;
}

.avertissement {
  margin: 0 0 6px;
  align-self: center;
  max-width: 300px;
  text-align: center;
  font-size: 12px;
  line-height: 1.5;
  color: var(--couleur-texte-secondaire);
}

.message {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
}

.message--moi {
  align-items: flex-end;
}

.message__bulle {
  /* Trois quarts de la largeur : 290 px environ sur téléphone, plus sur grand écran */
  max-width: min(76%, 520px);
  padding: 10px 12px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font-size: 13px;
  line-height: 1.4;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.message--moi .message__bulle {
  background: var(--couleur-primaire);
  border-color: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.message__heure {
  padding: 0 4px;
  font-size: 11px;
  color: var(--couleur-texte-secondaire);
}

.saisie-message {
  margin: 0 -16px;
  flex-direction: column;
}

.saisie-message__ligne {
  display: flex;
  gap: 10px;
}

.saisie-message__champ {
  flex: 1;
  min-width: 0;
}

/* Ordinateur : la conversation dans une carte à gauche, l'annonce rappelée à droite */
@media (min-width: 1024px) {
  .conversation {
    padding-bottom: 28px;
  }

  .disposition {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    grid-template-rows: minmax(0, 1fr);
    gap: 28px;
  }

  .annonce {
    grid-column: 2;
    grid-row: 1;
    align-self: start;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    padding: 12px;
  }

  .annonce__vignette {
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
  }

  .annonce__titre {
    font-size: 15px;
  }

  .annonce .bouton {
    align-self: flex-start;
  }

  .discussion {
    grid-column: 1;
    grid-row: 1;
    padding: 16px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 14px;
    background: var(--couleur-surface);
  }

  .saisie-message {
    margin: 0;
  }
}
</style>
