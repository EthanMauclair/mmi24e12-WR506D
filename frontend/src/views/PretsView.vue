<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '@/services/api'
import { fondVignette } from '@/utils/couleurs'
import { echeance, jourCourt } from '@/utils/dates'

const { t } = useI18n()
const router = useRouter()

const prets = ref({ enCours: [], rendus: [] })
const onglet = ref('enCours')
const chargement = ref(true)
const erreur = ref('')

const affiches = computed(() => prets.value[onglet.value])

function emprunteur(pret) {
  return pret.emprunteur ? t('article.preteA', { nom: `@${pret.emprunteur.pseudo}` }) : t('prets.preteANom', { nom: pret.emprunteurNom })
}

async function marquerRendu(pret) {
  erreur.value = ''
  try {
    const rendu = await api(`/prets/${pret.id}/retour`, { methode: 'POST' })
    prets.value = {
      enCours: prets.value.enCours.filter((autre) => autre.id !== pret.id),
      rendus: [rendu, ...prets.value.rendus],
    }
  } catch (e) {
    erreur.value = e.violations.general ?? e.message
  }
}

async function supprimer(pret) {
  if (!window.confirm(t('prets.confirmerSuppression', { titre: pret.article.titre }))) {
    return
  }
  erreur.value = ''
  try {
    await api(`/prets/${pret.id}`, { methode: 'DELETE' })
    prets.value[onglet.value] = affiches.value.filter((autre) => autre.id !== pret.id)
  } catch (e) {
    erreur.value = e.message
  }
}

onMounted(async () => {
  try {
    prets.value = await api('/prets')
  } catch (e) {
    erreur.value = e.message
  } finally {
    chargement.value = false
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
        <h1 class="entete__titre">{{ t('profil.ongletPrets') }}</h1>
      </header>

      <div role="tablist" :aria-label="t('prets.statuts')" class="segments">
        <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'enCours' }" :aria-selected="onglet === 'enCours'" @click="onglet = 'enCours'">
          {{ t('prets.enCours') }} · {{ prets.enCours.length }}
        </button>
        <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'rendus' }" :aria-selected="onglet === 'rendus'" @click="onglet = 'rendus'">
          {{ t('prets.rendus') }} · {{ prets.rendus.length }}
        </button>
      </div>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">{{ t('prets.chargement') }}</p>
      <p v-else-if="affiches.length === 0" class="etat">
        <template v-if="onglet === 'enCours'">{{ t('prets.aucunEnCours') }}</template>
        <template v-else>{{ t('prets.aucunRendu') }}</template>
      </p>

      <article
        v-for="pret in affiches"
        :key="pret.id"
        class="pret"
        :class="{ 'pret--retard': pret.enCours && pret.joursRestants < 0 }"
      >
        <div class="pret__entete">
          <RouterLink :to="{ name: 'article', params: { id: pret.article.id } }" class="pret__vignette" :style="{ background: fondVignette(pret.article.couleur) }" :aria-label="pret.article.titre">
            <img v-if="pret.article.photo" :src="pret.article.photo" alt="" class="pret__photo" />
          </RouterLink>
          <span class="pret__textes">
            <RouterLink :to="{ name: 'article', params: { id: pret.article.id } }" class="pret__titre">{{ pret.article.titre }}</RouterLink>
            <span class="pret__qui">{{ emprunteur(pret) }}</span>
          </span>
          <span v-if="pret.enCours" class="statut" :class="{ 'statut--retard': pret.joursRestants < 0 }">{{ echeance(pret.joursRestants) }}</span>
          <span v-else class="statut statut--rendu">{{ t('prets.rendu') }}</span>
        </div>

        <div class="dates">
          <span class="date">
            <span class="date__libelle">{{ t('prets.preteLe') }}</span>
            <span class="date__valeur">{{ jourCourt(pret.datePret) }}</span>
          </span>
          <span v-if="pret.enCours" class="date">
            <span class="date__libelle">{{ t('prets.retourPrevu') }}</span>
            <span class="date__valeur">{{ pret.dateRetourPrevue ? jourCourt(pret.dateRetourPrevue) : '—' }}</span>
          </span>
          <span v-else class="date">
            <span class="date__libelle">{{ t('prets.renduLe') }}</span>
            <span class="date__valeur">{{ jourCourt(pret.dateRetourReelle) }}</span>
          </span>
        </div>

        <div class="pret__pied">
          <span class="pret__note">{{ pret.note ?? t('prets.aucuneNote') }}</span>
          <button v-if="pret.enCours" type="button" class="pret__rendu" @click="marquerRendu(pret)">{{ t('article.marquerRendu') }}</button>
          <button v-else type="button" class="pret__supprimer" :aria-label="t('prets.supprimer', { titre: pret.article.titre })" @click="supprimer(pret)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
          </button>
        </div>
      </article>
    </main>
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 16px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
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

.segments {
  display: flex;
  padding: 4px;
  border-radius: 14px;
  background: var(--couleur-info-fond);
}

.segment {
  flex: 1;
  height: 38px;
  border: 0;
  border-radius: 11px;
  background: none;
  color: var(--couleur-texte-secondaire);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.segment--actif {
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font-weight: 800;
  box-shadow: var(--ombre-segment);
}

.pret {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 18px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.pret--retard {
  border-color: var(--couleur-alerte-bordure);
}

.pret__entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pret__vignette {
  position: relative;
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 12px;
  /* Un vêtement blanc reste visible sur la carte */
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.pret__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pret__textes {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pret__titre {
  font-size: 15px;
  font-weight: 800;
  color: var(--couleur-texte);
}

.pret__qui {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
  overflow-wrap: anywhere;
}

.statut {
  flex-shrink: 0;
  padding: 4px 9px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 11.5px;
  font-weight: 800;
}

.statut--retard {
  background: var(--couleur-alerte-fond);
  color: var(--couleur-alerte);
}

.statut--rendu {
  background: var(--couleur-succes-fond);
  color: var(--couleur-succes);
}

.dates {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  font-size: 12px;
}

.date {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--couleur-fond);
}

.date__libelle {
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.date__valeur {
  font-size: 13px;
  font-weight: 800;
}

.pret__pied {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.pret__note {
  min-width: 0;
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  font-style: italic;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pret__rendu {
  flex-shrink: 0;
  height: 36px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1.5px solid var(--couleur-primaire);
  background: var(--couleur-surface);
  color: var(--couleur-primaire);
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.pret__supprimer {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--couleur-texte-secondaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
</style>
