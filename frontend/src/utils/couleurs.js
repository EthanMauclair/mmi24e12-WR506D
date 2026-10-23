/**
 * Fond d'une vignette d'article sans photo : sa couleur principale, ou une teinte neutre.
 */
export function fondVignette(codeHex) {
  return codeHex ?? 'var(--couleur-vignette-grege)'
}

/**
 * Indique si une couleur (#RRGGBB) est claire, pour afficher l'icône en foncé par-dessus.
 */
export function estCouleurClaire(codeHex) {
  if (!codeHex) {
    return false
  }
  const [r, g, b] = [1, 3, 5].map((debut) => parseInt(codeHex.slice(debut, debut + 2), 16))
  // Luminance perçue (formule ITU-R BT.601)
  return (r * 299 + g * 587 + b * 114) / 1000 > 180
}
