import { createI18n } from 'vue-i18n'
import en from './en.json'
import fr from './fr.json'
import pt from './pt.json'

/**
 * Langues de l'application : le français est la langue principale, et la langue de secours
 * quand une traduction manque.
 */
export const LANGUES = [
  { code: 'fr', libelle: 'Français', abrege: 'FR', intl: 'fr-FR' },
  { code: 'en', libelle: 'English', abrege: 'EN', intl: 'en-GB' },
  { code: 'pt', libelle: 'Português', abrege: 'PT', intl: 'pt-PT' },
]

const CLE = 'penderie.langue'

/**
 * Langue mémorisée, sinon la première langue du navigateur que l'application connaît, sinon le français.
 */
function langueInitiale() {
  try {
    const memorisee = localStorage.getItem(CLE)
    if (LANGUES.some((langue) => langue.code === memorisee)) {
      return memorisee
    }
  } catch {
    // Stockage indisponible (navigation privée) : on se fie au navigateur
  }
  const preferees = navigator.languages ?? [navigator.language]
  for (const preferee of preferees) {
    const code = preferee?.slice(0, 2).toLowerCase()
    if (LANGUES.some((langue) => langue.code === code)) {
      return code
    }
  }
  return 'fr'
}

// Deux formes (« article | articles ») : en français, 0 et 1 sont au singulier ; en anglais et en portugais, seul 1 l'est
const singulierJusquaUn = (nombre, formes) => (formes === 2 ? (Math.abs(nombre) <= 1 ? 0 : 1) : Math.min(Math.abs(nombre), formes - 1))
const singulierPourUn = (nombre, formes) => (formes === 2 ? (Math.abs(nombre) === 1 ? 0 : 1) : Math.min(Math.abs(nombre), formes - 1))

export const i18n = createI18n({
  legacy: false,
  locale: langueInitiale(),
  fallbackLocale: 'fr',
  messages: { fr, en, pt },
  pluralRules: { fr: singulierJusquaUn, en: singulierPourUn, pt: singulierPourUn },
})

document.documentElement.lang = i18n.global.locale.value

export function langueCourante() {
  return i18n.global.locale.value
}

/**
 * Code de la langue courante pour Intl (dates, prix) : fr-FR, en-GB ou pt-PT.
 */
export function localeIntl() {
  return LANGUES.find((langue) => langue.code === langueCourante()).intl
}

export function changerLangue(code) {
  i18n.global.locale.value = code
  document.documentElement.lang = code
  try {
    localStorage.setItem(CLE, code)
  } catch {
    // Stockage indisponible : la langue vaut pour cette visite seulement
  }
}
