<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import BlocCommentaires from '@/components/BlocCommentaires.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { estCouleurClaire, fondVignette } from '@/utils/couleurs'
import { echeance, formatDate, jourCourt } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()
const route = useRoute()
const session = useSessionStore()

const article = ref(null)
const chargement = ref(true)
const erreur = ref('')
const erreurConfidentialite = ref('')
const erreurPret = ref('')
const indexPhoto = ref(0)

// Un membre du logement revient à la pièce ; un abonné ou un visiteur, au profil de celui qui a ajouté l'article
const retour = computed(() => {
  if (!article.value?.voitEmplacement) {
    return { name: 'membre', params: { pseudo: article.value?.proprietaire.pseudo } }
  }
  return article.value.emplacement ? `/pieces/${article.value.emplacement.piece.id}` : '/dressing'
})
const ajouteParUnAutre = computed(() => article.value.proprietaire.id !== session.utilisateur?.id)
// « Appartement Lyon › Chambre › Armoire »
const chemin = computed(() => {
  const emplacement = article.value.emplacement
  return emplacement ? [emplacement.logement.nom, emplacement.piece.nom, emplacement.stockage.nom].join(' › ') : t('commun.aRanger')
})
const peutPreter = computed(() => article.value.statut === 'DISPONIBLE' && !article.value.estPersonnel)
const peutVendre = computed(() => article.value.peutVendre && !article.value.estPersonnel && article.value.statut === 'DISPONIBLE')

async function charger(id) {
  chargement.value = true
  erreur.value = ''
  try {
    article.value = await api(`/articles/${id}`)
    indexPhoto.value = 0
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
  }
}

async function marquerRendu() {
  erreurPret.value = ''
  try {
    await api(`/prets/${article.value.pretEnCours.id}/retour`, { methode: 'POST' })
    article.value = await api(`/articles/${article.value.id}`)
  } catch (e) {
    erreurPret.value = e.violations.general ?? e.message
  }
}

// L'interrupteur change d'abord à l'écran, puis revient en arrière si l'API refuse
async function changerConfidentialite(estPersonnel) {
  erreurConfidentialite.value = ''
  article.value.estPersonnel = estPersonnel
  try {
    article.value = await api(`/articles/${article.value.id}`, { methode: 'PATCH', corps: { estPersonnel } })
  } catch (e) {
    article.value.estPersonnel = !estPersonnel
    erreurConfidentialite.value = e.violations.estPersonnel ?? e.message
  }
}

watch(() => route.params.id, charger, { immediate: true })
</script>

