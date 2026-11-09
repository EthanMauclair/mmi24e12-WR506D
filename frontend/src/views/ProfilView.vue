<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const profil = ref(null)
const articles = ref([])
const chargement = ref(true)
const erreur = ref('')
const erreurAction = ref('')
const actionEnCours = ref(false)
const onglet = ref('articles')

// /profil affiche le profil de l'utilisateur connecté, /membres/:pseudo celui d'un membre
const pseudo = computed(() => route.params.pseudo ?? session.utilisateur?.pseudo)
const estMoi = computed(() => profil.value?.relation.estMoi ?? false)
const nomAffiche = computed(() => profil.value.nomComplet ?? profil.value.pseudo)

const actionAbonnement = computed(() => {
  const relation = profil.value.relation
  if (relation.abonnement === 'ACCEPTE') {
    return { libelle: 'Abonné', secondaire: true }
  }
  if (relation.abonnement === 'EN_ATTENTE') {
    return { libelle: 'Demande envoyée', secondaire: true }
  }
  if (relation.mEstAbonne) {
    return { libelle: "S'abonner en retour", secondaire: false }
  }
  return { libelle: profil.value.estPrive ? 'Demander à suivre' : "S'abonner", secondaire: false }
})

function pluriel(nombre, mot) {
  return nombre > 1 ? `${mot}s` : mot
}

async function chargerArticles() {
  articles.value = profil.value.peutVoirContenu ? await api(`/profils/${profil.value.pseudo}/articles`) : []
}

