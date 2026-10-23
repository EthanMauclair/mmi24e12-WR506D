<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

const optionsVisibilite = [
  { id: 'public', label: 'Public', desc: 'Tout le monde voit vos tenues' },
  { id: 'prive', label: 'Privé', desc: 'Vous acceptez chaque abonné' },
]

const pseudo = ref('')
const email = ref('')
const motDePasse = ref('')
const dateNaissance = ref('')
const visibilite = ref('public')
const conditionsAcceptees = ref(false)
</script>

<template>
  <div class="ecran">
    <header class="entete">
      <RouterLink to="/connexion" class="retour" aria-label="Retour à la connexion">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
      </RouterLink>
      <span class="pastille">100 % gratuit</span>
    </header>

    <form id="formulaire-inscription" class="formulaire" @submit.prevent>
      <div class="intro">
        <h1 class="intro__titre">Créer un compte</h1>
        <span class="intro__texte">Indexez votre dressing et partagez vos tenues.</span>
      </div>

      <label class="champ">
        <span class="champ__libelle">Nom d'utilisateur</span>
        <input v-model="pseudo" class="saisie" type="text" autocomplete="username" placeholder="Nom d'utilisateur" />
      </label>

      <label class="champ">
        <span class="champ__libelle">E-mail</span>
        <input v-model="email" class="saisie" type="email" autocomplete="email" placeholder="E-mail" />
      </label>

      <div class="deux-colonnes">
        <label class="champ">
          <span class="champ__libelle">Mot de passe</span>
          <input v-model="motDePasse" class="saisie saisie--compacte" type="password" autocomplete="new-password" placeholder="8 caractères min." />
        </label>
        <label class="champ">
          <span class="champ__libelle">Date de naissance</span>
          <input v-model="dateNaissance" class="saisie saisie--compacte" type="text" inputmode="numeric" placeholder="JJ / MM / AAAA" />
        </label>
      </div>

      <p class="info">
        <svg class="info__icone" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg>
        Moins de 18 ans ? Votre compte sera rattaché à un compte majeur, qui vous ajoutera à son logement.
      </p>

      <fieldset class="visibilite">
        <legend class="visibilite__legende">Visibilité du profil</legend>
        <div class="deux-colonnes">
          <button
            v-for="option in optionsVisibilite"
            :key="option.id"
            type="button"
            class="option"
            :class="{ 'option--active': option.id === visibilite }"
            :aria-pressed="option.id === visibilite"
            @click="visibilite = option.id"
          >
            <span class="option__titre">{{ option.label }}</span>
            <span class="option__description">{{ option.desc }}</span>
          </button>
        </div>
      </fieldset>

      <label class="conditions">
        <input v-model="conditionsAcceptees" type="checkbox" class="conditions__case" />
        <span>J'accepte les <a href="#" class="conditions__lien">conditions d'utilisation</a> et la <a href="#" class="conditions__lien">politique de confidentialité</a>.</span>
      </label>
    </form>

    <div class="pied">
      <button type="submit" form="formulaire-inscription" class="bouton-principal">Créer mon compte</button>
      <p class="pied__texte">
        Déjà inscrit ?
        <RouterLink to="/connexion" class="pied__lien">Se connecter</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.entete {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px 0;
}

.retour {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 14px;
  border: 1px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pastille {
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--couleur-primaire-pale);
  color: var(--couleur-primaire);
  font-size: 12px;
  font-weight: 800;
}

.formulaire {
  flex: 1;
  padding: 16px 24px 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.intro {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 2px;
}

.intro__titre {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.intro__texte {
  font-size: 14px;
  color: var(--couleur-texte-secondaire);
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
