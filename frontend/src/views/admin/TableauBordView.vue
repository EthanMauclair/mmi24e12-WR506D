<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BarresHorizontales from '@/components/stats/BarresHorizontales.vue'
import GraphiqueColonnes from '@/components/stats/GraphiqueColonnes.vue'
import TuileChiffre from '@/components/stats/TuileChiffre.vue'
import { localeIntl } from '@/i18n'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { formatDate } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()
const session = useSessionStore()

const indicateurs = ref(null)
const erreur = ref('')

const pourcentage = (part, total) => new Intl.NumberFormat(localeIntl(), { style: 'percent' }).format(total ? part / total : 0)
const repartition = (lignes, prefixe) => lignes.map((ligne) => ({ cle: ligne.code, libelle: t(`${prefixe}.${ligne.code}`), valeur: ligne.nombre }))
const top = (lignes) => lignes.map((ligne) => ({ cle: ligne.nom, libelle: ligne.nom, valeur: ligne.nombre }))

const parJour = (serie) =>
  serie.map(({ jour, nombre }) => {
    const [annee, mois, numero] = jour.split('-').map(Number)
    const date = new Date(annee, mois - 1, numero)
    return { cle: jour, libelle: formatDate(date, { weekday: 'long', day: 'numeric', month: 'long' }), libelleAxe: formatDate(date), valeur: nombre }
  })

// Part des articles ajoutés par un scan (photo analysée par l'IA ou code-barres) : mesure l'usage de la fonction phare
const partScan = computed(() => {
  const { parMode, total } = indicateurs.value.contenu
  const scans = parMode.filter((mode) => mode.code !== 'MANUEL').reduce((somme, mode) => somme + mode.nombre, 0)
  return total ? scans / total : 0
})

