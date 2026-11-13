<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { ilYA } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()
const router = useRouter()

const conversations = ref([])
const chargement = ref(true)
const erreur = ref('')

onMounted(async () => {
  try {
    conversations.value = (await api('/conversations')).conversations
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <button type="button" class="bouton-icone" :aria-label="t('commun.retour')" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <h1 class="entete__titre">{{ t('messages.titre') }}</h1>
      </header>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="conversations.length === 0" class="etat">
        {{ t('messages.aucune') }}
      </p>
      <ul v-else class="liste">
        <li v-for="conversation in conversations" :key="conversation.id">
          <RouterLink :to="{ name: 'conversation', params: { id: conversation.id } }" class="conversation">
            <AvatarMembre :membre="conversation.interlocuteur" :taille="48" />
            <span class="conversation__textes">
              <span class="conversation__ligne">
                <span class="conversation__pseudo">{{ conversation.interlocuteur.pseudo }}</span>
                <span v-if="conversation.dernierMessage" class="conversation__date">{{ ilYA(conversation.dernierMessage.envoyeAt) }}</span>
              </span>
              <span class="conversation__annonce">
                {{ t(conversation.estVendeur ? 'messages.acheteur' : 'messages.vendeur') }} · {{ conversation.annonce.article.titre }} · {{ formatPrix(conversation.annonce.prix) }}
              </span>
              <span v-if="conversation.dernierMessage" class="conversation__dernier" :class="{ 'conversation__dernier--non-lu': conversation.nonLus }">
                <template v-if="conversation.dernierMessage.deMoi">{{ `${t('messages.vous')} ` }}</template>{{ conversation.dernierMessage.contenu }}
              </span>
            </span>
            <span v-if="conversation.nonLus" class="conversation__non-lus" :aria-label="t('annonce.nonLus', conversation.nonLus)">{{ conversation.nonLus }}</span>
          </RouterLink>
        </li>
      </ul>
    </main>

    <BarreNavigation actif="marche" />
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
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

.liste {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.conversation {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--couleur-texte);
}

.conversation__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.conversation__ligne {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.conversation__pseudo {
  font-size: 15px;
  font-weight: 800;
}

.conversation__date {
  flex-shrink: 0;
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.conversation__annonce {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--couleur-primaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation__dernier {
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation__dernier--non-lu {
  color: var(--couleur-texte);
  font-weight: 700;
}

.conversation__non-lus {
  flex-shrink: 0;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  box-sizing: border-box;
  border-radius: 999px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
