<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const annonce = ref(null)
const erreur = ref('')
const erreurAction = ref('')
const envoi = ref(false)

const premierMessage = ref(t('annonce.premierMessage'))
const prix = ref('')
const vente = ref(false)
const acheteur = ref('')

const estActive = computed(() => ['EN_LIGNE', 'RESERVEE'].includes(annonce.value?.statut))
const maConversation = computed(() => (annonce.value?.estVendeur ? null : annonce.value?.conversations[0]))
const nonLus = computed(() => annonce.value?.conversations.reduce((total, conversation) => total + conversation.nonLus, 0) ?? 0)

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
    <p v-if="!annonce && !erreur" class="etat">{{ t('annonce.chargement') }}</p>
    <div v-else-if="!annonce" class="page">
      <p class="message-erreur" role="alert">{{ erreur }}</p>
      <RouterLink :to="{ name: 'marche' }" class="lien">{{ t('annonce.retourMarche') }}</RouterLink>
    </div>

    <main v-else class="page fiche">
      <!-- Ordinateur : retour et fil d'Ariane au-dessus des deux colonnes -->
      <div class="entete seulement-ordinateur">
        <RouterLink :to="{ name: 'marche' }" class="bouton-icone" :aria-label="t('annonce.retourMarche')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <span class="fil-ariane entete__textes">{{ t('navigation.marche') }} › {{ annonce.estVendeur ? t('annonce.votreAnnonceTitre') : annonce.article.titre }}</span>
        <RouterLink v-if="annonce.estVendeur" :to="{ name: 'article', params: { id: annonce.article.id } }" class="lien">{{ t('annonce.voirFiche') }}</RouterLink>
      </div>

      <div class="deux-colonnes">
        <div
          class="photo"
          :class="{ 'photo--claire': estCouleurClaire(annonce.article.couleur), 'photo--vendeur': annonce.estVendeur }"
          :style="{ background: annonce.article.photo ? 'var(--couleur-pastel-sable)' : fondVignette(annonce.article.couleur) }"
        >
          <img v-if="annonce.article.photo" :src="annonce.article.photo" alt="" class="photo__image" />
          <svg v-else width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><circle cx="7" cy="7" r="1" /></svg>
          <RouterLink :to="{ name: 'marche' }" class="photo__retour seulement-mobile" :aria-label="t('annonce.retourMarche')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </RouterLink>
          <span class="badge badge--blanc photo__statut">{{ t(`annonce.statuts.${annonce.statut}`) }}</span>
        </div>

        <div class="informations">
          <!-- Acheteur : titre et prix ; vendeur : « Votre annonce · 18 € » -->
          <div class="titre">
            <div class="entete__textes">
              <h1 class="titre-page titre__nom">{{ annonce.article.titre }}</h1>
              <span class="texte-secondaire">{{ annonce.estVendeur ? t('annonce.votreAnnonce', { prix: formatPrix(annonce.prix) }) : annonce.article.infos }}</span>
            </div>
            <span v-if="!annonce.estVendeur" class="titre-page titre__prix">{{ formatPrix(annonce.prix) }}</span>
            <RouterLink v-else :to="{ name: 'article', params: { id: annonce.article.id } }" class="lien seulement-mobile">{{ t('annonce.voirFiche') }}</RouterLink>
          </div>

          <p v-if="erreurAction" class="message-erreur" role="alert">{{ erreurAction }}</p>

          <!-- Acheteur : contacter le vendeur, ou reprendre la conversation -->
          <template v-if="!annonce.estVendeur">
            <p v-if="annonce.description" class="texte-secondaire description">{{ annonce.description }}</p>
            <RouterLink :to="{ name: 'membre', params: { pseudo: annonce.vendeur.pseudo } }" class="carte carte--rangee vendeur">
              <AvatarMembre :membre="annonce.vendeur" :taille="44" />
              <span class="entete__textes">
                <span class="texte-secondaire">{{ t('annonce.venduPar') }}</span>
                <b>@{{ annonce.vendeur.pseudo }}</b>
              </span>
              <svg class="ligne__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
            <p class="carte carte--grise texte-secondaire">{{ t('annonce.avertissement') }}</p>

            <div v-if="maConversation" class="barre-actions barre-actions--dans-page">
              <RouterLink :to="{ name: 'conversation', params: { id: maConversation.id } }" class="bouton bouton--large">
                {{ t('annonce.voirConversation') }}<template v-if="maConversation.nonLus"> · {{ t('annonce.nonLus', maConversation.nonLus) }}</template>
              </RouterLink>
            </div>
            <form v-else-if="estActive" class="contact" @submit.prevent="contacter">
              <label class="champ">
                <span class="champ__libelle">{{ t('annonce.votreMessage', { pseudo: `@${annonce.vendeur.pseudo}` }) }}</span>
                <textarea v-model="premierMessage" class="saisie" rows="3" maxlength="2000"></textarea>
              </label>
              <div class="barre-actions barre-actions--dans-page">
                <button type="submit" class="bouton bouton--large" :disabled="envoi || !premierMessage.trim()">{{ t('annonce.contacter') }}</button>
              </div>
            </form>
            <p v-else class="etat">{{ t('annonce.horsLigne') }}</p>
          </template>

          <!-- Vendeur : prix, statut et conversations reçues -->
          <template v-else>
            <template v-if="estActive">
              <form class="prix" @submit.prevent="modifier({ prix })">
                <label class="champ prix__champ">
                  <span class="champ__libelle">{{ t('annonce.changerPrixEuros') }}</span>
                  <input v-model="prix" class="saisie" type="text" inputmode="decimal" />
                </label>
                <button type="submit" class="bouton bouton--contour" :disabled="envoi">{{ t('annonce.modifier') }}</button>
              </form>
              <div class="grille-3">
                <button v-if="annonce.statut === 'EN_LIGNE'" type="button" class="bouton bouton--contour bouton--petit" :disabled="envoi" @click="modifier({ statut: 'RESERVEE' })">{{ t('annonce.reserver') }}</button>
                <button v-else type="button" class="bouton bouton--contour bouton--petit" :disabled="envoi" @click="modifier({ statut: 'EN_LIGNE' })">{{ t('annonce.remettreEnLigne') }}</button>
                <button type="button" class="bouton bouton--petit" :disabled="envoi" :aria-expanded="vente" @click="vente = !vente">{{ t('annonce.marquerVendue') }}</button>
                <button type="button" class="bouton bouton--contour bouton--petit" :disabled="envoi" @click="modifier({ statut: 'RETIREE' }, t('annonce.confirmerRetrait'))">{{ t('annonce.retirer') }}</button>
              </div>
              <form v-if="vente" class="carte carte--grise vente" @submit.prevent="modifier({ statut: 'VENDUE', acheteur }, t('annonce.confirmerVente'))">
                <label class="champ">
                  <span class="champ__libelle">{{ t('annonce.venduA') }}</span>
                  <select v-model="acheteur" class="saisie">
                    <option value="">{{ t('annonce.horsApplication') }}</option>
                    <option v-for="conversation in annonce.conversations" :key="conversation.id" :value="conversation.acheteur.pseudo">@{{ conversation.acheteur.pseudo }}</option>
                  </select>
                </label>
                <button type="submit" class="bouton" :disabled="envoi">{{ t('annonce.confirmerLaVente') }}</button>
              </form>
            </template>
            <p v-else-if="annonce.statut === 'VENDUE'" class="etat">{{ annonce.acheteur ? t('annonce.venduAMembre', { pseudo: `@${annonce.acheteur.pseudo}` }) : t('annonce.vendu') }}</p>

            <section class="pile conversations">
              <div class="ligne-titre">
                <h2 class="titre-section">{{ t('annonce.conversations', { n: annonce.conversations.length }) }}</h2>
                <span v-if="nonLus" class="badge badge--plein">{{ t('annonce.nonLus', nonLus) }}</span>
              </div>
              <p v-if="annonce.conversations.length === 0" class="etat">{{ t('annonce.personne') }}</p>
              <div v-else class="lignes">
                <RouterLink v-for="conversation in annonce.conversations" :key="conversation.id" :to="{ name: 'conversation', params: { id: conversation.id } }" class="ligne">
                  <AvatarMembre :membre="conversation.acheteur" :taille="44" />
                  <span class="ligne__textes">
                    <b>@{{ conversation.acheteur.pseudo }}</b>
                    <span class="texte-secondaire dernier">{{ conversation.dernierMessage?.contenu }}</span>
                  </span>
                  <span v-if="conversation.nonLus" class="point" :aria-label="t('annonce.nonLus', conversation.nonLus)"></span>
                  <svg v-else class="ligne__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                </RouterLink>
              </div>
            </section>
          </template>
        </div>
      </div>
    </main>

    <BarreNavigation actif="marche" ordinateur-seulement />
  </div>
