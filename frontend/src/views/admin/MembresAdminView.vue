<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AvatarMembre from '@/components/AvatarMembre.vue'
import { api } from '@/services/api'
import { useSessionStore } from '@/stores/session'
import { formatDate, ilYA } from '@/utils/dates'

const { t } = useI18n()
const session = useSessionStore()

const ROLES = ['MEMBRE', 'ADMIN', 'SUPER_ADMIN']

const recherche = ref('')
const page = ref(1)
const resultat = ref(null)
const selection = ref(null)
const erreur = ref('')
const succes = ref('')
let minuteur = null

async function charger() {
  erreur.value = ''
  try {
    resultat.value = await api(`/admin/membres?q=${encodeURIComponent(recherche.value.trim())}&page=${page.value}`)
  } catch (e) {
    erreur.value = e.message
  }
}

// Recherche au fil de la saisie, sans requête à chaque touche
watch(recherche, () => {
  clearTimeout(minuteur)
  minuteur = setTimeout(() => {
    page.value = 1
    charger()
  }, 300)
})

function changerPage(decalage) {
  page.value += decalage
  charger()
}

async function ouvrir(membre) {
  succes.value = ''
  erreur.value = ''
  try {
    selection.value = await api(`/admin/membres/${membre.id}`)
  } catch (e) {
    erreur.value = e.message
  }
}

function remplacer(membre) {
  const liste = resultat.value.membres
  const index = liste.findIndex((autre) => autre.id === membre.id)
  if (index !== -1) {
    liste[index] = { ...liste[index], ...membre }
  }
}

async function modifier(corps, message) {
  erreur.value = ''
  succes.value = ''
  try {
    const membre = await api(`/admin/membres/${selection.value.id}`, { methode: 'PATCH', corps })
    selection.value = { ...selection.value, ...membre }
    remplacer(membre)
    succes.value = message
  } catch (e) {
    erreur.value = Object.values(e.violations ?? {})[0] ?? e.message
  }
}

function basculerBlocage() {
  const bloquer = !selection.value.estBloque
  if (bloquer && !window.confirm(t('admin.membres.confirmerBlocage', { pseudo: selection.value.pseudo }))) {
    return
  }
  modifier({ estBloque: bloquer }, bloquer ? t('admin.membres.bloque', { pseudo: selection.value.pseudo }) : t('admin.membres.debloque', { pseudo: selection.value.pseudo }))
}

function changerRole(evenement) {
  const role = evenement.target.value
  if (!window.confirm(t('admin.membres.confirmerRole', { pseudo: selection.value.pseudo, role: t(`admin.roles.${role}`) }))) {
    evenement.target.value = selection.value.role
    return
  }
  modifier({ role }, t('admin.membres.roleModifie', { pseudo: selection.value.pseudo }))
}

async function supprimer() {
  const { pseudo, impact } = selection.value
  const message = [
    t('admin.membres.confirmerSuppression', { pseudo }),
    t('admin.membres.impactArticles', impact.articles),
    t('admin.membres.impactLogements', { n: impact.logements, autres: impact.articlesAutres }),
    t('admin.membres.impactSocial', { tenues: impact.tenues, commentaires: impact.commentaires }),
    t('admin.membres.irreversible'),
  ].join('\n')
  if (!window.confirm(message)) {
    return
  }
  erreur.value = ''
  try {
    await api(`/admin/membres/${selection.value.id}`, { methode: 'DELETE' })
    succes.value = t('admin.membres.supprime', { pseudo })
    selection.value = null
    await charger()
  } catch (e) {
    erreur.value = Object.values(e.violations ?? {})[0] ?? e.message
  }
}

const estMoi = (membre) => membre.id === session.utilisateur.id

onMounted(charger)
</script>

