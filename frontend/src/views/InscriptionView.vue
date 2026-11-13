<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import SelecteurLangue from '@/components/SelecteurLangue.vue'
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
  <div class="ecran">
    <div class="bandeau">
      <div class="bandeau__haut">
        <h1 class="bandeau__titre">{{ t('inscription.titre') }}</h1>
        <SelecteurLangue />
      </div>
      <span class="bandeau__texte">{{ t('inscription.sousTitre') }}</span>
    </div>

    <form id="formulaire-inscription" class="formulaire" novalidate @submit.prevent="creerCompte">
      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <label class="champ">
        <span class="champ__libelle">{{ t('inscription.pseudo') }}</span>
        <input v-model="pseudo" class="saisie" :class="{ 'saisie--invalide': erreurs.pseudo }" type="text" autocomplete="username" :placeholder="t('inscription.pseudo')" />
        <span v-if="erreurs.pseudo" class="champ__erreur">{{ erreurs.pseudo }}</span>
      </label>

      <label class="champ">
        <span class="champ__libelle">{{ t('connexion.email') }}</span>
        <input v-model="email" class="saisie" :class="{ 'saisie--invalide': erreurs.email }" type="email" autocomplete="email" :placeholder="t('connexion.email')" />
        <span v-if="erreurs.email" class="champ__erreur">{{ erreurs.email }}</span>
      </label>

      <div class="deux-colonnes">
        <label class="champ">
          <span class="champ__libelle">{{ t('connexion.motDePasse') }}</span>
          <input v-model="motDePasse" class="saisie saisie--compacte" :class="{ 'saisie--invalide': erreurs.motDePasse }" type="password" autocomplete="new-password" :placeholder="t('inscription.motDePasseMin')" />
          <span v-if="erreurs.motDePasse" class="champ__erreur">{{ erreurs.motDePasse }}</span>
        </label>
        <label class="champ">
          <span class="champ__libelle">{{ t('inscription.dateNaissance') }}</span>
          <input v-model="dateNaissance" class="saisie saisie--compacte" :class="{ 'saisie--invalide': erreurs.dateNaissance }" type="date" :max="aujourdhui" autocomplete="bday" />
          <span v-if="erreurs.dateNaissance" class="champ__erreur">{{ erreurs.dateNaissance }}</span>
        </label>
      </div>

      <p class="info">
        <svg class="info__icone" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg>
        {{ t('inscription.mineur') }}
      </p>

      <label v-if="estMineur" class="champ">
        <span class="champ__libelle">{{ t('inscription.emailTuteur') }}</span>
        <input v-model="emailTuteur" class="saisie" :class="{ 'saisie--invalide': erreurs.emailTuteur }" type="email" :placeholder="t('inscription.emailTuteurExemple')" />
        <span v-if="erreurs.emailTuteur" class="champ__erreur">{{ erreurs.emailTuteur }}</span>
      </label>

      <fieldset class="visibilite">
        <legend class="visibilite__legende">{{ t('inscription.visibilite') }}</legend>
        <div class="deux-colonnes">
          <button
            v-for="option in optionsVisibilite"
            :key="option"
            type="button"
            class="option"
            :class="{ 'option--active': option === visibilite }"
            :aria-pressed="option === visibilite"
            @click="visibilite = option"
          >
            <span class="option__titre">{{ t(`inscription.${option}`) }}</span>
            <span class="option__description">{{ t(`inscription.${option}Description`) }}</span>
          </button>
        </div>
      </fieldset>

      <label class="conditions">
        <input v-model="conditionsAcceptees" type="checkbox" class="conditions__case" />
        <i18n-t keypath="inscription.conditions" tag="span" scope="global">
          <template #cgu><a href="#" class="conditions__lien">{{ t('inscription.cgu') }}</a></template>
          <template #confidentialite><a href="#" class="conditions__lien">{{ t('inscription.confidentialite') }}</a></template>
        </i18n-t>
      </label>
      <span v-if="erreurs.conditionsAcceptees" class="champ__erreur">{{ erreurs.conditionsAcceptees }}</span>
    </form>

    <div class="pied">
      <button type="submit" form="formulaire-inscription" class="bouton-principal" :disabled="envoiEnCours">
        {{ t(envoiEnCours ? 'inscription.enCours' : 'inscription.creer') }}
      </button>
      <p class="pied__texte">
        {{ t('inscription.dejaInscrit') }}
        <RouterLink to="/connexion" class="pied__lien">{{ t('connexion.seConnecter') }}</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
/* Même habillage que le bandeau de l'écran de connexion */
.bandeau {
  flex-shrink: 0;
  padding: 32px 24px 48px;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bandeau__haut {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.bandeau__titre {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.bandeau__texte {
  font-size: 14px;
  font-weight: 500;
  opacity: 0.85;
}

.formulaire {
  flex: 1;
  margin-top: -24px;
  padding: 24px 24px 0;
  border-radius: 26px 26px 0 0;
  background: var(--couleur-fond);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.champ {
  gap: 5px;
}

.saisie {
  height: 50px;
}

.saisie--compacte {
  min-width: 0;
  padding: 0 14px;
  font-size: 14px;
}

.saisie[type='date']::-webkit-calendar-picker-indicator {
  cursor: pointer;
}

.deux-colonnes {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.info {
  margin: 0;
  display: flex;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--couleur-info-fond);
  color: var(--couleur-primaire-fonce);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.45;
}

.info__icone {
  flex-shrink: 0;
  margin-top: 1px;
}

.visibilite {
  margin: 0;
  padding: 0;
  border: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.visibilite__legende {
  padding: 0 0 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--couleur-libelle);
}

.option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.option--active {
  border-color: var(--couleur-primaire);
  background: var(--couleur-primaire-pale);
}

.option__titre {
  font-size: 14px;
  font-weight: 800;
  color: var(--couleur-texte);
}

.option--active .option__titre {
  color: var(--couleur-primaire);
}

.option__description {
  font-size: 11.5px;
  color: var(--couleur-texte-secondaire);
  line-height: 1.35;
}

.conditions {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  line-height: 1.45;
  color: var(--couleur-libelle);
  cursor: pointer;
}

.conditions__case {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin: 0;
  accent-color: var(--couleur-primaire);
}

.conditions__lien {
  font-weight: 700;
}

.pied {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 24px 20px;
  background: var(--couleur-surface);
  border-top: 1px solid var(--couleur-bordure);
}

.pied__texte {
  margin: 0;
  text-align: center;
  font-size: 13.5px;
  color: var(--couleur-texte-secondaire);
}

.pied__lien {
  font-weight: 800;
}
</style>
