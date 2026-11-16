<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import BarreNavigation from '@/components/BarreNavigation.vue'
import SelecteurLangue from '@/components/SelecteurLangue.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { reduireEnJpeg } from '@/utils/image'

const { t } = useI18n()
const router = useRouter()
const session = useSessionStore()

const valeurs = reactive({ pseudo: '', prenom: '', nom: '', bio: '', estPrive: false })
const avatar = ref(null)
const champPhoto = ref(null)
const envoiPhoto = ref(false)
const erreurPhoto = ref('')
const etaitPrive = ref(false)
const demandesEnAttente = ref(0)
const chargement = ref(true)
const envoiEnCours = ref(false)
const erreur = ref('')
const erreurs = ref({})

const devientPublic = computed(() => etaitPrive.value && !valeurs.estPrive && demandesEnAttente.value > 0)
// Aperçu de l'avatar : la photo, ou l'initiale du pseudo en cours de saisie
const membreAffiche = computed(() => ({ pseudo: valeurs.pseudo || session.utilisateur.pseudo, avatar: avatar.value }))

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

async function changerPhoto(evenement) {
  const fichier = evenement.target.files[0]
  evenement.target.value = ''
  if (!fichier) {
    return
  }
  erreurPhoto.value = ''
  envoiPhoto.value = true
  try {
    const formulaire = new FormData()
    formulaire.append('photo', await reduireEnJpeg(fichier), 'avatar.jpg')
    avatar.value = (await api('/moi/avatar', { methode: 'POST', formulaire })).avatar
  } catch (e) {
    erreurPhoto.value = e.violations?.photo ?? e.message
  } finally {
    envoiPhoto.value = false
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
    avatar.value = profil.avatar
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
    <main class="page">
      <header class="entete">
        <RouterLink :to="{ name: 'profil' }" class="bouton-icone" :aria-label="t('demandes.retourProfil')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
        </RouterLink>
        <h1 class="titre-page">{{ t('profil.modifier') }}</h1>
      </header>

      <p v-if="chargement" class="etat">{{ t('commun.chargement') }}</p>
      <div v-else class="colonne-gauche disposition">
        <div class="photo">
          <AvatarMembre class="photo__avatar" :membre="membreAffiche" :taille="84" />
          <button type="button" class="lien" :disabled="envoiPhoto" @click="champPhoto.click()">{{ t(envoiPhoto ? 'modifierProfil.envoiPhoto' : 'modifierProfil.changerPhoto') }}</button>
          <span v-if="erreurPhoto" class="champ__erreur" role="alert">{{ erreurPhoto }}</span>
          <input ref="champPhoto" type="file" accept="image/*" class="visuellement-cache" tabindex="-1" @change="changerPhoto" />
        </div>

        <form class="formulaire" novalidate @submit.prevent="enregistrer">
          <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>

          <label class="champ">
            <span class="champ__libelle">{{ t('modifierProfil.pseudo') }}</span>
            <input v-model.trim="valeurs.pseudo" class="saisie" :class="{ 'saisie--invalide': erreurs.pseudo }" type="text" autocomplete="username" required />
            <span v-if="erreurs.pseudo" class="champ__erreur">{{ erreurs.pseudo }}</span>
          </label>

          <div class="grille-2">
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
            <textarea v-model="valeurs.bio" class="saisie" :class="{ 'saisie--invalide': erreurs.bio }" rows="3" maxlength="500" :placeholder="t('modifierProfil.exempleBio')"></textarea>
            <span v-if="erreurs.bio" class="champ__erreur">{{ erreurs.bio }}</span>
          </label>

          <label class="carte carte--rangee prive">
            <span class="entete__textes">
              <b class="prive__titre">{{ t('commun.profilPrive') }}</b>
              <span class="texte-secondaire">{{ t('modifierProfil.aidePrive') }}</span>
            </span>
            <input v-model="valeurs.estPrive" type="checkbox" role="switch" class="interrupteur" />
          </label>
          <p v-if="devientPublic" class="bandeau-info">{{ t('modifierProfil.devientPublic', demandesEnAttente) }}</p>

          <div class="champ">
            <span class="champ__libelle">{{ t('modifierProfil.langue') }}</span>
            <SelecteurLangue recharger complet />
          </div>

          <div class="barre-actions barre-actions--dans-page actions">
            <button type="button" class="bouton bouton--contour bouton--flexible" @click="seDeconnecter">{{ t('dressing.seDeconnecter') }}</button>
            <button type="submit" class="bouton bouton--flexible" :disabled="envoiEnCours">{{ t('commun.enregistrer') }}</button>
          </div>
        </form>
      </div>
    </main>

    <BarreNavigation actif="profil" ordinateur-seulement />
  </div>
</template>

<style scoped>
.disposition {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.photo {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.formulaire {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.prive {
  cursor: pointer;
}

.prive__titre {
  font-size: 14px;
}

@media (min-width: 1024px) {
  .disposition {
    display: grid;
    --largeur-colonne-fixe: 200px;
    gap: 36px;
  }

  .photo__avatar {
    width: 140px !important;
    height: 140px !important;
    font-size: 52px !important;
  }

  .formulaire {
    max-width: 600px;
    gap: 12px;
  }

  .actions {
    justify-content: space-between;
  }

  .actions .bouton {
    flex: none;
  }
}
</style>
