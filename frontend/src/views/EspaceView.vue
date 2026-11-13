<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BarresHorizontales from '@/components/stats/BarresHorizontales.vue'
import GraphiqueColonnes from '@/components/stats/GraphiqueColonnes.vue'
import TuileChiffre from '@/components/stats/TuileChiffre.vue'
import { api } from '@/services/api'
import { formatDate } from '@/utils/dates'
import { formatPrix } from '@/utils/prix'

const { t } = useI18n()
const router = useRouter()

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
    <main class="contenu">
      <header class="entete">
        <button type="button" class="bouton-icone" :aria-label="t('commun.retour')" @click="router.back()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <div>
          <h1 class="entete__titre">{{ t('espace.titre') }}</h1>
          <p class="entete__sous-titre">{{ t('espace.sousTitre') }}</p>
        </div>
      </header>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-else-if="!stats" class="etat">{{ t('espace.chargement') }}</p>

      <template v-else>
        <div class="tuiles">
          <TuileChiffre :libelle="t('espace.articles')" :valeur="stats.articles.total" :detail="t('espace.dontPersonnels', stats.articles.personnels)" />
          <TuileChiffre :libelle="t('espace.ventes')" :valeur="formatPrix(stats.ventes.montant)" :detail="`${t('espace.vendus', stats.ventes.vendues)} · ${t('espace.enVente', stats.ventes.enCours)}`" />
          <TuileChiffre
            :libelle="t('espace.pretsEnCours')"
            :valeur="stats.prets.enCours"
            :detail="stats.prets.enRetard ? t('espace.enRetard', stats.prets.enRetard) : t('espace.rendus', stats.prets.rendus)"
            :alerte="stats.prets.enRetard > 0"
          />
          <TuileChiffre :libelle="t('espace.abonnes')" :valeur="stats.social.abonnes" :detail="`${t('espace.abonnementsN', stats.social.abonnements)} · ${t('espace.amisN', stats.social.amis)}`" />
        </div>

        <section class="carte">
          <GraphiqueColonnes :titre="t('espace.ajoutsParMois')" :points="mois" />
        </section>

        <section class="carte">
          <BarresHorizontales :titre="t('espace.parFamille')" :elements="repartition(stats.articles.parFamille, 'stats.familles')" :vide="t('espace.aucunArticle')" />
          <BarresHorizontales :titre="t('espace.parStatut')" :elements="repartition(stats.articles.parStatut, 'article.statuts')" :vide="t('espace.aucunArticle')" />
          <BarresHorizontales
            v-if="stats.articles.parLogement.length > 1"
            :titre="t('espace.parLogement')"
            :elements="stats.articles.parLogement.map((l) => ({ cle: l.nom, libelle: l.nom, valeur: l.nombre }))"
          />
        </section>

        <section class="carte">
          <BarresHorizontales
            :titre="t('espace.couleurs')"
            :parts="false"
            :elements="stats.couleurs.map((c) => ({ cle: c.nom, libelle: c.nom, valeur: c.nombre, pastille: c.codeHex }))"
            :vide="t('espace.aucunVetement')"
          />
          <BarresHorizontales :titre="t('espace.marques')"
            :parts="false" :elements="stats.marques.map((m) => ({ cle: m.nom, libelle: m.nom, valeur: m.nombre }))" :vide="t('espace.aucuneMarque')" />
          <BarresHorizontales :titre="t('espace.modesAjout')" :elements="repartition(stats.articles.parMode, 'stats.modes')" :vide="t('espace.aucunArticle')" />
        </section>

        <section class="carte">
          <h2 class="carte__titre">{{ t('espace.activiteSociale') }}</h2>
          <div class="tuiles">
            <TuileChiffre :libelle="t('espace.tenues')" :valeur="stats.social.tenues" />
            <TuileChiffre :libelle="t('espace.likesRecus')" :valeur="stats.social.likesRecus" />
            <TuileChiffre :libelle="t('espace.commentairesRecus')" :valeur="stats.social.commentairesRecus" />
            <TuileChiffre :libelle="t('espace.abonnements')" :valeur="stats.social.abonnements" />
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 16px 20px 32px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.entete__titre {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.entete__sous-titre {
  margin: 2px 0 0;
  font-size: 13px;
  color: var(--couleur-texte-secondaire);
}

.etat {
  margin: 24px 0;
  text-align: center;
  color: var(--couleur-texte-secondaire);
}

.message-erreur {
  margin: 0;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--couleur-erreur-fond);
  color: var(--couleur-erreur);
  font-size: 14px;
}

.tuiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.carte {
  padding: 16px;
  border-radius: 18px;
  border: 1px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.carte .tuiles :deep(.tuile) {
  background: var(--couleur-fond);
}

.carte__titre {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}
</style>
