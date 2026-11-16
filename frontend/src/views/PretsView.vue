<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import VignetteArticle from '@/components/VignetteArticle.vue'
import { api } from '@/services/api'
import { echeance, jourCourt } from '@/utils/dates'

const { t } = useI18n()

const prets = ref({ enCours: [], rendus: [] })
const onglet = ref('enCours')
const chargement = ref(true)
const erreur = ref('')

const affiches = computed(() => prets.value[onglet.value])

function emprunteur(pret) {
  return pret.emprunteur ? t('article.preteA', { nom: `@${pret.emprunteur.pseudo}` }) : t('prets.preteANom', { nom: pret.emprunteurNom })
}

// « Prêté le 2 oct. · Retour prévu le 15 oct. » (ou « Rendu le … » pour un prêt rendu)
function dates(pret) {
  const debut = t('prets.preteLeDate', { date: jourCourt(pret.datePret) })
  if (!pret.enCours) {
    return `${debut} · ${t('prets.renduLeDate', { date: jourCourt(pret.dateRetourReelle) })}`
  }
  return pret.dateRetourPrevue ? `${debut} · ${t('prets.retourPrevuDate', { date: jourCourt(pret.dateRetourPrevue) })}` : debut
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
    <main class="page">
      <header class="entete entete--onglets">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('prets.retourProfil')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t('profil.ongletPrets') }}</h1>
        <div role="tablist" :aria-label="t('prets.statuts')" class="segments onglets">
          <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'enCours' }" :aria-selected="onglet === 'enCours'" @click="onglet = 'enCours'">
            {{ t('prets.enCours') }} · {{ prets.enCours.length }}
          </button>
          <button type="button" role="tab" class="segment" :class="{ 'segment--actif': onglet === 'rendus' }" :aria-selected="onglet === 'rendus'" @click="onglet = 'rendus'">
            {{ t('prets.rendus') }} · {{ prets.rendus.length }}
          </button>
        </div>
      </header>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
      <p v-if="chargement" class="etat">{{ t('prets.chargement') }}</p>
      <p v-else-if="affiches.length === 0" class="etat">
        <template v-if="onglet === 'enCours'">{{ t('prets.aucunEnCours') }}</template>
        <template v-else>{{ t('prets.aucunRendu') }}</template>
      </p>

      <div v-else class="liste">
        <article v-for="pret in affiches" :key="pret.id" class="carte pret" :class="{ 'carte--accent': pret.enCours && pret.joursRestants < 0 }">
          <div class="pret__entete">
            <RouterLink :to="{ name: 'article', params: { id: pret.article.id } }" class="pret__lien" :aria-label="pret.article.titre">
              <VignetteArticle class="pret__vignette" :photo="pret.article.photo" :couleur="pret.article.couleur" :icone="24" />
            </RouterLink>
            <span class="ligne__textes">
              <RouterLink :to="{ name: 'article', params: { id: pret.article.id } }" class="pret__titre">{{ pret.article.titre }}</RouterLink>
              <span class="texte-secondaire">{{ emprunteur(pret) }}</span>
            </span>
            <span v-if="pret.enCours" class="badge" :class="{ 'badge--plein': pret.joursRestants < 0 }">{{ echeance(pret.joursRestants) }}</span>
            <span v-else class="badge">{{ t('prets.rendu') }}</span>
          </div>

          <span class="texte-secondaire">{{ dates(pret) }}</span>

          <div class="pret__pied">
            <span class="texte-secondaire pret__note">{{ pret.note ?? t('prets.aucuneNote') }}</span>
            <button v-if="pret.enCours" type="button" class="bouton bouton--petit" @click="marquerRendu(pret)">{{ t('article.marquerRendu') }}</button>
            <button v-else type="button" class="bouton bouton--contour bouton--petit" :aria-label="t('prets.supprimer', { titre: pret.article.titre })" @click="supprimer(pret)">
              {{ t('commun.supprimer') }}
            </button>
          </div>
        </article>
      </div>
    </main>

    <BarreNavigation actif="profil" />
  </div>
</template>

<style scoped>
.entete--onglets {
  flex-wrap: wrap;
}

.onglets {
  flex-basis: 100%;
}

.liste {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pret {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pret__entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pret__lien {
  display: block;
}

.pret__vignette {
  width: 52px;
  height: 52px;
}

.pret__titre {
  font-size: 15px;
  font-weight: 700;
  color: var(--couleur-texte);
}

.pret__pied {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pret__note {
  flex: 1;
  min-width: 0;
}

@media (min-width: 1024px) {
  .entete--onglets {
    flex-wrap: nowrap;
  }

  .onglets {
    flex-basis: 320px;
    flex-shrink: 0;
  }

  .liste {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
  }

  .pret {
    gap: 10px;
  }

  .pret__vignette {
    width: 64px;
    height: 64px;
  }
}
</style>
