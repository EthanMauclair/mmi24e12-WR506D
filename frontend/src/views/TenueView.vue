<script setup>
import { ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BlocCommentaires from '@/components/BlocCommentaires.vue'
import CarteTenue from '@/components/CarteTenue.vue'
import { api } from '@/services/api'

const route = useRoute()
const router = useRouter()

const tenue = ref(null)
const erreur = ref('')

async function charger(id) {
  erreur.value = ''
  try {
    tenue.value = await api(`/tenues/${id}`)
  } catch (e) {
    erreur.value = e.statut === 404 ? "Cette tenue n'existe plus." : e.message
  }
}

watch(() => route.params.id, charger, { immediate: true })
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <button type="button" class="bouton-icone" aria-label="Retour" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <h1 class="entete__titre">Tenue</h1>
      </header>

      <div v-if="erreur" class="contenu-erreur">
        <p class="message-erreur" role="alert">{{ erreur }}</p>
        <RouterLink :to="{ name: 'tenues' }">Voir le fil des tenues</RouterLink>
      </div>
      <p v-else-if="!tenue" class="etat">Chargement de la tenue…</p>
      <template v-else>
        <CarteTenue :tenue="tenue" :apercu-commentaires="false" @supprimee="router.replace({ name: 'profil', query: { onglet: 'tenues' } })" />
        <BlocCommentaires
          :key="tenue.id"
          :source="`/tenues/${tenue.id}`"
          :peut-commenter="tenue.peutCommenter"
          :auteur="tenue.createur.pseudo"
          @nombre="tenue.nbCommentaires = $event"
        />
      </template>
    </main>
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

.contenu-erreur {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