onMounted(async () => {
  try {
    indicateurs.value = await api('/admin/indicateurs')
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="pile tableau">
    <header class="entete seulement-ordinateur">
      <div class="entete__textes">
        <h1 class="titre-page">{{ t('admin.tableau.titre') }}</h1>
        <span class="texte-secondaire">{{ t('admin.tableau.sousTitre') }}</span>
      </div>
      <span class="badge badge--plein">{{ t(session.utilisateur.estSuperAdmin ? 'admin.roles.SUPER_ADMIN' : 'admin.roles.ADMIN') }}</span>
    </header>

    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
    <p v-else-if="!indicateurs" class="etat">{{ t('commun.chargement') }}</p>

    <template v-else>
      <div class="tuiles">
        <TuileChiffre :libelle="t('admin.tableau.membresInscrits')" :valeur="indicateurs.membres.total" :detail="t('admin.tableau.admins', indicateurs.membres.admins)" />
        <TuileChiffre :libelle="t('admin.tableau.nouveaux30j')" :valeur="indicateurs.membres.nouveaux30j" :detail="t('admin.tableau.dont7j', { n: indicateurs.membres.nouveaux7j })" />
        <TuileChiffre :libelle="t('admin.tableau.actifs30j')" :valeur="indicateurs.membres.actifs30j" :detail="pourcentage(indicateurs.membres.actifs30j, indicateurs.membres.total)" />
        <TuileChiffre :libelle="t('admin.tableau.bloques')" :valeur="indicateurs.membres.bloques" />
      </div>

      <div class="sections">
        <GraphiqueColonnes :titre="t('admin.tableau.inscriptionsParJour')" :points="parJour(indicateurs.series.inscriptions)" />
        <GraphiqueColonnes :titre="t('admin.tableau.articlesParJour')" :points="parJour(indicateurs.series.articles)" />

        <section class="pile">
          <h2 class="titre-section">{{ t('admin.tableau.partScanTitre') }}</h2>
          <div class="part-scan" role="img" :aria-label="`${t('admin.tableau.partScanTitre')} : ${pourcentage(partScan, 1)}`">
            <span class="part-scan__piste"><span class="part-scan__remplissage" :style="{ width: `${partScan * 100}%` }"></span></span>
            <b class="texte-secondaire">{{ pourcentage(partScan, 1) }}</b>
          </div>
          <span class="texte-secondaire">{{ t('admin.tableau.partScanDetail') }}</span>
        </section>

        <BarresHorizontales :titre="t('admin.tableau.parFamille')" :parts="false" :elements="repartition(indicateurs.contenu.parFamille, 'stats.familles')" :vide="t('admin.tableau.aucunArticle')" />

        <section class="pile">
          <h2 class="titre-section">{{ t('admin.tableau.marcheActivite') }}</h2>
          <div class="grille-3">
            <TuileChiffre :libelle="t('admin.tableau.volumeVentes')" :valeur="formatPrix(indicateurs.marche.volumeVentes)" />
            <TuileChiffre :libelle="t('admin.tableau.prixMoyen')" :valeur="formatPrix(indicateurs.marche.prixMoyenVente)" />
            <TuileChiffre :libelle="t('admin.tableau.pretsRetard')" :valeur="indicateurs.prets.enRetard" />
          </div>
        </section>

        <section class="pile">
          <h2 class="titre-section">{{ t('admin.tableau.contenu') }}</h2>
          <div class="grille-3">
            <TuileChiffre :libelle="t('admin.tableau.articles')" :valeur="indicateurs.contenu.total" :detail="t('admin.tableau.ajouts30j', indicateurs.contenu.ajouts30j)" />
            <TuileChiffre :libelle="t('admin.tableau.logements')" :valeur="indicateurs.contenu.logements" />
            <TuileChiffre :libelle="t('admin.tableau.personnels')" :valeur="indicateurs.contenu.personnels" />
          </div>
        </section>

        <BarresHorizontales :titre="t('admin.tableau.parMode')" :elements="repartition(indicateurs.contenu.parMode, 'stats.modes')" :vide="t('admin.tableau.aucunArticle')" />
        <BarresHorizontales :titre="t('admin.tableau.parStatut')" :elements="repartition(indicateurs.contenu.parStatut, 'article.statuts')" :vide="t('admin.tableau.aucunArticle')" />
        <BarresHorizontales :titre="t('admin.tableau.topCategories')" :parts="false" :elements="top(indicateurs.tops.categories)" :vide="t('admin.tableau.aucunArticle')" />
        <BarresHorizontales :titre="t('admin.tableau.topMarques')" :parts="false" :elements="top(indicateurs.tops.marques)" :vide="t('admin.tableau.aucuneMarque')" />

        <section class="pile">
          <h2 class="titre-section">{{ t('admin.tableau.marche') }}</h2>
          <div class="grille-2">
            <TuileChiffre :libelle="t('admin.tableau.annoncesEnLigne')" :valeur="indicateurs.marche.enLigne" :detail="t('admin.tableau.reservees', indicateurs.marche.reservees)" />
            <TuileChiffre :libelle="t('admin.tableau.conversations')" :valeur="indicateurs.marche.conversations" :detail="t('admin.tableau.messages30j', indicateurs.marche.messages30j)" />
            <TuileChiffre :libelle="t('admin.tableau.ventesTitre')" :valeur="indicateurs.marche.vendues" />
            <TuileChiffre :libelle="t('admin.tableau.pretsEnCours')" :valeur="indicateurs.prets.enCours" :detail="t('admin.tableau.pretsTotal', indicateurs.prets.total)" />
          </div>
        </section>

        <section class="pile">
          <h2 class="titre-section">{{ t('admin.tableau.reseauSocial') }}</h2>
          <div class="grille-2">
            <TuileChiffre :libelle="t('admin.tableau.tenues')" :valeur="indicateurs.social.tenues" :detail="t('admin.tableau.publiques', indicateurs.social.tenuesPubliques)" />
            <TuileChiffre :libelle="t('admin.tableau.likes')" :valeur="indicateurs.social.likes" />
            <TuileChiffre :libelle="t('admin.tableau.commentaires')" :valeur="indicateurs.social.commentaires" />
            <TuileChiffre :libelle="t('admin.tableau.abonnements')" :valeur="indicateurs.social.abonnements" :detail="t('admin.tableau.demandesEnAttente', indicateurs.social.demandesEnAttente)" />
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tableau {
  gap: 14px;
}

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

.part-scan {
  display: flex;
  align-items: center;
  gap: 12px;
}

.part-scan__piste {
  flex: 1;
  height: 10px;
  border-radius: 5px;
  background: var(--couleur-piste);
  overflow: hidden;
}

.part-scan__remplissage {
  display: block;
  height: 100%;
  border-radius: 5px;
  background: var(--couleur-donnee);
}

@media (min-width: 1024px) {
  .tuiles {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .sections {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px;
    align-items: start;
  }
}
</style>