</template>

<style scoped>
.fiche {
  padding-top: 0;
}

/* Téléphone : photo bord à bord en haut de l'écran */
.photo {
  position: relative;
  margin: 0 -16px;
  height: 250px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: var(--couleur-photo-icone-grande);
}

.photo--vendeur {
  height: 170px;
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
  top: 14px;
  left: 14px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--couleur-photo-bouton);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo__statut {
  position: absolute;
  left: 14px;
  bottom: 12px;
}

.informations {
  display: flex;
  flex-direction: column;
  gap: 11px;
  min-width: 0;
}

.titre {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.titre__prix {
  flex-shrink: 0;
}

.description {
  white-space: pre-line;
}

.vendeur,
.vendeur:hover {
  color: var(--couleur-texte);
}

.contact {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.prix {
  display: flex;
  align-items: flex-end;
  gap: 10px;
}

.prix__champ {
  flex: 1;
}

.vente {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dernier {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.point {
  width: 9px;
  height: 9px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--couleur-primaire);
}

/* Ordinateur : photo carrée à gauche, annonce à droite */
@media (min-width: 1024px) {
  .fiche {
    padding-top: 28px;
  }

  .photo,
  .photo--vendeur {
    margin: 0;
    height: auto;
    aspect-ratio: 1 / 1;
    border-radius: 14px;
  }

  .photo__statut {
    left: 12px;
  }

  .informations {
    gap: 12px;
  }

  .titre__nom,
  .titre__prix {
    font-size: 26px;
  }
}
</style>
