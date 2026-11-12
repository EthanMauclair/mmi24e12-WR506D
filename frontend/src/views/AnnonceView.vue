<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'
import { formatPrix } from '@/utils/prix'

const STATUTS = { EN_LIGNE: 'En ligne', RESERVEE: 'Réservée', VENDUE: 'Vendue', RETIREE: 'Retirée' }

const route = useRoute()
const router = useRouter()

const annonce = ref(null)
const erreur = ref('')
const erreurAction = ref('')
const envoi = ref(false)

const premierMessage = ref('Bonjour ! Est-ce toujours disponible ?')
const prix = ref('')
const vente = ref(false)
const acheteur = ref('')

const estActive = computed(() => ['EN_LIGNE', 'RESERVEE'].includes(annonce.value?.statut))
const maConversation = computed(() => (annonce.value?.estVendeur ? null : annonce.value?.conversations[0]))

async function charger(id) {
  erreur.value = ''
  try {
    annonce.value = await api(`/annonces/${id}`)
    prix.value = String(annonce.value.prix).replace('.', ',')
  } catch (e) {
    erreur.value = e.message
  }
}

async function contacter() {
  erreurAction.value = ''
  envoi.value = true
  try {
    const conversation = await api(`/annonces/${annonce.value.id}/conversations`, { methode: 'POST', corps: { contenu: premierMessage.value } })
    router.push({ name: 'conversation', params: { id: conversation.id } })
  } catch (e) {
    erreurAction.value = e.violations.contenu ?? e.violations.general ?? e.message
  } finally {
    envoi.value = false
  }
}

/**
 * @param {object} changements prix, statut, acheteur
 */
async function modifier(changements, confirmation = null) {
  if (confirmation && !window.confirm(confirmation)) {
    return
  }
  erreurAction.value = ''
  envoi.value = true
  try {
    annonce.value = await api(`/annonces/${annonce.value.id}`, { methode: 'PATCH', corps: changements })
    prix.value = String(annonce.value.prix).replace('.', ',')
    vente.value = false
  } catch (e) {
    erreurAction.value = Object.values(e.violations)[0] ?? e.message
  } finally {
    envoi.value = false
  }
}

watch(() => route.params.id, charger, { immediate: true })
</script>

<template>
  <div class="ecran">
    <p v-if="!annonce && !erreur" class="etat">Chargement de l'annonce…</p>
    <div v-else-if="!annonce" class="contenu">
      <p class="message-erreur" role="alert">{{ erreur }}</p>
      <RouterLink :to="{ name: 'marche' }">Retour au marché</RouterLink>
    </div>

    <template v-else>
      <div
        class="photo"
        :class="{ 'photo--claire': estCouleurClaire(annonce.article.couleur) }"
        :style="{ background: fondVignette(annonce.article.couleur) }"
      >
        <img v-if="annonce.article.photo" :src="annonce.article.photo" alt="" class="photo__image" />
        <svg v-else width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><circle cx="7" cy="7" r="1" /></svg>
        <button type="button" class="photo__retour" aria-label="Retour" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
      </div>

      <main class="contenu contenu--fiche">
        <div class="titre">
          <div class="titre__textes">
            <span class="titre__prix">{{ formatPrix(annonce.prix) }}</span>
            <h1 class="titre__nom">{{ annonce.article.titre }}</h1>
            <span class="titre__infos">{{ annonce.article.infos }}</span>
          </div>
          <span class="statut" :class="`statut--${annonce.statut.toLowerCase()}`">{{ STATUTS[annonce.statut] }}</span>
        </div>

        <RouterLink :to="{ name: 'membre', params: { pseudo: annonce.vendeur.pseudo } }" class="vendeur">
          <AvatarMembre :membre="annonce.vendeur" :taille="36" />
          <span class="vendeur__textes">
            <span class="vendeur__libelle">Vendu par</span>
            <span class="vendeur__pseudo">{{ annonce.vendeur.pseudo }}</span>
          </span>
        </RouterLink>

        <p v-if="annonce.description" class="description">{{ annonce.description }}</p>
        <RouterLink :to="{ name: 'article', params: { id: annonce.article.id } }" class="lien">Voir la fiche de l'article</RouterLink>

        <p class="info">L'application ne gère ni paiement, ni retour, ni litige : mettez-vous d'accord directement.</p>
        <p v-if="erreurAction" class="message-erreur" role="alert">{{ erreurAction }}</p>

        <!-- Acheteur : contacter le vendeur, ou reprendre la conversation -->
        <template v-if="!annonce.estVendeur">
          <RouterLink v-if="maConversation" :to="{ name: 'conversation', params: { id: maConversation.id } }" class="bouton-principal">
            Voir la conversation<template v-if="maConversation.nonLus"> · {{ maConversation.nonLus }} non lu{{ maConversation.nonLus > 1 ? 's' : '' }}</template>
          </RouterLink>
          <form v-else-if="estActive" class="contact" @submit.prevent="contacter">
            <label class="champ">
              <span class="champ__libelle">Votre message à {{ annonce.vendeur.pseudo }}</span>
              <textarea v-model="premierMessage" class="saisie contact__message" rows="3" maxlength="2000"></textarea>
            </label>
            <button type="submit" class="bouton-principal" :disabled="envoi || !premierMessage.trim()">Contacter le vendeur</button>
          </form>
          <p v-else class="etat">Cette annonce n'est plus en ligne.</p>
        </template>

        <!-- Vendeur : prix, statut et conversations reçues -->
        <template v-else>
          <template v-if="estActive">
            <form class="prix" @submit.prevent="modifier({ prix })">
              <label class="saisie saisie--groupe prix__saisie">
                <input v-model="prix" type="text" inputmode="decimal" aria-label="Prix en euros" />
                <span aria-hidden="true">€</span>
              </label>
              <button type="submit" class="action" :disabled="envoi">Changer le prix</button>
            </form>
            <div class="actions">
              <button v-if="annonce.statut === 'EN_LIGNE'" type="button" class="action" :disabled="envoi" @click="modifier({ statut: 'RESERVEE' })">Réserver</button>
              <button v-else type="button" class="action" :disabled="envoi" @click="modifier({ statut: 'EN_LIGNE' })">Remettre en ligne</button>
              <button type="button" class="action action--principale" :disabled="envoi" @click="vente = !vente">Marquer vendue</button>
            </div>
            <form v-if="vente" class="vente" @submit.prevent="modifier({ statut: 'VENDUE', acheteur }, 'Marquer cette annonce comme vendue ? L\'article quittera votre dressing.')">
              <label class="champ">
                <span class="champ__libelle">Vendu à</span>
                <select v-model="acheteur" class="saisie vente__acheteur">
                  <option value="">Quelqu'un hors de l'application</option>
                  <option v-for="conversation in annonce.conversations" :key="conversation.id" :value="conversation.acheteur.pseudo">@{{ conversation.acheteur.pseudo }}</option>
                </select>
              </label>
              <button type="submit" class="bouton-principal" :disabled="envoi">Confirmer la vente</button>
            </form>
            <button type="button" class="retirer" :disabled="envoi" @click="modifier({ statut: 'RETIREE' }, 'Retirer cette annonce du marché ? L\'article redeviendra disponible.')">Retirer l'annonce</button>
          </template>
          <p v-else-if="annonce.statut === 'VENDUE'" class="etat">Vendu<template v-if="annonce.acheteur"> à @{{ annonce.acheteur.pseudo }}</template>.</p>

          <section class="conversations">
            <h2 class="conversations__titre">Messages reçus</h2>
            <p v-if="annonce.conversations.length === 0" class="etat">Personne ne vous a encore écrit.</p>
            <RouterLink v-for="conversation in annonce.conversations" :key="conversation.id" :to="{ name: 'conversation', params: { id: conversation.id } }" class="conversation">
              <AvatarMembre :membre="conversation.acheteur" :taille="40" />
              <span class="conversation__textes">
                <span class="conversation__pseudo">{{ conversation.acheteur.pseudo }}</span>
                <span class="conversation__dernier">{{ conversation.dernierMessage?.contenu }}</span>
              </span>
              <span v-if="conversation.nonLus" class="conversation__non-lus">{{ conversation.nonLus }}</span>
            </RouterLink>
          </section>
        </template>
      </main>
    </template>
  </div>
