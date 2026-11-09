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
