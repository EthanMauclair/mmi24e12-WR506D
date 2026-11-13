<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/api'
import { fondVignette } from '@/utils/couleurs'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const article = ref(null)
const prix = ref('')
const description = ref('')
const erreur = ref('')
const erreurs = ref({})
const envoi = ref(false)

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
      router.replace({ name: 'article', params: { id: route.params.id } })
    }
  } catch (e) {
    erreur.value = e.message
  }
})
</script>

<template>
  <div class="ecran">
    <main class="contenu">
      <header class="entete">
        <RouterLink :to="{ name: 'article', params: { id: route.params.id } }" class="bouton-icone" :aria-label="t('preter.retourArticle')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="entete__titre">{{ t('article.mettreEnVente') }}</h1>
      </header>

      <p v-if="!article && !erreur" class="etat">{{ t('commun.chargement') }}</p>
      <p v-else-if="!article" class="message-erreur" role="alert">{{ erreur }}</p>

      <form v-else class="formulaire" novalidate @submit.prevent="vendre">
        <div class="article">
          <span class="article__vignette" :style="{ background: fondVignette(article.couleur?.codeHex) }">
            <img v-if="article.photos.length" :src="article.photos[0]" alt="" class="article__photo" />
          </span>
          <span class="article__textes">
            <span class="article__titre">{{ article.titre }}</span>
            <span class="article__infos">{{ article.infos }}</span>
          </span>
        </div>

        <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

        <label class="champ">
          <span class="champ__libelle">{{ t('vendre.prix') }}</span>
          <span class="saisie saisie--groupe prix" :class="{ 'saisie--invalide': erreurs.prix }">
            <input v-model.trim="prix" type="text" inputmode="decimal" :placeholder="t('vendre.exemplePrix')" required />
            <span aria-hidden="true">€</span>
          </span>
          <span v-if="erreurs.prix" class="champ__erreur">{{ erreurs.prix }}</span>
        </label>

        <label class="champ">
          <span class="champ__libelle">{{ t('vendre.description') }}</span>
          <textarea v-model="description" class="saisie description" rows="4" maxlength="2000" :placeholder="t('vendre.exempleDescription')"></textarea>
        </label>

        <p class="info">
          {{ t('vendre.info') }}
        </p>

        <button type="submit" class="bouton-principal" :disabled="envoi || !prix">{{ t('vendre.publier') }}</button>
      </form>
    </main>
  </div>
</template>

<style scoped>
.contenu {
  flex: 1;
  padding: 20px 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
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

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.article {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 16px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
}

.article__vignette {
  position: relative;
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 12px;
  border: 1px solid var(--couleur-bordure);
  overflow: hidden;
}

.article__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.article__textes {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.article__titre {
  font-size: 15px;
  font-weight: 800;
}

.article__infos {
  font-size: 12.5px;
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
}

.champ {
  gap: 6px;
}

.prix {
  height: 50px;
  gap: 6px;
  font-weight: 700;
}

.description {
  padding: 12px 16px;
  resize: vertical;
  line-height: 1.5;
}

.info {
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.45;
}
</style>
