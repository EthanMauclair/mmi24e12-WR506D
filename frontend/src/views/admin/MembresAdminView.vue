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
  <div class="admin-pile">
    <div>
      <h1 class="admin-titre">{{ t('admin.membres.titre') }}</h1>
      <p class="admin-sous-titre">{{ t('admin.membres.sousTitre') }}</p>
    </div>

    <input v-model="recherche" type="search" class="admin-saisie recherche" :placeholder="t('admin.membres.rechercher')" :aria-label="t('admin.membres.rechercher')" />

    <p v-if="erreur" class="admin-message admin-message--erreur" role="alert">{{ erreur }}</p>
    <p v-if="succes" class="admin-message admin-message--succes" role="status">{{ succes }}</p>

    <div class="disposition" :class="{ 'disposition--detail': selection }">
      <section class="admin-carte">
        <p v-if="!resultat" class="admin-etat">{{ t('commun.chargement') }}</p>
        <p v-else-if="resultat.total === 0" class="admin-etat">{{ t('admin.membres.aucun') }}</p>
        <template v-else>
          <div class="admin-defilement">
            <table class="admin-tableau">
              <thead>
                <tr>
                  <th scope="col">{{ t('admin.membres.membre') }}</th>
                  <th scope="col">{{ t('admin.membres.role') }}</th>
                  <th scope="col">{{ t('admin.membres.inscription') }}</th>
                  <th scope="col">{{ t('admin.membres.derniereConnexion') }}</th>
                  <th scope="col" class="nombre">{{ t('admin.membres.articles') }}</th>
                  <th scope="col"><span class="visuellement-cache">{{ t('admin.membres.actions') }}</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="membre in resultat.membres" :key="membre.id" :class="{ 'ligne--choisie': selection?.id === membre.id }">
                  <td>
                    <span class="identite">
                      <AvatarMembre :membre="membre" :taille="32" />
                      <span>
                        <span class="identite__pseudo">@{{ membre.pseudo }}</span>
                        <span v-if="membre.estBloque" class="admin-pastille admin-pastille--alerte">{{ t('admin.membres.statutBloque') }}</span>
                        <span v-if="membre.estMineur" class="admin-pastille">{{ t('admin.membres.mineur') }}</span>
                        <span class="admin-secondaire identite__email">{{ membre.email }}</span>
                      </span>
                    </span>
                  </td>
                  <td>
                    <span class="admin-pastille" :class="{ 'admin-pastille--accent': membre.role !== 'MEMBRE' }">{{ t(`admin.roles.${membre.role}`) }}</span>
                  </td>
                  <td class="admin-secondaire">{{ formatDate(new Date(membre.createdAt), { day: 'numeric', month: 'short', year: 'numeric' }) }}</td>
                  <td class="admin-secondaire">{{ membre.derniereConnexionAt ? ilYA(membre.derniereConnexionAt) : t('admin.membres.jamais') }}</td>
                  <td class="nombre">{{ membre.nbArticles }}</td>
                  <td class="actions">
                    <button type="button" class="admin-bouton" @click="ouvrir(membre)">{{ t('admin.membres.gerer') }}</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <nav v-if="resultat.pages > 1" class="pagination" :aria-label="t('admin.membres.pagination')">
            <button type="button" class="admin-bouton" :disabled="page <= 1" @click="changerPage(-1)">{{ t('admin.membres.precedent') }}</button>
            <span class="admin-secondaire">{{ t('admin.membres.page', { page, pages: resultat.pages, total: resultat.total }) }}</span>
            <button type="button" class="admin-bouton" :disabled="page >= resultat.pages" @click="changerPage(1)">{{ t('admin.membres.suivant') }}</button>
          </nav>
        </template>
      </section>

      <aside v-if="selection" class="admin-carte detail" :aria-label="t('admin.membres.fiche', { pseudo: selection.pseudo })">
        <div class="detail__entete">
          <AvatarMembre :membre="selection" :taille="48" />
          <div class="detail__identite">
            <RouterLink :to="{ name: 'membre', params: { pseudo: selection.pseudo } }" class="identite__pseudo">@{{ selection.pseudo }}</RouterLink>
            <span class="admin-secondaire">{{ selection.nomComplet ?? selection.email }}</span>
          </div>
          <button type="button" class="bouton-icone detail__fermer" :aria-label="t('admin.membres.fermer')" @click="selection = null">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>

        <dl class="faits">
          <div><dt>{{ t('admin.membres.email') }}</dt><dd>{{ selection.email }}</dd></div>
          <div><dt>{{ t('admin.membres.profil') }}</dt><dd>{{ selection.estPrive ? t('admin.membres.prive') : t('admin.membres.public') }}</dd></div>
          <div><dt>{{ t('admin.membres.articles') }}</dt><dd>{{ selection.impact.articles }}</dd></div>
          <div><dt>{{ t('admin.membres.logements') }}</dt><dd>{{ selection.impact.logements }}</dd></div>
          <div><dt>{{ t('admin.membres.tenues') }}</dt><dd>{{ selection.impact.tenues }}</dd></div>
          <div><dt>{{ t('admin.membres.commentaires') }}</dt><dd>{{ selection.impact.commentaires }}</dd></div>
        </dl>

        <p v-if="estMoi(selection)" class="admin-message admin-message--info">{{ t('admin.membres.votreCompte') }}</p>
        <template v-else>
          <label class="admin-champ">
            {{ t('admin.membres.role') }}
            <select class="admin-saisie" :value="selection.role" @change="changerRole">
              <option v-for="role in ROLES" :key="role" :value="role">{{ t(`admin.roles.${role}`) }}</option>
            </select>
          </label>
          <p class="admin-secondaire">{{ t(`admin.membres.descriptionRoles.${selection.role}`) }}</p>

          <div class="detail__actions">
            <button type="button" class="admin-bouton" @click="basculerBlocage">
              {{ selection.estBloque ? t('admin.membres.debloquer') : t('admin.membres.bloquer') }}
            </button>
            <button type="button" class="admin-bouton admin-bouton--danger" @click="supprimer">{{ t('admin.membres.supprimer') }}</button>
          </div>
          <p class="admin-secondaire">{{ t('admin.membres.aideBlocage') }}</p>
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.recherche {
  width: 100%;
  max-width: 420px;
  height: 42px;
}

.disposition {
  display: grid;
  gap: 16px;
  /* minmax(0, …) : le tableau défile dans sa carte au lieu d'élargir la page */
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
}

@media (min-width: 900px) {
  .disposition--detail {
    grid-template-columns: minmax(0, 1fr) 340px;
  }
}

.identite {
  display: flex;
  align-items: center;
  gap: 10px;
}

.identite__pseudo {
  font-weight: 700;
  margin-right: 6px;
}

.identite__email {
  display: block;
}

.ligne--choisie td {
  background: var(--couleur-primaire-pale);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.detail {
  gap: 14px;
}

.detail__entete {
  display: flex;
  align-items: center;
  gap: 12px;
}

.detail__identite {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow-wrap: anywhere;
}

.detail__fermer {
  width: 36px;
  height: 36px;
}

.faits {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.faits div {
  min-width: 0;
}

.faits dt {
  font-size: 12px;
  color: var(--couleur-texte-secondaire);
}

.faits dd {
  margin: 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.detail__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.detail__actions .admin-bouton + .admin-bouton {
  margin-left: 0;
}

.detail p {
  margin: 0;
}
</style>
