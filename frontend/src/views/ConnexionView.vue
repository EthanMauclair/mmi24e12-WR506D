<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AtoutsAccueil from '@/components/AtoutsAccueil.vue'
import IllustrationAccueil from '@/components/IllustrationAccueil.vue'
import SelecteurLangue from '@/components/SelecteurLangue.vue'
import { useSessionStore } from '@/stores/session'

const { t } = useI18n()
const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const motDePasse = ref('')
const afficherMotDePasse = ref(false)
const envoiEnCours = ref(false)
const erreur = ref('')

async function seConnecter() {
  erreur.value = ''
  envoiEnCours.value = true
  try {
    await session.connexion(email.value.trim(), motDePasse.value)
    const redirection = route.query.redirection
    router.push(typeof redirection === 'string' && redirection.startsWith('/') ? redirection : { name: 'dressing' })
  } catch (e) {
    erreur.value = e.message
  } finally {
    envoiEnCours.value = false
  }
}

const typeMotDePasse = computed(() => (afficherMotDePasse.value ? 'text' : 'password'))
</script>

<template>
  <div class="accueil">
    <div class="accueil__haut">
      <span class="accueil__marque">
        <span class="accueil__logo" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6a2 2 0 1 1 2 2c-1 0-2 .7-2 1.6v.9" /><path d="M12 10.5 2.6 17a1.4 1.4 0 0 0 .8 2.5h17.2a1.4 1.4 0 0 0 .8-2.5z" /></svg>
        </span>
        {{ t('navigation.marque') }}
      </span>
      <SelecteurLangue />
    </div>

    <div class="accueil__carte">
      <!-- Présentation : en tête d'écran sur téléphone, colonne de gauche sur ordinateur -->
      <section class="presentation">
        <IllustrationAccueil class="presentation__illustration" />
        <h1 class="presentation__titre">{{ t('connexion.accroche') }}</h1>
        <p class="texte-secondaire">{{ t('connexion.sousTitre') }}</p>
        <AtoutsAccueil class="presentation__atouts" />
      </section>

      <div class="formulaire">
        <h2 class="titre-page formulaire__titre">{{ t('connexion.titre') }}</h2>
        <form class="pile" @submit.prevent="seConnecter">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('connexion.email') }}</span>
            <input v-model="email" class="saisie" type="email" autocomplete="email" :placeholder="t('connexion.exempleEmail')" required />
          </label>

          <label class="champ">
            <span class="champ__libelle">{{ t('connexion.motDePasse') }}</span>
            <input v-model="motDePasse" class="saisie" :type="typeMotDePasse" autocomplete="current-password" placeholder="••••••••" required />
          </label>

          <div class="ligne-titre">
            <button type="button" class="lien lien--discret" :aria-pressed="afficherMotDePasse" @click="afficherMotDePasse = !afficherMotDePasse">
              {{ t(afficherMotDePasse ? 'connexion.masquer' : 'connexion.afficher') }}
            </button>
            <a href="#" class="lien">{{ t('connexion.oubli') }}</a>
          </div>

          <button type="submit" class="bouton bouton--large" :disabled="envoiEnCours">
            {{ t(envoiEnCours ? 'connexion.enCours' : 'connexion.seConnecter') }}
          </button>

          <div class="separateur texte-secondaire">
            <span class="separateur__trait"></span>
            {{ t('connexion.ou') }}
            <span class="separateur__trait"></span>
          </div>

          <RouterLink :to="{ name: 'membres' }" class="bouton bouton--contour bouton--large">{{ t('connexion.decouvrir') }}</RouterLink>
        </form>

        <p class="texte-secondaire pied">
          {{ t('connexion.pasDeCompte') }}
          <RouterLink to="/inscription" class="lien">{{ t('connexion.inscription') }}</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Téléphone : fond dégradé du rose au blanc, logo et langue en haut, présentation puis formulaire.
   La page couvre toute la fenêtre ; le contenu est centré par les marges intérieures */
.accueil {
  --largeur-accueil: 358px;
  min-height: 100dvh;
  padding: 22px max(16px, calc((100% - var(--largeur-accueil)) / 2)) 24px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 22px;
  background: var(--degrade-accueil-mobile);
}

.accueil__haut {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.accueil__marque {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 800;
}

.accueil__logo {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  align-items: center;
  justify-content: center;
}

.accueil__carte {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.presentation {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.presentation__titre {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.01em;
  line-height: 1.2;
}

.presentation__atouts,
.formulaire__titre {
  display: none;
}

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.formulaire .pile {
  gap: 14px;
}

.lien--discret {
  color: var(--couleur-texte-secondaire);
  font-weight: 600;
  text-decoration: none;
}

.separateur {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.separateur__trait {
  width: 90px;
  height: 1px;
  background: var(--couleur-bordure);
}

.pied {
  text-align: center;
}

/* Tablette : carte centrée avec le seul formulaire */
@media (min-width: 600px) {
  .accueil {
    --largeur-accueil: 480px;
    padding-top: 40px;
    padding-bottom: 40px;
    justify-content: center;
    background: var(--degrade-accueil);
  }

  .accueil__carte {
    padding: 32px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 18px;
    background: var(--couleur-surface);
    box-shadow: var(--ombre-menu);
  }

  .presentation {
    display: none;
  }

  .formulaire__titre {
    display: block;
  }
}

/* Ordinateur : carte large en deux parties, présentation à gauche et formulaire à droite */
@media (min-width: 1024px) {
  .accueil {
    --largeur-accueil: 960px;
    gap: 14px;
  }

  .accueil__carte {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0;
    min-height: 560px;
    padding: 0;
    overflow: hidden;
  }

  .presentation {
    display: flex;
    gap: 18px;
    padding: 40px;
    border-right: 1.5px solid var(--couleur-bordure);
    background: var(--couleur-primaire-pale);
  }

  /* Titre et accroche d'abord, puis l'illustration et les atouts */
  .presentation__titre {
    order: 1;
    font-size: 30px;
  }

  .presentation .texte-secondaire {
    order: 2;
  }

  .presentation__illustration {
    order: 3;
    flex: 1;
    background: var(--couleur-fond);
  }

  .presentation__atouts {
    order: 4;
  }

  .presentation__atouts {
    display: flex;
  }

  .formulaire {
    justify-content: center;
    padding: 40px;
  }
}
</style>
