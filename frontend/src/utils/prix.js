import { localeIntl } from '@/i18n'

/**
 * Prix en euros dans la langue courante : « 18 € », « 12,50 € », « €12.50 »…
 */
export function formatPrix(prix) {
  return new Intl.NumberFormat(localeIntl(), {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: Number.isInteger(prix) ? 0 : 2,
  }).format(prix)
}
