<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { api } from '@/services/api'
import { ilYA } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()

const ONGLETS = ['commentaires', 'annonces', 'tenues']

const contenus = ref(null)
const onglet = ref('commentaires')
const erreur = ref('')
const succes = ref('')

async function charger() {
  try {
    contenus.value = await api('/admin/moderation')
  } catch (e) {
    erreur.value = e.message
  }
}

async function retirer(type, element, confirmation, message) {
  if (!window.confirm(confirmation)) {
    return
  }
  erreur.value = ''
  succes.value = ''
  try {
    await api(`/admin/moderation/${type}/${element.id}`, { methode: 'DELETE' })
    contenus.value[type] = contenus.value[type].filter((autre) => autre.id !== element.id)
    succes.value = message
  } catch (e) {
    erreur.value = Object.values(e.violations ?? {})[0] ?? e.message
  }
}

const supprimerCommentaire = (commentaire) =>
  retirer('commentaires', commentaire, t('admin.moderation.confirmerCommentaire', { auteur: commentaire.auteur }), t('admin.moderation.commentaireSupprime'))
const retirerAnnonce = (annonce) => retirer('annonces', annonce, t('admin.moderation.confirmerAnnonce', { titre: annonce.titre }), t('admin.moderation.annonceRetiree'))
const supprimerTenue = (tenue) => retirer('tenues', tenue, t('admin.moderation.confirmerTenue', { titre: tenue.titre }), t('admin.moderation.tenueSupprimee'))

const lienCible = (cible) => (cible.type === 'ARTICLE' ? { name: 'article', params: { id: cible.id } } : { name: 'tenue', params: { id: cible.id } })

onMounted(charger)
</script>

<template>
  <div class="admin-pile">
    <div>
      <h1 class="admin-titre">{{ t('admin.moderation.titre') }}</h1>
      <p class="admin-sous-titre">{{ t('admin.moderation.sousTitre') }}</p>
    </div>

    <div role="tablist" class="admin-segments" :aria-label="t('admin.moderation.titre')">
      <button
        v-for="nom in ONGLETS"
        :key="nom"
        type="button"
        role="tab"
        class="admin-segment"
        :class="{ 'admin-segment--actif': onglet === nom }"
        :aria-selected="onglet === nom"
        @click="onglet = nom"
      >
        {{ t(`admin.moderation.${nom}`) }}<template v-if="contenus"> · {{ contenus[nom].length }}</template>
      </button>
    </div>

    <p v-if="erreur" class="admin-message admin-message--erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="admin-message admin-message--succes" role="status">{{ succes }}</p>
    <p v-if="!contenus" class="admin-etat">{{ t('commun.chargement') }}</p>

    <section v-else class="admin-carte">
      <p v-if="contenus[onglet].length === 0" class="admin-etat">{{ t('admin.moderation.rien') }}</p>

      <ul v-else-if="onglet === 'commentaires'" class="liste">
        <li v-for="commentaire in contenus.commentaires" :key="commentaire.id" class="element">
          <div class="element__corps">
            <p class="element__texte">{{ commentaire.contenu }}</p>
            <p class="admin-secondaire">
              {{ t('admin.moderation.parSur', { auteur: `@${commentaire.auteur}` }) }}
              <RouterLink :to="lienCible(commentaire.cible)">{{ commentaire.cible.titre }}</RouterLink>
              · {{ ilYA(commentaire.createdAt) }}
            </p>
          </div>
          <button type="button" class="admin-bouton admin-bouton--danger" @click="supprimerCommentaire(commentaire)">{{ t('commun.supprimer') }}</button>
        </li>
      </ul>

      <ul v-else-if="onglet === 'annonces'" class="liste">
        <li v-for="annonce in contenus.annonces" :key="annonce.id" class="element">
          <span class="vignette">
            <img v-if="annonce.photo" :src="annonce.photo" alt="" />
          </span>
          <div class="element__corps">
            <RouterLink :to="{ name: 'annonce', params: { id: annonce.id } }" class="element__titre">{{ annonce.titre }}</RouterLink>
            <p class="admin-secondaire">
              {{ formatPrix(annonce.prix) }} · @{{ annonce.vendeur }} · {{ t(`annonce.statuts.${annonce.statut}`) }} · {{ ilYA(annonce.createdAt) }}
            </p>
            <p v-if="annonce.description" class="element__texte element__texte--court">{{ annonce.description }}</p>
          </div>
          <button type="button" class="admin-bouton admin-bouton--danger" @click="retirerAnnonce(annonce)">{{ t('admin.moderation.retirer') }}</button>
        </li>
      </ul>

      <ul v-else class="liste">
        <li v-for="tenue in contenus.tenues" :key="tenue.id" class="element">
          <div class="element__corps">
            <RouterLink :to="{ name: 'tenue', params: { id: tenue.id } }" class="element__titre">{{ tenue.titre }}</RouterLink>
            <p class="admin-secondaire">
              @{{ tenue.createur }} · {{ t('admin.moderation.pieces', tenue.nbArticles) }} · {{ t('admin.moderation.likes', tenue.nbLikes) }} · {{ ilYA(tenue.createdAt) }}
            </p>
            <p v-if="tenue.description" class="element__texte element__texte--court">{{ tenue.description }}</p>
          </div>
          <button type="button" class="admin-bouton admin-bouton--danger" @click="supprimerTenue(tenue)">{{ t('commun.supprimer') }}</button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.liste {
  margin: 0;
  padding: 0;
  list-style: none;
}

.element {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--couleur-separateur);
}

.element:first-child {
  padding-top: 0;
}

.element:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.element__corps {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.element__corps p {
  margin: 0;
}

.element__titre {
  font-weight: 700;
}

.element__texte {
  font-size: 14px;
  overflow-wrap: anywhere;
}

.element__texte--court {
  color: var(--couleur-texte-secondaire);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.vignette {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  border-radius: 12px;
  overflow: hidden;
  background: var(--couleur-pastel-sable);
}

.vignette img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
