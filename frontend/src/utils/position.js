/**
 * Position de l'utilisateur seulement s'il a déjà autorisé la localisation : aucune question n'est posée.
 * Sert à afficher la météo sur le dressing sans demander l'autorisation dès l'arrivée sur l'application.
 *
 * @returns {Promise<{lat: number, lon: number}|null>}
 */
export async function positionDejaAutorisee() {
  try {
    const permission = await navigator.permissions?.query({ name: 'geolocation' })
    return permission?.state === 'granted' ? await lirePosition() : null
  } catch {
    return null
  }
}

/**
 * Position de l'utilisateur pour la météo, ou null s'il refuse la localisation ou si elle tarde.
 * Une position de moins de 10 minutes est réutilisée.
 *
 * @returns {Promise<{lat: number, lon: number}|null>}
 */
export function lirePosition() {
  return new Promise((resoudre) => {
    if (!navigator.geolocation) {
      resoudre(null)
      return
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resoudre({ lat: coords.latitude, lon: coords.longitude }),
      () => resoudre(null),
      { timeout: 10000, maximumAge: 10 * 60 * 1000 },
    )
  })
}
