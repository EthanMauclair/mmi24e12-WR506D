<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import BarresHorizontales from '@/components/stats/BarresHorizontales.vue'
import GraphiqueColonnes from '@/components/stats/GraphiqueColonnes.vue'
import TuileChiffre from '@/components/stats/TuileChiffre.vue'
import { api } from '@/services/api'
import { formatDate } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()

const stats = ref(null)
const erreur = ref('')

/** Code d'une répartition (famille, statut, mode d'ajout) vers son libellé traduit */
const repartition = (lignes, prefixe) => lignes.map((ligne) => ({ cle: ligne.code, libelle: t(`${prefixe}.${ligne.code}`), valeur: ligne.nombre }))

const mois = computed(() =>
  (stats.value?.ajoutsParMois ?? []).map(({ mois: valeur, nombre }) => {
    const [annee, numero] = valeur.split('-').map(Number)
    const date = new Date(annee, numero - 1, 1)
    return { cle: valeur, libelle: formatDate(date, { month: 'long', year: 'numeric' }), libelleAxe: formatDate(date, { month: 'short' }), valeur: nombre }
  }),
)

onMounted(async () => {
  try {
    stats.value = await api('/moi/statistiques')
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('demandes.retourProfil')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <div class="entete__textes">
          <h1 class="titre-page">{{ t('espace.titre') }}</h1>
          <span class="texte-secondaire">{{ t('espace.sousTitre') }}</span>
        </div>
      </header>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="!stats" class="etat">{{ t('espace.chargement') }}</p>

      <template v-else>
        <div class="tuiles">
          <TuileChiffre :libelle="t('espace.articles')" :valeur="stats.articles.total" :detail="t('espace.dontPersonnels', stats.articles.personnels)" />
          <TuileChiffre :libelle="t('espace.ventes')" :valeur="formatPrix(stats.ventes.montant)" :detail="`${t('espace.vendus', stats.ventes.vendues)}, ${t('espace.enVente', stats.ventes.enCours)}`" />
          <TuileChiffre
            :libelle="t('espace.pretsEnCours')"
            :valeur="stats.prets.enCours"
            :detail="stats.prets.enRetard ? t('espace.enRetard', stats.prets.enRetard) : t('espace.rendus', stats.prets.rendus)"
            :alerte="stats.prets.enRetard > 0"
          />
          <TuileChiffre :libelle="t('espace.abonnes')" :valeur="stats.social.abonnes" />
        </div>

        <div class="sections">
          <GraphiqueColonnes :titre="t('espace.ajoutsParMois')" :points="mois" />
          <BarresHorizontales :titre="t('espace.parFamille')" :parts="false" :elements="repartition(stats.articles.parFamille, 'stats.familles')" :vide="t('espace.aucunArticle')" />
          <BarresHorizontales :titre="t('espace.modesAjout')" :elements="repartition(stats.articles.parMode, 'stats.modes')" :vide="t('espace.aucunArticle')" />

          <section class="pile">
            <h2 class="titre-section">{{ t('espace.activiteSociale') }}</h2>
            <div class="grille-3">
              <TuileChiffre :libelle="t('espace.tenues')" :valeur="stats.social.tenues" />
              <TuileChiffre :libelle="t('espace.likesRecus')" :valeur="stats.social.likesRecus" />
              <TuileChiffre :libelle="t('espace.commentairesRecus')" :valeur="stats.social.commentairesRecus" />
            </div>
          </section>

          <BarresHorizontales :titre="t('espace.parStatut')" :elements="repartition(stats.articles.parStatut, 'article.statuts')" :vide="t('espace.aucunArticle')" />
          <BarresHorizontales
            v-if="stats.articles.parLogement.length > 1"
            :titre="t('espace.parLogement')"
            :elements="stats.articles.parLogement.map((l) => ({ cle: l.nom, libelle: l.nom, valeur: l.nombre }))"
          />
          <BarresHorizontales
            :titre="t('espace.couleurs')"
            :parts="false"
            :elements="stats.couleurs.map((c) => ({ cle: c.nom, libelle: c.nom, valeur: c.nombre, pastille: c.codeHex }))"
            :vide="t('espace.aucunVetement')"
          />
          <BarresHorizontales :titre="t('espace.marques')" :parts="false" :elements="stats.marques.map((m) => ({ cle: m.nom, libelle: m.nom, valeur: m.nombre }))" :vide="t('espace.aucuneMarque')" />
        </div>
      </template>
    </main>

    <BarreNavigation actif="profil" ordinateur-seulement />
  </div>
</template>

<style scoped>
.tuiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.sections {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (min-width: 1024px) {
  .tuiles {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .sections {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px;
    align-items: start;
  }
}
</style>
