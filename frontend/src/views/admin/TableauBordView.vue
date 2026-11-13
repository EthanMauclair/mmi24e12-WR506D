<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BarresHorizontales from '@/components/stats/BarresHorizontales.vue'
import GraphiqueColonnes from '@/components/stats/GraphiqueColonnes.vue'
import TuileChiffre from '@/components/stats/TuileChiffre.vue'
import { localeIntl } from '@/i18n'
import { api } from '@/services/api'
import { formatDate } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()

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
  return pourcentage(scans, total)
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
  <div class="admin-pile">
    <div>
      <h1 class="admin-titre">{{ t('admin.tableau.titre') }}</h1>
      <p class="admin-sous-titre">{{ t('admin.tableau.sousTitre') }}</p>
    </div>

    <p v-if="erreur" class="admin-message admin-message--erreur" role="alert">{{ erreur }}</p>
    <p v-else-if="!indicateurs" class="admin-etat">{{ t('commun.chargement') }}</p>

    <template v-else>
      <section>
        <h2 class="admin-section-titre">{{ t('admin.tableau.membres') }}</h2>
        <div class="admin-tuiles">
          <TuileChiffre :libelle="t('admin.tableau.membresInscrits')" :valeur="indicateurs.membres.total" :detail="t('admin.tableau.admins', indicateurs.membres.admins)" />
          <TuileChiffre :libelle="t('admin.tableau.nouveaux30j')" :valeur="indicateurs.membres.nouveaux30j" :detail="t('admin.tableau.dont7j', { n: indicateurs.membres.nouveaux7j })" />
          <TuileChiffre
            :libelle="t('admin.tableau.actifs30j')"
            :valeur="indicateurs.membres.actifs30j"
            :detail="t('admin.tableau.partMembres', { part: pourcentage(indicateurs.membres.actifs30j, indicateurs.membres.total) })"
          />
          <TuileChiffre :libelle="t('admin.tableau.bloques')" :valeur="indicateurs.membres.bloques" />
        </div>
      </section>

      <div class="admin-colonnes">
        <section class="admin-carte">
          <GraphiqueColonnes :titre="t('admin.tableau.inscriptionsParJour')" :points="parJour(indicateurs.series.inscriptions)" />
        </section>
        <section class="admin-carte">
          <GraphiqueColonnes :titre="t('admin.tableau.articlesParJour')" :points="parJour(indicateurs.series.articles)" />
        </section>
      </div>

      <section>
        <h2 class="admin-section-titre">{{ t('admin.tableau.contenu') }}</h2>
        <div class="admin-tuiles">
          <TuileChiffre :libelle="t('admin.tableau.articles')" :valeur="indicateurs.contenu.total" :detail="t('admin.tableau.ajouts30j', indicateurs.contenu.ajouts30j)" />
          <TuileChiffre :libelle="t('admin.tableau.partScan')" :valeur="partScan" :detail="t('admin.tableau.partScanDetail')" />
          <TuileChiffre :libelle="t('admin.tableau.logements')" :valeur="indicateurs.contenu.logements" />
          <TuileChiffre :libelle="t('admin.tableau.personnels')" :valeur="indicateurs.contenu.personnels" :detail="t('admin.tableau.personnelsDetail')" />
        </div>
      </section>

      <div class="admin-colonnes">
        <section class="admin-carte">
          <BarresHorizontales :titre="t('admin.tableau.parFamille')" :elements="repartition(indicateurs.contenu.parFamille, 'stats.familles')" :vide="t('admin.tableau.aucunArticle')" />
          <BarresHorizontales :titre="t('admin.tableau.parMode')" :elements="repartition(indicateurs.contenu.parMode, 'stats.modes')" :vide="t('admin.tableau.aucunArticle')" />
          <BarresHorizontales :titre="t('admin.tableau.parStatut')" :elements="repartition(indicateurs.contenu.parStatut, 'article.statuts')" :vide="t('admin.tableau.aucunArticle')" />
        </section>
        <section class="admin-carte">
          <BarresHorizontales :titre="t('admin.tableau.topCategories')"
            :parts="false" :elements="top(indicateurs.tops.categories)" :vide="t('admin.tableau.aucunArticle')" />
          <BarresHorizontales :titre="t('admin.tableau.topMarques')"
            :parts="false" :elements="top(indicateurs.tops.marques)" :vide="t('admin.tableau.aucuneMarque')" />
        </section>
      </div>

      <section>
        <h2 class="admin-section-titre">{{ t('admin.tableau.marche') }}</h2>
        <div class="admin-tuiles">
          <TuileChiffre :libelle="t('admin.tableau.volumeVentes')" :valeur="formatPrix(indicateurs.marche.volumeVentes)" :detail="t('admin.tableau.ventes', indicateurs.marche.vendues)" />
          <TuileChiffre :libelle="t('admin.tableau.prixMoyen')" :valeur="formatPrix(indicateurs.marche.prixMoyenVente)" />
          <TuileChiffre :libelle="t('admin.tableau.annoncesEnLigne')" :valeur="indicateurs.marche.enLigne" :detail="t('admin.tableau.reservees', indicateurs.marche.reservees)" />
          <TuileChiffre :libelle="t('admin.tableau.conversations')" :valeur="indicateurs.marche.conversations" :detail="t('admin.tableau.messages30j', indicateurs.marche.messages30j)" />
        </div>
      </section>

      <section>
        <h2 class="admin-section-titre">{{ t('admin.tableau.pretsSocial') }}</h2>
        <div class="admin-tuiles">
          <TuileChiffre
            :libelle="t('admin.tableau.pretsEnCours')"
            :valeur="indicateurs.prets.enCours"
            :detail="indicateurs.prets.enRetard ? t('admin.tableau.enRetard', indicateurs.prets.enRetard) : t('admin.tableau.pretsTotal', indicateurs.prets.total)"
            :alerte="indicateurs.prets.enRetard > 0"
          />
          <TuileChiffre :libelle="t('admin.tableau.tenues')" :valeur="indicateurs.social.tenues" :detail="t('admin.tableau.publiques', indicateurs.social.tenuesPubliques)" />
          <TuileChiffre :libelle="t('admin.tableau.likes')" :valeur="indicateurs.social.likes" />
          <TuileChiffre :libelle="t('admin.tableau.commentaires')" :valeur="indicateurs.social.commentaires" />
          <TuileChiffre
            :libelle="t('admin.tableau.abonnements')"
            :valeur="indicateurs.social.abonnements"
            :detail="t('admin.tableau.demandesEnAttente', indicateurs.social.demandesEnAttente)"
          />
        </div>
      </section>
    </template>
  </div>
</template>