<template>
  <div class="ecran">
    <p v-if="chargement && !article" class="etat">{{ t('article.chargement') }}</p>
    <div v-else-if="erreur" class="page">
      <p class="message-erreur" role="alert">{{ erreur }}</p>
      <RouterLink to="/dressing" class="lien">{{ t('piece.retour') }}</RouterLink>
    </div>

    <main v-else-if="article" class="page fiche">
      <!-- Ordinateur : retour, chemin de rangement et modification au-dessus des deux colonnes -->
      <div class="entete seulement-ordinateur">
        <RouterLink :to="retour" class="bouton-icone" :aria-label="t('commun.retour')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <span class="fil-ariane entete__textes">{{ article.voitEmplacement ? chemin : article.proprietaire.pseudo }}</span>
        <RouterLink v-if="article.peutModifier" :to="{ name: 'article-modifier', params: { id: article.id } }" class="bouton-icone" :aria-label="t('article.modifier')">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
        </RouterLink>
      </div>

      <div class="deux-colonnes">
        <div class="galerie">
          <div
            class="photo"
            :class="{ 'photo--claire': estCouleurClaire(article.couleur?.codeHex) }"
            :style="{ background: article.photos.length ? 'var(--couleur-pastel-sable)' : fondVignette(article.couleur?.codeHex) }"
          >
            <img v-if="article.photos.length" :src="article.photos[indexPhoto]" alt="" class="photo__image" />
            <svg v-else width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></svg>
            <RouterLink :to="retour" class="photo__bouton photo__bouton--gauche seulement-mobile" :aria-label="t('commun.retour')">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </RouterLink>
            <RouterLink
              v-if="article.peutModifier"
              :to="{ name: 'article-modifier', params: { id: article.id } }"
              class="photo__bouton photo__bouton--droite seulement-mobile"
              :aria-label="t('article.modifier')"
            >
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
            </RouterLink>
            <span v-if="article.dateScan" class="badge badge--blanc photo__source">{{ t('article.photoScan', { date: formatDate(new Date(article.dateScan)) }) }}</span>
          </div>
          <div v-if="article.photos.length > 1" class="indicateurs">
            <button
              v-for="(photo, index) in article.photos"
              :key="photo"
              type="button"
              class="indicateur"
              :class="{ 'indicateur--actif': index === indexPhoto }"
              :aria-label="t('article.photo', { n: index + 1 })"
              :aria-pressed="index === indexPhoto"
              @click="indexPhoto = index"
            ></button>
          </div>
        </div>

        <div class="informations">
          <div class="titre">
            <div class="entete__textes">
              <h1 class="titre-page titre__nom">{{ article.titre }}</h1>
              <span class="texte-secondaire">{{ article.infos }}</span>
            </div>
            <span class="badge" :class="{ 'badge--plein': article.statut === 'DISPONIBLE' }">{{ t(`article.statuts.${article.statut}`) }}</span>
          </div>

          <RouterLink v-if="ajouteParUnAutre" :to="{ name: 'membre', params: { pseudo: article.proprietaire.pseudo } }" class="carte carte--rangee auteur">
            <AvatarMembre :membre="article.proprietaire" :taille="32" />
            <span class="entete__textes">
              <span class="texte-secondaire">{{ t('article.ajoutePar') }}</span>
              <b>{{ article.proprietaire.pseudo }}</b>
            </span>
          </RouterLink>

          <!-- Un visiteur ou un abonné ne voit jamais où l'article est rangé -->
          <div v-if="article.voitEmplacement" class="carte carte--rangee emplacement">
            <span class="emplacement__icone">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
            </span>
            <span class="entete__textes">
              <span class="texte-secondaire">{{ t('article.rangeDans') }}</span>
              <b class="emplacement__chemin">{{ chemin }}</b>
            </span>
          </div>

          <!-- Autre statut : prêté ou en vente -->
          <RouterLink v-if="article.pretEnCours" :to="{ name: 'prets' }" class="bandeau" :class="{ 'bandeau--retard': article.pretEnCours.joursRestants < 0 }">
            <span class="entete__textes">
              <b>{{ t('article.preteA', { nom: article.pretEnCours.emprunteur ? `@${article.pretEnCours.emprunteur.pseudo}` : article.pretEnCours.emprunteurNom }) }}</b>
              <span class="bandeau__detail">
                {{ t('article.depuis', { date: jourCourt(article.pretEnCours.datePret) }) }}<template v-if="article.pretEnCours.dateRetourPrevue"> · {{ t('article.retourPrevu', { date: jourCourt(article.pretEnCours.dateRetourPrevue) }) }}</template>
              </span>
            </span>
            <span class="badge badge--blanc">{{ echeance(article.pretEnCours.joursRestants) }}</span>
          </RouterLink>
          <RouterLink v-if="article.annonce" :to="{ name: 'annonce', params: { id: article.annonce.id } }" class="bandeau">
            <span class="entete__textes">
              <b>{{ t(article.annonce.statut === 'RESERVEE' ? 'article.reserve' : 'article.enVente') }} · {{ formatPrix(article.annonce.prix) }}</b>
            </span>
            <span class="bandeau__lien">{{ t('article.voirAnnonce') }}</span>
          </RouterLink>
          <p v-if="erreurPret" class="message-erreur" role="alert">{{ erreurPret }}</p>

          <dl class="proprietes">
            <div class="propriete">
              <dt class="propriete__libelle">{{ t('article.categorie') }}</dt>
              <dd class="propriete__valeur">{{ article.categorieChemin }}</dd>
            </div>
            <div v-if="article.couleur" class="propriete">
              <dt class="propriete__libelle">{{ t('article.couleur') }}</dt>
              <dd class="propriete__valeur couleur">
                <span class="pastille-couleur" :style="{ background: article.couleur.codeHex }"></span>{{ article.couleur.nom }}
              </dd>
            </div>
            <div v-if="article.voitEmplacement" class="propriete propriete--etiquettes">
              <dt class="propriete__libelle">{{ t('article.etiquettes') }}</dt>
              <dd class="etiquettes">
                <span v-for="etiquette in article.etiquettes" :key="etiquette.id" class="puce puce--petite">{{ etiquette.nom }}</span>
                <span v-if="!article.etiquettes.length && !article.peutModifier" class="texte-secondaire">{{ t('article.aucuneEtiquette') }}</span>
                <RouterLink v-if="article.peutModifier" :to="{ name: 'article-modifier', params: { id: article.id } }" class="lien">{{ t('article.ajouterEtiquette') }}</RouterLink>
              </dd>
            </div>
          </dl>

          <label v-if="article.peutChangerConfidentialite" class="carte carte--grise carte--rangee personnel">
            <span class="entete__textes">
              <b class="personnel__titre">{{ t('article.personnel') }}</b>
              <span class="texte-secondaire">{{ t('article.personnelAide') }}</span>
            </span>
            <input :checked="article.estPersonnel" type="checkbox" role="switch" class="interrupteur" @change="changerConfidentialite($event.target.checked)" />
          </label>
          <p v-if="erreurConfidentialite" class="message-erreur" role="alert">{{ erreurConfidentialite }}</p>

          <BlocCommentaires
            v-if="!article.estPersonnel"
            :key="article.id"
            class="commentaires-fiche"
            :source="`/articles/${article.id}`"
            :peut-commenter="article.peutCommenter"
            :auteur="article.proprietaire.pseudo"
          />

          <!-- En bas de l'écran sur téléphone ; juste après les informations sur ordinateur -->
          <div v-if="article.peutModifier" class="barre-actions barre-actions--dans-page actions-fiche">
            <button v-if="article.pretEnCours" type="button" class="bouton bouton--contour bouton--flexible" @click="marquerRendu">{{ t('article.marquerRendu') }}</button>
            <RouterLink v-else-if="peutPreter" :to="{ name: 'article-preter', params: { id: article.id } }" class="bouton bouton--contour bouton--flexible">{{ t('article.preter') }}</RouterLink>
            <RouterLink v-if="article.annonce" :to="{ name: 'annonce', params: { id: article.annonce.id } }" class="bouton bouton--flexible">{{ t('article.voirAnnonce') }}</RouterLink>
            <!-- Seul celui qui a ajouté l'article le vend ; un article personnel ou prêté ne peut pas être mis en vente -->
            <RouterLink v-else-if="peutVendre" :to="{ name: 'article-vendre', params: { id: article.id } }" class="bouton bouton--flexible">{{ t('article.mettreEnVente') }}</RouterLink>
          </div>
        </div>
      </div>
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
/* Téléphone : la photo occupe le haut de l'écran, bord à bord */
.fiche {
  padding-top: 0;
}

