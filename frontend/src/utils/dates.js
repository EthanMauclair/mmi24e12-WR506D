const FORMAT_JOUR = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })

/**
 * Date du jour au format AAAA-MM-JJ (valeur d'un champ date), dans le fuseau de l'utilisateur.
 */
export function aujourdhui() {
  const date = new Date()
  return [date.getFullYear(), date.getMonth() + 1, date.getDate()].map((n) => String(n).padStart(2, '0')).join('-')
}

/**
 * « 12 nov. » à partir d'une date AAAA-MM-JJ (lue comme une date locale, sans décalage de fuseau).
 */
export function jourCourt(dateIso) {
  const [annee, mois, jour] = dateIso.split('-').map(Number)
  return FORMAT_JOUR.format(new Date(annee, mois - 1, jour))
}

/**
 * Échéance d'un prêt d'après le nombre de jours avant le retour prévu (négatif : en retard).
 */
export function echeance(joursRestants) {
  if (joursRestants === null) {
    return 'Sans date'
  }
  if (joursRestants < 0) {
    return 'En retard'
  }
  if (joursRestants === 0) {
    return "Aujourd'hui"
  }
  if (joursRestants === 1) {
    return 'Demain'
  }
  return joursRestants < 21 ? `Dans ${joursRestants} j` : `Dans ${Math.round(joursRestants / 7)} sem.`
}

/**
 * Date relative courte, comme sur les réseaux sociaux : « à l'instant », « il y a 5 min », « il y a 2 h »,
 * « hier », puis la date (« 3 nov. »).
 *
 * @param {string} dateIso date au format ISO 8601, telle que renvoyée par l'API
 */
export function ilYA(dateIso) {
  const date = new Date(dateIso)
  const minutes = Math.floor((Date.now() - date.getTime()) / 60000)
  if (minutes < 1) {
    return "à l'instant"
  }
  if (minutes < 60) {
    return `il y a ${minutes} min`
  }
  if (minutes < 24 * 60) {
    return `il y a ${Math.floor(minutes / 60)} h`
  }
  if (minutes < 48 * 60) {
    return 'hier'
  }
  return FORMAT_JOUR.format(date)
}
