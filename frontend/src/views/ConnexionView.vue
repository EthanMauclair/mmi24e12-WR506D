<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
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
const libelleBascule = computed(() =>
  t(afficherMotDePasse.value ? 'connexion.masquer' : 'connexion.afficher'),
)
</script>

<template>
  <div class="ecran">
    <div class="bandeau">
      <div class="bandeau__haut">
        <span class="bandeau__logo">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6a2 2 0 1 1 2 2c-1 0-2 .7-2 1.6v.9" /><path d="M12 10.5 2.6 17a1.4 1.4 0 0 0 .8 2.5h17.2a1.4 1.4 0 0 0 .8-2.5z" /></svg>
        </span>
        <SelecteurLangue />
      </div>
      <div class="bandeau__textes">
        <span class="bandeau__titre">{{ t('connexion.accroche') }}</span>
        <span class="bandeau__sous-titre">{{ t('connexion.sousTitre') }}</span>
      </div>
    </div>

    <form class="formulaire" @submit.prevent="seConnecter">
      <h1 class="formulaire__titre">{{ t('connexion.titre') }}</h1>

      <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

      <label class="champ">
        <span class="champ__libelle">{{ t('connexion.email') }}</span>
        <input v-model="email" class="saisie" type="email" autocomplete="email" :placeholder="t('connexion.exempleEmail')" required />
      </label>

      <label class="champ">
        <span class="champ__libelle">{{ t('connexion.motDePasse') }}</span>
        <span class="saisie saisie--groupe saisie--mot-de-passe">
          <input v-model="motDePasse" :type="typeMotDePasse" autocomplete="current-password" placeholder="••••••••" required />
          <button type="button" class="bascule" :aria-label="libelleBascule" @click="afficherMotDePasse = !afficherMotDePasse">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
          </button>
        </span>
      </label>

      <a href="#" class="oubli">{{ t('connexion.oubli') }}</a>

      <button type="submit" class="bouton-principal" :disabled="envoiEnCours">
        {{ t(envoiEnCours ? 'connexion.enCours' : 'connexion.seConnecter') }}
      </button>

      <div class="separateur">
        <span class="separateur__trait"></span>
        {{ t('connexion.ou') }}
        <span class="separateur__trait"></span>
      </div>

      <a href="#" class="bouton-secondaire">
        {{ t('connexion.decouvrir') }}
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
      </a>
    </form>

    <p class="pied">
      {{ t('connexion.pasDeCompte') }}
      <RouterLink to="/inscription" class="pied__lien">{{ t('connexion.inscription') }}</RouterLink>
    </p>
  </div>
</template>

<style scoped>
.bandeau {
  height: 250px;
  flex-shrink: 0;
  box-sizing: border-box;
  padding: 40px 24px 0;
  background: var(--couleur-primaire);
  color: var(--couleur-sur-primaire);
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.bandeau__haut {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.bandeau__logo {
  width: 60px;
  height: 60px;
  border-radius: 18px;
  background: var(--couleur-sur-primaire-voile);
  display: flex;
  align-items: center;
  justify-content: center;
}

.bandeau__textes {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bandeau__titre {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.bandeau__sous-titre {
  font-size: 14px;
  font-weight: 500;
  opacity: 0.85;
}

.formulaire {
  flex: 1;
  margin-top: -24px;
  padding: 28px 24px 0;
  border-radius: 26px 26px 0 0;
  background: var(--couleur-fond);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.formulaire__titre {
  margin: 0 0 4px;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.champ {
  gap: 6px;
}

.saisie {
  height: 52px;
}

.saisie--mot-de-passe {
  padding: 0 6px 0 16px;
}

.bascule {
  width: 44px;
  height: 44px;
  border: 0;
  background: none;
  color: var(--couleur-texte-secondaire);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.oubli {
  align-self: flex-end;
  padding: 4px 0;
  font-size: 13px;
  font-weight: 700;
}

.separateur {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--couleur-texte-secondaire);
  font-size: 12.5px;
  font-weight: 600;
}

.separateur__trait {
  flex: 1;
  height: 1px;
  background: var(--couleur-bordure);
}

.bouton-secondaire {
  height: 52px;
  box-sizing: border-box;
  border-radius: 16px;
  border: 1.5px solid var(--couleur-bordure);
  background: var(--couleur-surface);
  color: var(--couleur-texte);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
}

.pied {
  margin: 0;
  padding: 16px 24px 28px;
  text-align: center;
  font-size: 14px;
  color: var(--couleur-texte-secondaire);
}

.pied__lien {
  font-weight: 800;
}
</style>
