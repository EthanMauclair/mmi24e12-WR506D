import { i18n, localeIntl } from '@/i18n'

const t = (...parametres) => i18n.global.t(...parametres)

/**
 * Date dans la langue courante (« 12 nov. », « 12 Nov », « 12/11 »…), avec les options d'Intl.DateTimeFormat.
 */
export function formatDate(date, options = { day: 'numeric', month: 'short' }) {
  return new Intl.DateTimeFormat(localeIntl(), options).format(date)
}

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
  return formatDate(new Date(annee, mois - 1, jour))
}

/**
 * Échéance d'un prêt d'après le nombre de jours avant le retour prévu (négatif : en retard).
 */
export function echeance(joursRestants) {
  if (joursRestants === null) {
    return t('dates.sansDate')
  }
  if (joursRestants < 0) {
    return t('dates.enRetard')
  }
  if (joursRestants === 0) {
    return t('dates.aujourdhui')
  }
  if (joursRestants === 1) {
    return t('dates.demain')
  }
  return joursRestants < 21 ? t('dates.dansJours', { n: joursRestants }) : t('dates.dansSemaines', { n: Math.round(joursRestants / 7) })
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
    return t('dates.instant')
  }
  if (minutes < 60) {
    return t('dates.minutes', { n: minutes })
  }
  if (minutes < 24 * 60) {
    return t('dates.heures', { n: Math.floor(minutes / 60) })
  }
  if (minutes < 48 * 60) {
    return t('dates.hier')
  }
  return formatDate(date)
}
