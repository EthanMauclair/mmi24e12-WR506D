<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import IllustrationAccueil from '@/components/IllustrationAccueil.vue'
import { useSessionStore } from '@/stores/session'

const { t } = useI18n()
const session = useSessionStore()
const router = useRouter()

const optionsVisibilite = ['public', 'prive']

const pseudo = ref('')
const email = ref('')
const motDePasse = ref('')
// Format AAAA-MM-JJ du champ date ; le navigateur l'affiche en JJ/MM/AAAA avec un calendrier
const dateNaissance = ref('')
const aujourdhui = new Date().toISOString().slice(0, 10)
const visibilite = ref('public')
const conditionsAcceptees = ref(false)
const emailTuteur = ref('')

const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})

// Un mineur indique l'e-mail du compte majeur auquel il est rattaché
const estMineur = computed(() => {
  if (!dateNaissance.value) {
    return false
  }
  const majorite = new Date(dateNaissance.value)
  majorite.setFullYear(majorite.getFullYear() + 18)
  return majorite > new Date()
})

async function creerCompte() {
  erreur.value = ''
  erreurs.value = {}
  envoiEnCours.value = true
  try {
    await session.inscription({
      pseudo: pseudo.value,
      email: email.value,
      motDePasse: motDePasse.value,
      dateNaissance: dateNaissance.value,
      estPrive: visibilite.value === 'prive',
      conditionsAcceptees: conditionsAcceptees.value,
      emailTuteur: estMineur.value ? emailTuteur.value : null,
    })
    router.push({ name: 'dressing' })
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = Object.keys(e.violations).length ? '' : e.message
  } finally {
    envoiEnCours.value = false
  }
}
</script>

<template>
  <div class="accueil">
    <div class="accueil__carte">
      <!-- Ordinateur : présentation à gauche -->
      <section class="presentation">
        <p class="presentation__titre">{{ t('accueil.inscriptionAccroche') }}</p>
        <IllustrationAccueil class="presentation__illustration" />
        <p class="texte-secondaire">{{ t('accueil.gratuit') }}</p>
      </section>

      <div class="formulaire">
        <header class="entete">
          <RouterLink to="/connexion" class="bouton-icone seulement-mobile" :aria-label="t('commun.retour')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
          </RouterLink>
          <h1 class="titre-page">{{ t('inscription.titre') }}</h1>
        </header>
        <p class="texte-secondaire sous-titre">{{ t('inscription.sousTitre') }}</p>

        <form class="pile" novalidate @submit.prevent="creerCompte">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('inscription.pseudo') }}</span>
            <input v-model="pseudo" class="saisie" :class="{ 'saisie--invalide': erreurs.pseudo }" type="text" autocomplete="username" :placeholder="t('inscription.exemplePseudo')" />
            <span v-if="erreurs.pseudo" class="champ__erreur">{{ erreurs.pseudo }}</span>
          </label>

          <label class="champ">
            <span class="champ__libelle">{{ t('connexion.email') }}</span>
            <input v-model="email" class="saisie" :class="{ 'saisie--invalide': erreurs.email }" type="email" autocomplete="email" :placeholder="t('connexion.exempleEmail')" />
            <span v-if="erreurs.email" class="champ__erreur">{{ erreurs.email }}</span>
          </label>

          <div class="identifiants">
            <label class="champ">
              <span class="champ__libelle">{{ t('connexion.motDePasse') }}</span>
              <input v-model="motDePasse" class="saisie" :class="{ 'saisie--invalide': erreurs.motDePasse }" type="password" autocomplete="new-password" :placeholder="t('inscription.motDePasseMin')" />
              <span v-if="erreurs.motDePasse" class="champ__erreur">{{ erreurs.motDePasse }}</span>
            </label>
            <label class="champ">
              <span class="champ__libelle">{{ t('inscription.dateNaissance') }}</span>
              <input v-model="dateNaissance" class="saisie" :class="{ 'saisie--invalide': erreurs.dateNaissance }" type="date" :max="aujourdhui" autocomplete="bday" />
              <span v-if="erreurs.dateNaissance" class="champ__erreur">{{ erreurs.dateNaissance }}</span>
            </label>
          </div>

          <!-- Moins de 18 ans : e-mail du compte majeur qui rattache le mineur -->
          <template v-if="estMineur">
            <p class="bandeau-info">{{ t('inscription.mineur') }}</p>
            <label class="champ">
              <span class="champ__libelle">{{ t('inscription.emailTuteur') }}</span>
              <input v-model="emailTuteur" class="saisie" :class="{ 'saisie--invalide': erreurs.emailTuteur }" type="email" :placeholder="t('inscription.emailTuteurExemple')" />
              <span v-if="erreurs.emailTuteur" class="champ__erreur">{{ erreurs.emailTuteur }}</span>
            </label>
          </template>

          <fieldset class="visibilite">
            <legend class="champ__libelle">{{ t('inscription.visibilite') }}</legend>
            <div class="grille-2">
              <button
                v-for="option in optionsVisibilite"
                :key="option"
                type="button"
                class="carte option"
                :class="{ 'option--active': option === visibilite }"
                :aria-pressed="option === visibilite"
                @click="visibilite = option"
              >
                <b>{{ t(`inscription.${option}`) }}</b>
                <span class="texte-secondaire">{{ t(`inscription.${option}Description`) }}</span>
              </button>
            </div>
          </fieldset>

          <label class="conditions texte-secondaire">
            <input v-model="conditionsAcceptees" type="checkbox" class="conditions__case" />
            <i18n-t keypath="inscription.conditions" tag="span" scope="global">
              <template #cgu><a href="#" class="lien">{{ t('inscription.cgu') }}</a></template>
              <template #confidentialite><a href="#" class="lien">{{ t('inscription.confidentialite') }}</a></template>
            </i18n-t>
          </label>
          <span v-if="erreurs.conditionsAcceptees" class="champ__erreur">{{ erreurs.conditionsAcceptees }}</span>

          <button type="submit" class="bouton bouton--large" :disabled="envoiEnCours">
            {{ t(envoiEnCours ? 'inscription.enCours' : 'inscription.creer') }}
          </button>
        </form>

        <p class="texte-secondaire pied">
          {{ t('inscription.dejaInscrit') }}
          <RouterLink to="/connexion" class="lien">{{ t('connexion.seConnecter') }}</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Fond dégradé sur toute la fenêtre ; le contenu est centré par les marges intérieures */
