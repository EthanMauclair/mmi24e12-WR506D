<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
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

const lienCible = (cible) => (cible.type === 'ARTICLE' ? { name: 'article', params: { id: cible.id } } : { name: 'tenue', params: { id: cible.id } })

/**
 * Contenus de l'onglet sous une même forme : auteur, contenu, « sur » quoi, date et action de modération.
 */
const lignes = computed(() => {
  if (!contenus.value) {
    return []
  }
  if (onglet.value === 'commentaires') {
    return contenus.value.commentaires.map((commentaire) => ({
      id: commentaire.id,
      auteur: commentaire.auteur,
      contenu: commentaire.contenu,
      sur: { titre: commentaire.cible.titre, lien: lienCible(commentaire.cible) },
      date: commentaire.createdAt,
      action: t('commun.supprimer'),
      faire: () => retirer('commentaires', commentaire, t('admin.moderation.confirmerCommentaire', { auteur: commentaire.auteur }), t('admin.moderation.commentaireSupprime')),
    }))
  }
  if (onglet.value === 'annonces') {
    return contenus.value.annonces.map((annonce) => ({
      id: annonce.id,
      auteur: annonce.vendeur,
      contenu: `${formatPrix(annonce.prix)} · ${t(`annonce.statuts.${annonce.statut}`)}${annonce.description ? ` · ${annonce.description}` : ''}`,
      sur: { titre: annonce.titre, lien: { name: 'annonce', params: { id: annonce.id } } },
      date: annonce.createdAt,
      action: t('admin.moderation.retirerMarche'),
      faire: () => retirer('annonces', annonce, t('admin.moderation.confirmerAnnonce', { titre: annonce.titre }), t('admin.moderation.annonceRetiree')),
    }))
  }
  return contenus.value.tenues.map((tenue) => ({
    id: tenue.id,
    auteur: tenue.createur,
    contenu: [t('admin.moderation.pieces', tenue.nbArticles), t('admin.moderation.likes', tenue.nbLikes), tenue.description].filter(Boolean).join(' · '),
    sur: { titre: tenue.titre, lien: { name: 'tenue', params: { id: tenue.id } } },
    date: tenue.createdAt,
    action: t('admin.moderation.supprimerTenue'),
    faire: () => retirer('tenues', tenue, t('admin.moderation.confirmerTenue', { titre: tenue.titre }), t('admin.moderation.tenueSupprimee')),
  }))
})

onMounted(charger)
</script>

<template>
  <div class="pile moderation">
    <header class="entete entete--onglets">
      <h1 class="titre-page seulement-ordinateur">{{ t('admin.moderation.titre') }}</h1>
      <div role="tablist" class="segments onglets" :aria-label="t('admin.moderation.titre')">
        <button
          v-for="nom in ONGLETS"
          :key="nom"
          type="button"
          role="tab"
          class="segment"
          :class="{ 'segment--actif': onglet === nom }"
          :aria-selected="onglet === nom"
          @click="onglet = nom"
        >
          {{ t(`admin.moderation.${nom}`) }}<template v-if="contenus"> · {{ contenus[nom].length }}</template>
        </button>
      </div>
    </header>

    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="bandeau-info" role="status">{{ succes }}</p>
    <p v-if="!contenus" class="etat">{{ t('commun.chargement') }}</p>
    <p v-else-if="lignes.length === 0" class="etat">{{ t('admin.moderation.rien') }}</p>

    <template v-else>
      <!-- Téléphone : une carte par contenu -->
      <article v-for="ligne in lignes" :key="ligne.id" class="carte contenu seulement-mobile">
        <div class="contenu__entete">
          <AvatarMembre :membre="{ pseudo: ligne.auteur }" :taille="32" />
          <b class="contenu__titre">
            @{{ ligne.auteur }} · {{ t('admin.moderation.sur') }} « <RouterLink :to="ligne.sur.lien">{{ ligne.sur.titre }}</RouterLink> »
          </b>
          <span class="texte-secondaire">{{ ilYA(ligne.date) }}</span>
        </div>
        <span class="texte-secondaire contenu__texte">{{ ligne.contenu }}</span>
        <button type="button" class="bouton bouton--contour bouton--petit contenu__action" @click="ligne.faire()">{{ ligne.action }}</button>
      </article>

      <!-- Ordinateur : tableau -->
      <div class="defilement seulement-ordinateur">
        <table class="tableau-admin">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.moderation.auteur') }}</th>
              <th scope="col">{{ t('admin.moderation.contenu') }}</th>
              <th scope="col">{{ t('admin.moderation.surColonne') }}</th>
              <th scope="col">{{ t('admin.moderation.date') }}</th>
              <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ligne in lignes" :key="ligne.id">
              <td><b>@{{ ligne.auteur }}</b></td>
              <td class="cellule-texte">{{ ligne.contenu }}</td>
              <td><RouterLink :to="ligne.sur.lien">{{ ligne.sur.titre }}</RouterLink></td>
              <td class="texte-secondaire">{{ ilYA(ligne.date) }}</td>
              <td class="actions"><button type="button" class="bouton bouton--contour bouton--petit" @click="ligne.faire()">{{ ligne.action }}</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.moderation {
  gap: 14px;
}

.entete--onglets {
  flex-wrap: wrap;
}

.onglets {
  flex-basis: 100%;
}

.contenu {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.contenu__entete {
  display: flex;
  align-items: center;
  gap: 10px;
}

.contenu__titre {
  flex: 1;
  min-width: 0;
  font-size: 13px;
  overflow-wrap: anywhere;
}

.contenu__texte {
  overflow-wrap: anywhere;
}

.contenu__action {
  align-self: flex-end;
}

.cellule-texte {
  max-width: 380px;
  overflow-wrap: anywhere;
}

@media (min-width: 1024px) {
  .entete--onglets {
    flex-wrap: nowrap;
  }

  .entete--onglets .titre-page {
    flex: 1;
  }

  .onglets {
    flex-basis: 420px;
    flex-shrink: 0;
  }
}
</style>
