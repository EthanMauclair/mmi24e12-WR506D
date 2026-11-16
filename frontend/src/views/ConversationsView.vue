<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { ilYA } from '@/utils/dates'

const { t } = useI18n()

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
    <main class="page">
      <header class="entete">
        <RouterLink :to="{ name: 'marche' }" class="bouton-icone" :aria-label="t('annonce.retourMarche')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t('messages.titre') }}</h1>
      </header>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="conversations.length === 0" class="etat">{{ t('messages.aucune') }}</p>
      <div v-else class="lignes colonne-etroite">
        <RouterLink v-for="conversation in conversations" :key="conversation.id" :to="{ name: 'conversation', params: { id: conversation.id } }" class="ligne">
          <AvatarMembre :membre="conversation.interlocuteur" :taille="44" />
          <span class="ligne__textes">
            <span class="conversation__ligne">
              <b>@{{ conversation.interlocuteur.pseudo }}</b>
              <span class="badge">{{ t(conversation.estVendeur ? 'messages.acheteur' : 'messages.vendeur') }}</span>
            </span>
            <span class="texte-secondaire conversation__dernier" :class="{ 'conversation__dernier--non-lu': conversation.nonLus }">
              {{ conversation.annonce.article.titre }}<template v-if="conversation.dernierMessage"> · <template v-if="conversation.dernierMessage.deMoi">{{ `${t('messages.vous')} ` }}</template>{{ conversation.dernierMessage.contenu }}</template>
            </span>
          </span>
          <span class="conversation__fin">
            <span v-if="conversation.dernierMessage" class="texte-secondaire">{{ ilYA(conversation.dernierMessage.envoyeAt) }}</span>
            <span v-if="conversation.nonLus" class="badge badge--plein" :aria-label="t('annonce.nonLus', conversation.nonLus)">{{ conversation.nonLus }}</span>
          </span>
        </RouterLink>
      </div>
    </main>

    <BarreNavigation actif="marche" />
  </div>
</template>

<style scoped>
.conversation__ligne {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.conversation__dernier {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conversation__dernier--non-lu {
  color: var(--couleur-texte);
  font-weight: 700;
}

.conversation__fin {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

@media (min-width: 1024px) {
  .lignes {
    padding: 0 16px;
  }
}
</style>
