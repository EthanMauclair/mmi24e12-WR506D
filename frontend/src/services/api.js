// Client de l'API Symfony. En développement, Vite relaie /api vers le backend :
// le cookie de session est donc envoyé sur la même origine.

import { i18n, langueCourante } from '@/i18n'

const MESSAGES_PAR_STATUT = { 401: 'erreurs.nonConnecte', 403: 'erreurs.interdit', 404: 'erreurs.introuvable' }

export class ErreurApi extends Error {
  constructor(statut, message, violations = {}) {
    super(message)
    this.statut = statut
    this.violations = violations
  }
}

let surNonConnecte = () => {}

/**
 * Appelée quand l'API répond 401 en cours de navigation (session expirée).
 */
export function definirSurNonConnecte(rappel) {
  surNonConnecte = rappel
}

/**
 * @param {object} options
 * @param {object} [options.corps] envoyé en JSON
 * @param {FormData} [options.formulaire] envoyé en multipart (fichiers), le navigateur fixe le Content-Type
 */
export async function api(chemin, { methode = 'GET', corps, formulaire } = {}) {
  // L'API répond dans la langue de l'interface (messages d'erreur, catégories, couleurs…)
  const options = { method: methode, credentials: 'same-origin', headers: { Accept: 'application/json', 'Accept-Language': langueCourante() } }
  if (corps !== undefined) {
    options.headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(corps)
  } else if (formulaire !== undefined) {
    options.body = formulaire
  }

  let reponse
  try {
    reponse = await fetch(`/api${chemin}`, options)
  } catch {
    throw new ErreurApi(0, i18n.global.t('erreurs.reseau'))
  }

  if (reponse.status === 204) {
    return null
  }

  const donnees = await reponse.json().catch(() => null)
  if (!reponse.ok) {
    if (reponse.status === 401 && chemin !== '/moi' && chemin !== '/connexion') {
      surNonConnecte()
    }
    throw new ErreurApi(
      reponse.status,
      donnees?.message ?? i18n.global.t(MESSAGES_PAR_STATUT[reponse.status] ?? 'erreurs.inconnue'),
      donnees?.violations ?? {},
    )
  }

  return donnees
}
