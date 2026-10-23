import { api } from '@/services/api'

const CLE = 'penderie.logementActif'

export function memoriserLogementActif(id) {
  try {
    localStorage.setItem(CLE, String(id))
  } catch {
    // Stockage indisponible (navigation privée) : le premier logement sera utilisé
  }
}

export function logementMemorise() {
  try {
    return Number(localStorage.getItem(CLE))
  } catch {
    return null
  }
}

/**
 * Identifiant du logement affiché dans le dressing (le dernier choisi, sinon le premier),
 * ou null si l'utilisateur n'a aucun logement.
 */
export async function idLogementActif() {
  const logements = await api('/logements')
  const memorise = logements.find((logement) => logement.id === logementMemorise())
  return (memorise ?? logements[0])?.id ?? null
}
