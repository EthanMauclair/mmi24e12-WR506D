<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import CarteTenue from '@/components/CarteTenue.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const profil = ref(null)
const articles = ref([])
const chargement = ref(true)
const erreur = ref('')
const erreurAction = ref('')
const actionEnCours = ref(false)
// Après « Partager » dans le générateur, le profil s'ouvre directement sur ses tenues
const onglet = ref(route.query.onglet === 'tenues' ? 'tenues' : 'articles')
// null tant que l'onglet Tenues n'a pas été ouvert
const tenues = ref(null)
const erreurTenues = ref('')

// /profil affiche le profil de l'utilisateur connecté, /membres/:pseudo celui d'un membre
const pseudo = computed(() => route.params.pseudo ?? session.utilisateur?.pseudo)
const estMoi = computed(() => profil.value?.relation.estMoi ?? false)

const actionAbonnement = computed(() => {
  const relation = profil.value.relation
  if (relation.abonnement === 'ACCEPTE') {
    return { libelle: t('profil.abonne'), secondaire: true }
  }
  if (relation.abonnement === 'EN_ATTENTE') {
    return { libelle: t('profil.demandeEnvoyee'), secondaire: true }
  }
  if (relation.mEstAbonne) {
    return { libelle: t('profil.abonnerEnRetour'), secondaire: false }
  }
  return { libelle: t(profil.value.estPrive ? 'profil.demanderSuivre' : 'profil.abonner'), secondaire: false }
})

async function chargerArticles() {
  articles.value = profil.value.peutVoirContenu ? await api(`/profils/${profil.value.pseudo}/articles`) : []
  tenues.value = null
  if (onglet.value === 'tenues') {
    await chargerTenues()
  }
}

async function chargerTenues() {
  if (!profil.value.peutVoirContenu || tenues.value !== null) {
    return
  }
  erreurTenues.value = ''
  try {
    tenues.value = await api(`/profils/${profil.value.pseudo}/tenues`)
  } catch (e) {
    erreurTenues.value = e.message
  }
}

function ouvrirOnglet(nom) {
  onglet.value = nom
  if (nom === 'tenues') {
    chargerTenues()
  }
}

function retirerTenue(tenue) {
  tenues.value = tenues.value.filter((autre) => autre.id !== tenue.id)
}

async function charger() {
  chargement.value = true
  erreur.value = ''
  erreurAction.value = ''
  try {
    profil.value = await api(`/profils/${pseudo.value}`)
    await chargerArticles()
  } catch (e) {
    erreur.value = e.statut === 404 ? t('profil.inexistant') : e.message
  } finally {
    chargement.value = false
  }
}

async function basculerAbonnement() {
  if (!session.utilisateur) {
    router.push({ name: 'connexion', query: { redirection: route.fullPath } })
    return
  }
  const relation = profil.value.relation
  // Se désabonner d'un profil privé fait perdre l'accès à son contenu : on demande confirmation
  if (relation.abonnement === 'ACCEPTE' && profil.value.estPrive
    && !window.confirm(t('profil.confirmerDesabonnement', { pseudo: profil.value.pseudo }))) {
    return
  }

  actionEnCours.value = true
  erreurAction.value = ''
  try {
    profil.value = await api(`/profils/${profil.value.pseudo}/abonnement`, { methode: relation.abonnement ? 'DELETE' : 'POST' })
    await chargerArticles()
  } catch (e) {
    erreurAction.value = e.message
  } finally {
    actionEnCours.value = false
  }
}

watch(pseudo, (valeur) => valeur && charger(), { immediate: true })
</script>