async function charger() {
  chargement.value = true
  erreur.value = ''
  erreurAction.value = ''
  try {
    profil.value = await api(`/profils/${pseudo.value}`)
    await chargerArticles()
  } catch (e) {
    erreur.value = e.statut === 404 ? "Ce membre n'existe pas." : e.message
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
    && !window.confirm(`Ne plus suivre ${profil.value.pseudo} ? Son profil est privé : il faudra refaire une demande pour revoir ses articles.`)) {
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
    <main class="contenu">
      <p v-if="chargement && !profil" class="etat">Chargement du profil…</p>
      <div v-else-if="erreur" class="contenu-erreur">
        <p class="message-erreur" role="alert">{{ erreur }}</p>
        <RouterLink :to="{ name: 'membres' }">Rechercher un membre</RouterLink>
      </div>

      <template v-else-if="profil">
        <RouterLink v-if="!estMoi" :to="{ name: 'membres' }" class="retour">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          Membres
        </RouterLink>

        <header class="entete">
          <AvatarMembre :membre="profil" :taille="76" anneau />
          <div class="entete__textes">
            <h1 class="entete__nom">{{ nomAffiche }}</h1>
            <span class="entete__pseudo">@{{ profil.pseudo }}</span>
            <span v-if="profil.estPrive" class="badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              Profil privé
            </span>
          </div>
          <RouterLink v-if="estMoi" :to="{ name: 'profil-modifier' }" class="bouton-icone entete__bouton" aria-label="Paramètres du profil">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
          </RouterLink>
        </header>

        <p v-if="profil.bio" class="bio">{{ profil.bio }}</p>

        <div class="compteurs">
          <div class="compteur">
            <span class="compteur__nombre">{{ profil.compteurs.articles ?? '—' }}</span>
            <span class="compteur__libelle">{{ pluriel(profil.compteurs.articles ?? 0, 'article') }}</span>
          </div>
          <component
            :is="profil.peutVoirContenu ? RouterLink : 'div'"
            :to="{ name: 'membre-abonnes', params: { pseudo: profil.pseudo } }"
            class="compteur compteur--milieu"
          >
            <span class="compteur__nombre">{{ profil.compteurs.abonnes }}</span>
            <span class="compteur__libelle">{{ pluriel(profil.compteurs.abonnes, 'abonné') }}</span>
          </component>
          <component
            :is="profil.peutVoirContenu ? RouterLink : 'div'"
            :to="{ name: 'membre-abonnements', params: { pseudo: profil.pseudo } }"
            class="compteur"
          >
            <span class="compteur__nombre">{{ profil.compteurs.abonnements }}</span>
            <span class="compteur__libelle">{{ pluriel(profil.compteurs.abonnements, 'abonnement') }}</span>
          </component>
        </div>

        <template v-if="estMoi">
          <RouterLink v-if="profil.moi.demandes" :to="{ name: 'profil-demandes' }" class="demandes">
            <span class="demandes__icone">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></svg>
            </span>
            <span class="demandes__texte">{{ profil.moi.demandes }} {{ pluriel(profil.moi.demandes, "demande") }} d'abonnement</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
          </RouterLink>

          <div class="boutons">
            <RouterLink :to="{ name: 'profil-modifier' }" class="bouton bouton--secondaire">Modifier le profil</RouterLink>
            <RouterLink :to="{ name: 'profil-amis' }" class="bouton bouton--principal">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2-6.2 3.2L7 14.2 2 9.3l6.9-1z" /></svg>
              Amis · {{ profil.moi.amis }}
            </RouterLink>
          </div>

          <RouterLink :to="{ name: 'membres' }" class="recherche">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
            Rechercher des membres
          </RouterLink>
        </template>

        <template v-else>
          <div class="boutons">
            <button
              type="button"
              class="bouton"
              :class="actionAbonnement.secondaire ? 'bouton--secondaire' : 'bouton--principal'"
              :disabled="actionEnCours"
              @click="basculerAbonnement"
            >
              <svg v-if="profil.relation.abonnement === 'ACCEPTE'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
              {{ actionAbonnement.libelle }}
            </button>
          </div>
          <p v-if="profil.relation.estAmi" class="ami">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2-6.2 3.2L7 14.2 2 9.3l6.9-1z" /></svg>
            Vous faites partie de ses amis : vous pouvez commenter ses articles.
          </p>
          <p v-if="erreurAction" class="message-erreur" role="alert">{{ erreurAction }}</p>
        </template>

        <div role="tablist" aria-label="Contenu du profil" class="onglets">
          <button type="button" role="tab" class="onglet" :class="{ 'onglet--actif': onglet === 'articles' }" :aria-selected="onglet === 'articles'" @click="onglet = 'articles'">
            Articles<template v-if="profil.compteurs.articles !== null"> · {{ profil.compteurs.articles }}</template>
          </button>
          <button type="button" role="tab" class="onglet" :class="{ 'onglet--actif': onglet === 'tenues' }" :aria-selected="onglet === 'tenues'" @click="onglet = 'tenues'">Tenues</button>
        </div>

        <div v-if="!profil.peutVoirContenu" class="verrou">
          <span class="verrou__icone">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
          </span>
          <span class="verrou__titre">Ce profil est privé</span>
          <span class="verrou__texte">Abonnez-vous pour voir ses articles et ses tenues.</span>
        </div>
        <p v-else-if="onglet === 'tenues'" class="etat">Les tenues arriveront avec le générateur de tenue.</p>
        <p v-else-if="articles.length === 0" class="etat">Aucun article pour le moment.</p>
        <div v-else class="grille">
          <RouterLink
            v-for="article in articles"
            :key="article.id"
            :to="{ name: 'article', params: { id: article.id } }"
            class="vignette"
            :class="{ 'vignette--claire': estCouleurClaire(article.couleur) }"
            :style="{ background: fondVignette(article.couleur) }"
            :aria-label="article.titre"
          >
            <img v-if="article.photo" :src="article.photo" alt="" class="vignette__photo" />
            <svg v-else width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
          </RouterLink>
        </div>
      </template>
    </main>

    <BarreNavigation :actif="estMoi || !route.params.pseudo ? 'profil' : ''" />
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

.retour {
  align-self: flex-start;
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  font-weight: 700;
}

.entete {
  display: flex;
  align-items: center;
  gap: 16px;
}

.entete__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.entete__nom {
  margin: 0;
  font-size: 21px;
  font-weight: 800;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}

.entete__pseudo {
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.entete__bouton {
  align-self: flex-start;
}

.badge {
  display: inline-flex;
  align-self: flex-start;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12px;
  font-weight: 700;
}

.bio {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-line;
}

.compteurs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: 12px 0;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.compteur {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  color: var(--couleur-texte);
}

.compteur--milieu {
  border-left: 1px solid var(--couleur-separateur);
  border-right: 1px solid var(--couleur-separateur);
}

.compteur__nombre {
  font-size: 18px;
  font-weight: 800;
}

.compteur__libelle {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.demandes {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 16px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire-fonce);
}

.demandes__icone {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.demandes__texte {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
}

.boutons {
  display: flex;
  gap: 10px;
}

.bouton {
  flex: 1;
  height: 44px;
  box-sizing: border-box;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.bouton:disabled {
  opacity: 0.6;
  cursor: wait;
}

.bouton--secondaire {
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
}

.bouton--principal {
  border: 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
}

.bouton--principal:hover {
  color: var(--couleur-sur-primaire);
  background: var(--couleur-primaire-fonce);
}

.recherche {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 44px;
  padding: 0 14px;
  border-radius: 14px;
  background: var(--couleur-info-fond);
  color: var(--couleur-texte-secondaire);
  font-size: 14px;
  font-weight: 600;
}

.ami {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--couleur-primaire);
}

.onglets {
  display: flex;
  border-bottom: 1px solid var(--couleur-bordure);
}

.onglet {
  flex: 1;
  height: 44px;
  border: 0;
  border-bottom: 2.5px solid transparent;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.onglet--actif {
  border-bottom-color: var(--couleur-primaire);
  color: var(--couleur-primaire);
  font-weight: 800;
}

.grille {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
}

.vignette {
  position: relative;
  height: 112px;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--couleur-photo-icone);
}

.vignette--claire {
  color: var(--couleur-photo-icone-sombre);
}

.vignette__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.verrou {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 28px 16px;
  text-align: center;
}

.verrou__icone {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: 2px solid var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
}

.verrou__titre {
  font-size: 16px;
  font-weight: 800;
}

.verrou__texte {
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
}

.contenu-erreur {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