</template>

<style scoped>
.photo {
  position: relative;
  height: 300px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone-grande);
}

.photo--claire {
  color: var(--couleur-photo-icone-sombre);
}

.photo__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo__retour {
  position: absolute;
  top: 16px;
  left: 16px;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 14px;
  background: var(--couleur-photo-bouton);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.contenu--fiche {
  margin-top: -20px;
  padding-top: 22px;
  border-radius: 24px 24px 0 0;
  background: var(--couleur-fond);
  position: relative;
}

.titre {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.titre__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.titre__prix {
  font-size: 24px;
  font-weight: 800;
  color: var(--couleur-primaire);
}

.titre__nom {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.titre__infos {
  font-size: 14px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.statut {
  flex-shrink: 0;
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--couleur-succes-fond);
  color: var(--couleur-succes);
  font-size: 12px;
  font-weight: 700;
}

.statut--reservee {
  background: var(--couleur-alerte-fond);
  color: var(--couleur-alerte);
}

.statut--vendue,
.statut--retiree {
  background: var(--couleur-info-fond);
  color: var(--couleur-texte-secondaire);
}

.vendeur {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--couleur-texte);
}

.vendeur__textes {
  display: flex;
  flex-direction: column;
}

.vendeur__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.vendeur__pseudo {
  font-size: 14px;
  font-weight: 800;
}

.description {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-line;
}

.lien {
  align-self: flex-start;
  font-size: 14px;
  font-weight: 700;
}

.info {
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.4;
}

.contact {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.champ {
  gap: 6px;
}

.contact__message {
  padding: 12px 16px;
  resize: vertical;
  line-height: 1.5;
}

.prix {
  display: flex;
  gap: 10px;
}

.prix__saisie {
  flex: 1;
  height: 44px;
  gap: 6px;
  font-weight: 700;
}

.actions {
  display: flex;
  gap: 10px;
}

.action {
  flex: 1;
  height: 44px;
  padding: 0 12px;
  border-radius: 14px;
  border: 1.5px solid var(--couleur-primaire);
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.prix .action {
  flex: 0 0 auto;
}

.action--principale {
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.action:disabled,
.retirer:disabled {
  opacity: 0.6;
  cursor: wait;
}

.vente {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.vente__acheteur {
  height: 48px;
}

.retirer {
  align-self: center;
  padding: 8px;
  border: 0;
  background: none;
  color: var(--couleur-erreur);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.conversations {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.conversations__titre {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
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

.conversation__pseudo {
  font-size: 14px;
  font-weight: 800;
}

.conversation__dernier {
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation__non-lus {
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