<template>
  <div class="ecran">
    <main class="page">
      <p v-if="chargement && !profil" class="etat">{{ t('profil.chargement') }}</p>
      <div v-else-if="erreur" class="pile">
        <p class="message-erreur" role="alert">{{ erreur }}</p>
        <RouterLink :to="{ name: 'membres' }" class="lien">{{ t('profil.rechercherMembre') }}</RouterLink>
      </div>

      <template v-else-if="profil">
        <!-- Téléphone : « @pseudo » en titre, avec retour (profil d'un membre) ou recherche (mon profil) -->
        <header class="entete seulement-mobile">
          <RouterLink v-if="!estMoi" :to="{ name: 'membres' }" class="bouton-icone" :aria-label="t('commun.retour')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </RouterLink>
          <h1 class="titre-page">@{{ profil.pseudo }}</h1>
          <RouterLink v-if="estMoi" :to="{ name: 'membres' }" class="bouton-icone" :aria-label="t('profil.rechercherMembres')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          </RouterLink>
        </header>

        <!-- Ordinateur : fil d'Ariane sur le profil d'un membre -->
        <div v-if="!estMoi" class="entete seulement-ordinateur">
          <RouterLink :to="{ name: 'membres' }" class="bouton-icone" :aria-label="t('commun.retour')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </RouterLink>
          <span class="fil-ariane">{{ t('profil.membres') }} › @{{ profil.pseudo }}</span>
        </div>

        <section class="profil">
          <AvatarMembre class="profil__avatar" :membre="profil" :taille="84" />

          <div class="profil__titre seulement-ordinateur">
            <h1 class="titre-page">@{{ profil.pseudo }}</h1>
            <template v-if="estMoi">
              <RouterLink :to="{ name: 'profil-modifier' }" class="bouton bouton--contour bouton--petit">{{ t('profil.modifier') }}</RouterLink>
              <RouterLink :to="{ name: 'profil-espace' }" class="bouton bouton--contour bouton--petit">{{ t('profil.monEspace') }}</RouterLink>
              <RouterLink :to="{ name: 'membres' }" class="bouton-icone" :aria-label="t('profil.rechercherMembres')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
              </RouterLink>
            </template>
            <button
              v-else
              type="button"
              class="bouton bouton--petit"
              :class="{ 'bouton--contour': actionAbonnement.secondaire }"
              :disabled="actionEnCours"
              @click="basculerAbonnement"
            >
              {{ actionAbonnement.libelle }}
            </button>
          </div>

          <div class="compteurs">
            <div class="compteur">
              <b>{{ profil.compteurs.articles ?? '—' }}</b>
              <span class="texte-secondaire">{{ t('dressing.articles', profil.compteurs.articles ?? 0) }}</span>
            </div>
            <component :is="profil.peutVoirContenu ? RouterLink : 'div'" :to="{ name: 'membre-abonnes', params: { pseudo: profil.pseudo } }" class="compteur">
              <b>{{ profil.compteurs.abonnes }}</b>
              <span class="texte-secondaire">{{ t('profil.abonnes', profil.compteurs.abonnes) }}</span>
            </component>
            <component :is="profil.peutVoirContenu ? RouterLink : 'div'" :to="{ name: 'membre-abonnements', params: { pseudo: profil.pseudo } }" class="compteur">
              <b>{{ profil.compteurs.abonnements }}</b>
              <span class="texte-secondaire">{{ t('profil.abonnements', profil.compteurs.abonnements) }}</span>
            </component>
          </div>

          <p class="profil__identite">
            <b v-if="profil.nomComplet">{{ profil.nomComplet }}</b>
            <span v-if="profil.nomComplet && profil.bio" class="seulement-ordinateur texte-secondaire"> · </span>
            <span v-if="profil.bio" class="texte-secondaire profil__bio">{{ profil.bio }}</span>
          </p>
        </section>

        <template v-if="estMoi">
          <div class="boutons seulement-mobile">
            <RouterLink :to="{ name: 'profil-modifier' }" class="bouton bouton--contour bouton--petit bouton--flexible">{{ t('profil.modifier') }}</RouterLink>
            <RouterLink :to="{ name: 'profil-espace' }" class="bouton bouton--contour bouton--petit bouton--flexible">{{ t('profil.monEspace') }}</RouterLink>
          </div>

          <!-- Liens : liste sur téléphone, puces sur ordinateur -->
          <nav class="raccourcis" :aria-label="t('profil.parametres')">
            <RouterLink :to="{ name: 'profil-demandes' }" class="raccourci">
              <span class="raccourci__libelle">{{ t('demandes.titre') }}</span>
              <span v-if="profil.moi.demandes" class="badge badge--plein">{{ profil.moi.demandes }}</span>
              <svg class="ligne__chevron seulement-mobile" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
            <RouterLink :to="{ name: 'profil-amis' }" class="raccourci">
              <span class="raccourci__libelle">{{ t('profil.amis', { n: profil.moi.amis }) }}</span>
              <svg class="ligne__chevron seulement-mobile" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
            <RouterLink :to="{ name: 'prets' }" class="raccourci">
              <span class="raccourci__libelle">{{ t('profil.ongletPrets') }} · {{ profil.moi.pretsEnCours }}</span>
              <svg class="ligne__chevron seulement-mobile" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
            <RouterLink v-if="session.utilisateur?.estAdmin" :to="{ name: 'admin' }" class="raccourci">
              <span class="raccourci__libelle">{{ t('profil.backOffice') }}</span>
              <svg class="ligne__chevron seulement-mobile" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </RouterLink>
          </nav>
        </template>

        <template v-else>
          <button
            type="button"
            class="bouton bouton--large seulement-mobile"
            :class="{ 'bouton--contour': actionAbonnement.secondaire }"
            :disabled="actionEnCours"
            @click="basculerAbonnement"
          >
            {{ actionAbonnement.libelle }}
          </button>
          <p v-if="profil.relation.estAmi" class="bandeau-info">{{ t('profil.estAmi') }}</p>
          <p v-if="erreurAction" class="message-erreur" role="alert">{{ erreurAction }}</p>
        </template>

        <div v-if="!profil.peutVoirContenu" class="verrou">
          <span class="verrou__icone">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
          </span>
          <b>{{ t('profil.prive') }}</b>
          <span class="texte-secondaire">{{ t('profil.abonnezVous') }}</span>
        </div>

        <template v-else>
          <div role="tablist" :aria-label="t('profil.contenu')" class="segments onglets">
            <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'articles' }" :aria-selected="onglet === 'articles'" @click="ouvrirOnglet('articles')">
              {{ t('profil.ongletArticles') }}
            </button>
            <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'tenues' }" :aria-selected="onglet === 'tenues'" @click="ouvrirOnglet('tenues')">
              {{ t('profil.ongletTenues') }}
            </button>
          </div>

          <template v-if="onglet === 'tenues'">
            <p v-if="erreurTenues" class="message-erreur" role="alert">{{ erreurTenues }}</p>
            <p v-if="tenues === null" class="etat">{{ t('profil.chargementTenues') }}</p>
            <div v-else-if="tenues.length === 0" class="pile">
              <p class="etat">{{ t('profil.aucuneTenue') }}</p>
              <RouterLink v-if="estMoi" :to="{ name: 'tenues' }" class="bouton">{{ t('profil.genererTenue') }}</RouterLink>
            </div>
            <div v-else class="pile grille-2-ordinateur">
              <CarteTenue v-for="tenue in tenues" :key="tenue.id" :tenue="tenue" @supprimee="retirerTenue" />
            </div>
          </template>
          <p v-else-if="articles.length === 0" class="etat">{{ t('profil.aucunArticle') }}</p>
          <div v-else class="grille-photos grille-profil">
            <RouterLink v-for="article in articles" :key="article.id" :to="{ name: 'article', params: { id: article.id } }" class="vignette-lien" :aria-label="article.titre">
              <VignetteArticle :photo="article.photo" :couleur="article.couleur" carree :icone="28" />
            </RouterLink>
          </div>
        </template>
      </template>
    </main>

    <BarreNavigation :actif="estMoi || !route.params.pseudo ? 'profil' : ''" />
  </div>
