<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import SelecteurLangue from '@/components/SelecteurLangue.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'

const { t } = useI18n()
const router = useRouter()
const session = useSessionStore()

const valeurs = reactive({ pseudo: '', prenom: '', nom: '', bio: '', estPrive: false })
const etaitPrive = ref(false)
const demandesEnAttente = ref(0)
const chargement = ref(true)
const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})

const devientPublic = computed(() => etaitPrive.value && !valeurs.estPrive && demandesEnAttente.value > 0)

async function enregistrer() {
  erreur.value = ''
  erreurs.value = {}
  envoiEnCours.value = true
  try {
    session.utilisateur = await api('/moi/profil', { methode: 'PUT', corps: { ...valeurs } })
    router.push({ name: 'profil' })
  } catch (e) {
    erreurs.value = e.violations
    erreur.value = Object.keys(e.violations).length ? t('commun.verifierChamps') : e.message
  } finally {
    envoiEnCours.value = false
  }
}

async function seDeconnecter() {
  await session.deconnexion()
  router.push({ name: 'connexion' })
}

onMounted(async () => {
  try {
    const profil = await api(`/profils/${session.utilisateur.pseudo}`)
    Object.assign(valeurs, {
      pseudo: profil.pseudo,
      prenom: profil.prenom ?? '',
      nom: profil.nom ?? '',
      bio: profil.bio ?? '',
      estPrive: profil.estPrive,
    })
    etaitPrive.value = profil.estPrive
    demandesEnAttente.value = profil.moi.demandes
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
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('demandes.retourProfil')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="entete__titre">{{ t('profil.modifier') }}</h1>
      </header>

      <p v-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <form v-else class="formulaire" novalidate @submit.prevent="enregistrer">
        <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

        <label class="champ">
          <span class="champ__libelle">{{ t('inscription.pseudo') }}</span>
          <input v-model.trim="valeurs.pseudo" class="saisie" :class="{ 'saisie--invalide': erreurs.pseudo }" type="text" autocomplete="username" required />
          <span v-if="erreurs.pseudo" class="champ__erreur">{{ erreurs.pseudo }}</span>
        </label>

        <div class="ligne">
          <label class="champ">
            <span class="champ__libelle">{{ t('modifierProfil.prenom') }}</span>
            <input v-model.trim="valeurs.prenom" class="saisie" :class="{ 'saisie--invalide': erreurs.prenom }" type="text" autocomplete="given-name" />
            <span v-if="erreurs.prenom" class="champ__erreur">{{ erreurs.prenom }}</span>
          </label>
          <label class="champ">
            <span class="champ__libelle">{{ t('modifierProfil.nom') }}</span>
            <input v-model.trim="valeurs.nom" class="saisie" :class="{ 'saisie--invalide': erreurs.nom }" type="text" autocomplete="family-name" />
            <span v-if="erreurs.nom" class="champ__erreur">{{ erreurs.nom }}</span>
          </label>
        </div>

        <label class="champ">
          <span class="champ__libelle">{{ t('modifierProfil.bio') }}</span>
          <textarea v-model="valeurs.bio" class="saisie saisie--zone" :class="{ 'saisie--invalide': erreurs.bio }" rows="3" maxlength="500" :placeholder="t('modifierProfil.exempleBio')"></textarea>
          <span v-if="erreurs.bio" class="champ__erreur">{{ erreurs.bio }}</span>
        </label>

        <label class="prive">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="prive__icone"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
          <span class="prive__textes">
            <span class="prive__titre">{{ t('commun.profilPrive') }}</span>
            <span class="prive__aide">{{ t('modifierProfil.aidePrive') }}</span>
          </span>
          <input v-model="valeurs.estPrive" type="checkbox" class="visuellement-cache" />
          <span class="interrupteur" :class="{ 'interrupteur--actif': valeurs.estPrive }" aria-hidden="true">
            <span class="interrupteur__curseur"></span>
          </span>
        </label>
        <p v-if="devientPublic" class="info">
          {{ t('modifierProfil.devientPublic', demandesEnAttente) }}
        </p>

        <div class="champ">
          <span class="champ__libelle">{{ t('langue.choisir') }}</span>
          <SelecteurLangue recharger />
        </div>

        <button type="submit" class="bouton-principal" :disabled="envoiEnCours">{{ t('commun.enregistrer') }}</button>
        <button type="button" class="deconnexion" @click="seDeconnecter">{{ t('dressing.seDeconnecter') }}</button>
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
  gap: 20px;
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

.champ {
  gap: 6px;
  min-width: 0;
}

.champ .saisie {
  height: 50px;
}

.champ .saisie--zone {
  height: auto;
  padding: 12px 16px;
  resize: vertical;
  line-height: 1.5;
}

.ligne {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.prive {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--couleur-surface);
  border: 1px solid var(--couleur-bordure);
  cursor: pointer;
}

.prive__icone {
  color: var(--couleur-primaire);
}

.prive__textes {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.prive__titre {
  font-size: 14px;
  font-weight: 700;
}

.prive__aide {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.interrupteur {
  flex-shrink: 0;
  width: 46px;
  height: 28px;
  border-radius: 999px;
  background: var(--couleur-interrupteur-inactif);
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 3px;
  box-sizing: border-box;
}

.interrupteur--actif {
  background: var(--couleur-primaire);
  justify-content: flex-end;
}

.prive input:focus-visible + .interrupteur {
  outline: 2px solid var(--couleur-primaire);
  outline-offset: 2px;
}

.interrupteur__curseur {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--couleur-surface);
  box-shadow: var(--ombre-curseur);
}

.info {
  margin: -6px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--couleur-primaire);
}

.deconnexion {
  align-self: center;
  padding: 8px;
  border: 0;
  background: none;
  color: var(--couleur-erreur);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>