.accueil {
  --largeur-accueil: 358px;
  min-height: 100dvh;
  padding: 18px max(16px, calc((100% - var(--largeur-accueil)) / 2)) 24px;
  box-sizing: border-box;
  background: var(--degrade-accueil-mobile);
}

.presentation {
  display: none;
}

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.formulaire .pile {
  gap: 12px;
}

.sous-titre {
  margin-top: -4px;
}

.identifiants {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.visibilite {
  margin: 0;
  padding: 0;
  border: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.visibilite legend {
  padding: 0 0 6px;
}

.option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  font: inherit;
  text-align: left;
  color: var(--couleur-texte);
  cursor: pointer;
}

.option--active {
  border: 2px solid var(--couleur-primaire);
  background: var(--couleur-primaire-pale);
}

.conditions {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
}

.conditions__case {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin: 0;
  accent-color: var(--couleur-primaire);
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
    background: var(--degrade-accueil);
  }

  .accueil__carte {
    padding: 32px;
    border: 1.5px solid var(--couleur-bordure);
    border-radius: 18px;
    background: var(--couleur-surface);
    box-shadow: var(--ombre-menu);
  }
}

/* Ordinateur : carte large en deux parties, présentation à gauche et formulaire à droite */
@media (min-width: 1024px) {
  .accueil {
    --largeur-accueil: 960px;
    display: flex;
    align-items: center;
  }

  .accueil__carte {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    min-height: 720px;
    padding: 0;
    overflow: hidden;
  }

  .presentation {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 40px;
    border-right: 1.5px solid var(--couleur-bordure);
    background: var(--couleur-primaire-pale);
  }

  .presentation__titre {
    margin: 0;
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.2;
  }

  .presentation__illustration {
    flex: 1;
    background: var(--couleur-fond);
  }

  .formulaire {
    justify-content: center;
    gap: 10px;
    padding: 32px 40px;
  }

  .formulaire .pile {
    gap: 10px;
  }

  .sous-titre {
    display: none;
  }

  .identifiants {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
}
</style>