</template>

<style scoped>
/* Téléphone : avatar et compteurs sur une ligne, puis nom et bio */
.profil {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  grid-template-areas:
    'avatar compteurs'
    'identite identite';
  align-items: center;
  gap: 12px 16px;
}

.profil__avatar {
  grid-area: avatar;
}

.compteurs {
  grid-area: compteurs;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  text-align: center;
}

.compteur {
  display: flex;
  flex-direction: column;
  color: var(--couleur-texte);
}

a.compteur:hover {
  color: var(--couleur-primaire);
}

.profil__identite {
  grid-area: identite;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.profil__bio {
  white-space: pre-line;
}

.raccourcis {
  display: flex;
  flex-direction: column;
  padding: 0 12px;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 14px;
  background: var(--couleur-surface);
}

.raccourci {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 0;
  border-bottom: 1px solid var(--couleur-separateur);
  color: var(--couleur-texte);
}

.raccourci:last-child {
  border-bottom: 0;
}

.raccourci:hover {
  color: var(--couleur-texte);
}

.raccourci__libelle {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
}

.verrou {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 30px 16px;
  border: 1.5px dashed var(--couleur-bordure-pointillee);
  border-radius: 14px;
  background: var(--couleur-surface);
  text-align: center;
}

.verrou__icone {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.vignette-lien {
  display: block;
}

.vignette-lien :deep(.vignette) {
  width: 100%;
}

.vignette-lien:hover :deep(.vignette) {
  outline: 2px solid var(--couleur-primaire);
  outline-offset: 1px;
}

/* Ordinateur : en-tête de profil en ligne (photo, pseudo et actions, compteurs, nom et bio) */
@media (min-width: 1024px) {
  .profil {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      'avatar titre'
      'avatar compteurs'
      'avatar identite';
    align-items: start;
    gap: 8px 24px;
  }

  .profil__avatar {
    width: 110px !important;
    height: 110px !important;
    font-size: 40px !important;
  }

  .profil__titre {
    grid-area: titre;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
  }

  .compteurs {
    display: flex;
    gap: 28px;
    text-align: left;
  }

  .compteur {
    flex-direction: row;
    align-items: baseline;
    gap: 6px;
  }

  .profil__identite {
    display: block;
  }

  .raccourcis {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0;
    border: 0;
    background: none;
  }

  .raccourci,
  .raccourci:last-child {
    height: 32px;
    padding: 0 13px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 999px;
    background: var(--couleur-surface);
  }

  .raccourci:hover {
    border-color: var(--couleur-primaire);
  }

  .raccourci__libelle {
    flex: none;
    font-size: 13px;
    font-weight: 600;
  }

  .onglets {
    width: 280px;
  }

  .verrou {
    padding: 60px 16px;
  }

  .grille-profil {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }
}
</style>
