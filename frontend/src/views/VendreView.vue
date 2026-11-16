<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BarreNavigation from '@/components/BarreNavigation.vue'
import RappelArticle from '@/components/RappelArticle.vue'
import { api } from '@/services/api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const article = ref(null)
const prix = ref('')
const description = ref('')
const erreur = ref('')
const erreurs = ref({})
const envoi = ref(false)

const retourArticle = { name: 'article', params: { id: route.params.id } }

async function vendre() {
  erreur.value = ''
  erreurs.value = {}
  envoi.value = true
  try {
    const annonce = await api(`/articles/${article.value.id}/annonce`, { methode: 'POST', corps: { prix: prix.value, description: description.value } })
    router.replace({ name: 'annonce', params: { id: annonce.id } })
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = e.violations.general ?? (Object.keys(e.violations).length ? t('commun.verifierChamps') : e.message)
  } finally {
    envoi.value = false
  }
}

onMounted(async () => {
  try {
    article.value = await api(`/articles/${route.params.id}`)
    // Déjà en vente, prêté ou pas à soi : retour à la fiche
    if (!article.value.peutVendre || article.value.statut !== 'DISPONIBLE') {
      router.replace(retourArticle)
    }
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="ecran">
    <main class="page">
      <header class="entete">
        <RouterLink :to="retourArticle" class="bouton-icone" :aria-label="t('preter.retourArticle')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t('article.mettreEnVente') }}</h1>
      </header>

      <p v-if="!article && !erreur" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="!article" class="message-erreur" role="alert">{{ erreur }}</p>

      <div v-else class="colonne-gauche disposition">
        <RappelArticle :article="article" />

        <form class="formulaire colonne-etroite" novalidate @submit.prevent="vendre">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('vendre.prix') }}</span>
            <input v-model.trim="prix" class="saisie" :class="{ 'saisie--invalide': erreurs.prix }" type="text" inputmode="decimal" :placeholder="t('vendre.exemplePrix')" required />
            <span v-if="erreurs.prix" class="champ__erreur">{{ erreurs.prix }}</span>
          </label>

          <label class="champ">
            <span class="champ__libelle">{{ t('vendre.description') }}</span>
            <textarea v-model="description" class="saisie description" rows="5" maxlength="2000" :placeholder="t('vendre.exempleDescription')"></textarea>
          </label>

          <p class="carte carte--grise texte-secondaire">{{ t('vendre.info') }}</p>

          <div class="barre-actions barre-actions--dans-page barre-actions--droite">
            <RouterLink :to="retourArticle" class="bouton bouton--contour seulement-ordinateur">{{ t('commun.annuler') }}</RouterLink>
            <button type="submit" class="bouton valider" :disabled="envoi || !prix">{{ t('vendre.publier') }}</button>
          </div>
        </form>
      </div>
    </main>

    <BarreNavigation actif="dressing" ordinateur-seulement />
  </div>
</template>

<style scoped>
.formulaire {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.disposition {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.description {
  min-height: 120px;
}

.valider {
  flex: 1;
}

@media (min-width: 1024px) {
  .disposition {
    display: grid;
    --largeur-colonne-fixe: 320px;
    gap: 40px;
  }

  .colonne-etroite {
    --largeur-etroite: 560px;
    gap: 14px;
  }

  .description {
    min-height: 140px;
  }

  .valider {
    flex: none;
  }
}
</style>
