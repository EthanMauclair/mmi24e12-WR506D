const FORMAT_JOUR = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })

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
