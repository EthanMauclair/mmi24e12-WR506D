// Client de l'API Symfony. En développement, Vite relaie /api vers le backend :
// le cookie de session est donc envoyé sur la même origine.

const MESSAGES_PAR_STATUT = {
  401: 'Vous devez être connecté.',
  403: "Vous n'avez pas accès à cette ressource.",
  404: 'Élément introuvable.',
}

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
  const options = { method: methode, credentials: 'same-origin', headers: { Accept: 'application/json' } }
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
    throw new ErreurApi(0, 'Impossible de joindre le serveur. Vérifiez votre connexion.')
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
      donnees?.message ?? MESSAGES_PAR_STATUT[reponse.status] ?? 'Une erreur est survenue. Réessayez plus tard.',
      donnees?.violations ?? {},
    )
  }

  return donnees
}