.galerie {
  margin: 0 -16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.photo {
  position: relative;
  height: 270px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
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

.photo__bouton {
  position: absolute;
  top: 14px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--couleur-photo-bouton);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo__bouton--gauche {
  left: 14px;
}

.photo__bouton--droite {
  right: 14px;
}

.photo__source {
  position: absolute;
  left: 14px;
  bottom: 12px;
}

/* Points de la galerie : sur la photo en bas à droite sur téléphone, sous la photo sur ordinateur */
.indicateurs {
  position: absolute;
  right: 30px;
  top: 252px;
  display: flex;
  gap: 5px;
}

.galerie {
  position: relative;
}

.indicateur {
  width: 8px;
  height: 8px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--couleur-photo-indicateur);
  cursor: pointer;
}

.indicateur--actif {
  background: var(--couleur-primaire);
}

.informations {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.titre {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.titre .badge {
  align-self: flex-start;
  margin-top: 4px;
}

.auteur,
.auteur:hover {
  color: var(--couleur-texte);
}

.emplacement__icone {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 10px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.emplacement__chemin {
  font-size: 14px;
}

.bandeau {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  font-size: 14px;
}

.bandeau:hover {
  background: var(--couleur-primaire-fonce);
  color: var(--couleur-sur-primaire);
}

.bandeau--retard {
  background: var(--couleur-erreur);
}

.bandeau__detail {
  font-size: 12px;
  opacity: 0.9;
}

.bandeau__lien {
  font-size: 13px;
  font-weight: 700;
  text-decoration: underline;
}

.proprietes {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.proprietes dd {
  margin: 0;
}

.propriete--etiquettes {
  align-items: flex-start;
}

.propriete--etiquettes .propriete__libelle {
  padding-top: 5px;
}

.couleur {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pastille-couleur {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 20, 22, 0.15);
}

.etiquettes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.personnel {
  cursor: pointer;
}

.personnel__titre {
  font-size: 14px;
}

/* Ordinateur : photo carrée à gauche, informations et actions à droite */
@media (min-width: 1024px) {
  .fiche {
    padding-top: 28px;
  }

  .galerie {
    margin: 0;
  }

  .photo {
    height: auto;
    aspect-ratio: 1 / 1;
    border-radius: 14px;
  }

  .photo__source {
    left: 12px;
    bottom: 12px;
  }

  .indicateurs {
    position: static;
    justify-content: center;
  }

  .indicateur {
    background: var(--couleur-bordure);
  }

  .indicateur--actif {
    background: var(--couleur-primaire);
  }

  .titre__nom {
    font-size: 26px;
  }

  .actions-fiche {
    order: 1;
  }

  .commentaires-fiche {
    order: 2;
  }
}

/* Téléphone : la barre colle au bas de la page, qui finit sous elle */
.actions-fiche {
  margin-top: auto;
}
</style>
