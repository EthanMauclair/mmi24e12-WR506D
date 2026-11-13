<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { fondVignette } from '@/utils/couleurs'
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
    <div v-else-if="!conversation" class="erreur">
      <p class="message-erreur" role="alert">{{ erreur }}</p>
      <RouterLink :to="{ name: 'messages' }">{{ t('messages.retour') }}</RouterLink>
    </div>

    <template v-else>
      <header class="entete">
        <RouterLink :to="{ name: 'messages' }" class="bouton-icone" :aria-label="t('messages.retour')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <RouterLink :to="{ name: 'membre', params: { pseudo: conversation.interlocuteur.pseudo } }" class="interlocuteur">
          <AvatarMembre :membre="conversation.interlocuteur" :taille="40" />
          <span class="interlocuteur__textes">
            <span class="interlocuteur__pseudo">{{ conversation.interlocuteur.pseudo }}</span>
            <span class="interlocuteur__role">{{ t(conversation.estVendeur ? 'messages.acheteurInteresse' : 'messages.vendeur') }}</span>
          </span>
        </RouterLink>
      </header>

      <RouterLink :to="{ name: 'annonce', params: { id: conversation.annonce.id } }" class="annonce">
        <span class="annonce__vignette" :style="{ background: fondVignette(conversation.annonce.article.couleur) }">
          <img v-if="conversation.annonce.article.photo" :src="conversation.annonce.article.photo" alt="" class="annonce__photo" />
        </span>
        <span class="annonce__textes">
          <span class="annonce__titre">{{ conversation.annonce.article.titre }} · {{ conversation.annonce.article.infos }}</span>
          <span class="annonce__prix">{{ formatPrix(conversation.annonce.prix) }}</span>
        </span>
        <span class="annonce__voir">{{ t('commun.voir') }}</span>
      </RouterLink>

      <main ref="fil" class="fil" aria-live="polite">
        <p class="avertissement">{{ t('annonce.avertissement') }}</p>
        <div v-for="message in conversation.messages" :key="message.id" class="message" :class="{ 'message--moi': message.deMoi }">
          <span class="message__bulle">{{ message.contenu }}</span>
          <span class="message__heure">{{ heure(message.envoyeAt) }}<template v-if="message.deMoi && message.lu"> · {{ t('messages.lu') }}</template></span>
        </div>
      </main>

      <form class="saisie-message" @submit.prevent="envoyer">
        <p v-if="erreurEnvoi" class="message-erreur" role="alert">{{ erreurEnvoi }}</p>
        <div class="saisie-message__ligne">
          <label class="saisie-message__champ">
            <input v-model="brouillon" type="text" :aria-label="t('messages.votreMessage')" :placeholder="t('messages.ecrire')" maxlength="2000" />
          </label>
          <button type="submit" class="saisie-message__envoyer" :aria-label="t('messages.envoyer')" :disabled="envoi || !brouillon.trim()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4z" /></svg>
          </button>
        </div>
      </form>
    </template>
  </div>
</template>

<style scoped>
/* L'écran tient dans la hauteur de la fenêtre : seuls les messages défilent */
.ecran--conversation {
  height: 100dvh;
}

.erreur {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 24px 20px;
}

.entete {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px 12px;
  background: var(--couleur-surface);
  border-bottom: 1px solid var(--couleur-bordure);
}

.interlocuteur {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--couleur-texte);
}

.interlocuteur__textes {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.interlocuteur__pseudo {
  font-size: 15px;
  font-weight: 800;
}

.interlocuteur__role {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.annonce {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 14px 20px 0;
  padding: 10px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  color: var(--couleur-texte);
}

.annonce__vignette {
  position: relative;
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 10px;
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.annonce__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.annonce__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.annonce__titre {
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.annonce__prix {
  font-size: 15px;
  font-weight: 800;
  color: var(--couleur-primaire);
}

.annonce__voir {
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-primaire);
  padding-right: 4px;
}

.fil {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.avertissement {
  margin: 0 0 6px;
  align-self: center;
  max-width: 290px;
  text-align: center;
  font-size: 12px;
  line-height: 1.45;
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
  max-width: 260px;
  padding: 10px 14px;
  border-radius: 18px 18px 18px 6px;
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font-size: 14px;
  line-height: 1.45;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.message--moi .message__bulle {
  border-radius: 18px 18px 6px 18px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.message__heure {
  font-size: 11px;
  color: var(--couleur-texte-secondaire);
  padding: 0 4px;
}

.saisie-message {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 20px 22px;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.saisie-message__ligne {
  display: flex;
  align-items: center;
  gap: 10px;
}

.saisie-message__champ {
  flex: 1;
  display: flex;
  align-items: center;
  height: 48px;
  padding: 0 16px;
  border-radius: 999px;
  background: var(--couleur-fond);
  border: 1px solid var(--couleur-bordure);
}

.saisie-message__champ input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--couleur-texte);
}

.saisie-message__envoyer {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border-radius: 50%;
  border: 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.saisie-message__envoyer:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