<template>
  <div class="pile membres">
    <header class="entete">
      <h1 class="titre-page seulement-ordinateur">{{ t('admin.membres.titre') }}</h1>
      <label class="recherche">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
        <input v-model="recherche" type="search" :placeholder="t('admin.membres.rechercher')" :aria-label="t('admin.membres.rechercher')" />
      </label>
    </header>

    <p v-if="erreur" class="message-erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="bandeau-info" role="status">{{ succes }}</p>

    <div class="colonne-droite disposition">
      <div class="pile">
        <p v-if="!resultat" class="etat">{{ t('commun.chargement') }}</p>
        <p v-else-if="resultat.total === 0" class="etat">{{ t('admin.membres.aucun') }}</p>
        <template v-else>
          <!-- Téléphone : liste de membres -->
          <div class="lignes seulement-mobile">
            <button v-for="membre in resultat.membres" :key="membre.id" type="button" class="ligne ligne-bouton" :class="{ 'ligne--choisie': selection?.id === membre.id }" @click="ouvrir(membre)">
              <AvatarMembre :membre="membre" :taille="32" />
              <span class="ligne__textes">
                <b>@{{ membre.pseudo }}</b>
                <span class="texte-secondaire">{{ membre.email }}</span>
              </span>
              <span v-if="membre.estBloque" class="badge badge--plein">{{ t('admin.membres.statutBloque') }}</span>
              <span v-else class="badge">{{ t(`admin.roles.${membre.role}`) }}</span>
            </button>
          </div>

          <!-- Ordinateur : tableau -->
          <div class="defilement seulement-ordinateur">
            <table class="tableau-admin">
              <thead>
                <tr>
                  <th scope="col">{{ t('admin.membres.membre') }}</th>
                  <th scope="col">{{ t('admin.membres.email') }}</th>
                  <th scope="col">{{ t('admin.membres.role') }}</th>
                  <th scope="col">{{ t('admin.membres.inscritLe') }}</th>
                  <th scope="col">{{ t('admin.membres.statut') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="membre in resultat.membres" :key="membre.id" :class="{ 'ligne--choisie': selection?.id === membre.id }">
                  <td>
                    <button type="button" class="lien-tableau" @click="ouvrir(membre)">@{{ membre.pseudo }}</button>
                  </td>
                  <td>{{ membre.email }}</td>
                  <td><span class="badge">{{ t(`admin.roles.${membre.role}`) }}</span></td>
                  <td>{{ formatDate(new Date(membre.createdAt), { day: 'numeric', month: 'short' }) }}</td>
                  <td>
                    <span v-if="membre.estBloque" class="badge badge--plein">{{ t('admin.membres.statutBloque') }}</span>
                    <template v-else>{{ t('admin.membres.actif') }}</template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <nav v-if="resultat.pages > 1" class="pagination texte-secondaire" :aria-label="t('admin.membres.pagination')">
            <button type="button" class="lien lien--neutre" :disabled="page <= 1" @click="changerPage(-1)">‹ {{ t('admin.membres.precedent') }}</button>
            <b>{{ t('admin.membres.pageSur', { page, pages: resultat.pages }) }}</b>
            <button type="button" class="lien lien--neutre" :disabled="page >= resultat.pages" @click="changerPage(1)">{{ t('admin.membres.suivant') }} ›</button>
          </nav>
        </template>
      </div>

      <!-- Panneau de détail du membre choisi -->
      <aside v-if="selection" class="carte detail" :aria-label="t('admin.membres.fiche', { pseudo: selection.pseudo })">
        <div class="detail__entete">
          <b class="detail__titre">@{{ selection.pseudo }} · {{ t('admin.membres.detail') }}</b>
          <button type="button" class="bouton-icone detail__fermer" :aria-label="t('admin.membres.fermer')" @click="selection = null">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>
        <span class="texte-secondaire">
          {{ selection.email }} · {{ t('admin.membres.derniereConnexion') }} : {{ selection.derniereConnexionAt ? ilYA(selection.derniereConnexionAt) : t('admin.membres.jamais') }}
        </span>
        <RouterLink :to="{ name: 'membre', params: { pseudo: selection.pseudo } }" class="lien">{{ t('admin.membres.voirProfil') }}</RouterLink>

        <p v-if="estMoi(selection)" class="bandeau-info">{{ t('admin.membres.votreCompte') }}</p>
        <template v-else>
          <button type="button" class="bouton bouton--contour bouton--petit bouton--large" @click="basculerBlocage">
            {{ selection.estBloque ? t('admin.membres.debloquer') : t('admin.membres.bloquer') }}
          </button>
          <label class="champ">
            <span class="champ__libelle">{{ t('admin.membres.role') }}</span>
            <select class="saisie" :value="selection.role" @change="changerRole">
              <option v-for="role in ROLES" :key="role" :value="role">{{ t(`admin.roles.${role}`) }}</option>
            </select>
          </label>
          <p class="bandeau-info">
            {{ t('admin.membres.impactArticles', selection.impact.articles) }}
            {{ t('admin.membres.impactLogements', { n: selection.impact.logements, autres: selection.impact.articlesAutres }) }}
            {{ t('admin.membres.impactSocial', { tenues: selection.impact.tenues, commentaires: selection.impact.commentaires }) }}
          </p>
          <button type="button" class="bouton bouton--contour bouton--petit bouton--large danger" @click="supprimer">{{ t('admin.membres.supprimer') }}</button>
        </template>
      </aside>
      <aside v-else class="carte carte--grise texte-secondaire seulement-ordinateur">{{ t('admin.membres.choisir') }}</aside>
    </div>
  </div>
</template>

<style scoped>
.membres {
  gap: 14px;
}

.recherche {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 46px;
  padding: 0 12px;
  box-sizing: border-box;
  border: 1.5px solid var(--couleur-bordure);
  border-radius: 12px;
  background: var(--couleur-surface);
  color: var(--couleur-texte-secondaire);
}

.recherche:focus-within {
  border-color: var(--couleur-primaire);
}

.recherche input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--couleur-texte);
}

.disposition {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ligne-bouton {
  width: 100%;
  border-top: 0;
  border-left: 0;
  border-right: 0;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.ligne--choisie,
.ligne--choisie td {
  background: var(--couleur-primaire-pale);
}

.detail {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border: 2px solid var(--couleur-primaire);
}

.detail__entete {
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail__titre {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}

.detail__fermer {
  width: 32px;
  height: 32px;
  border-radius: 9px;
}

.danger {
  color: var(--couleur-erreur);
  border-color: var(--couleur-erreur);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
}

.lien--neutre {
  color: var(--couleur-texte-secondaire);
  text-decoration: none;
}

.lien--neutre:disabled {
  opacity: 0.4;
  cursor: default;
}

@media (min-width: 1024px) {
  .recherche {
    flex: none;
    width: 360px;
  }

  .disposition {
    display: grid;
    --largeur-colonne-fixe: 320px;
    gap: 28px;
  }
}
</style>
