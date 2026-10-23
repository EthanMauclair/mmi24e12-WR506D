export const COTE_MAXIMUM = 1024
const QUALITE_JPEG = 0.85

/**
 * Réduit une image (fichier, vidéo ou canvas) à COTE_MAXIMUM pixels sur son plus grand côté
 * et la convertit en JPEG, avant l'envoi au serveur.
 *
 * @param {Blob|HTMLVideoElement} source
 * @returns {Promise<Blob>}
 */
export async function reduireEnJpeg(source) {
  // createImageBitmap applique l'orientation EXIF des photos de téléphone
  const image = source instanceof Blob ? await createImageBitmap(source) : source
  const largeur = image.videoWidth || image.width
  const hauteur = image.videoHeight || image.height
  const ratio = Math.min(1, COTE_MAXIMUM / Math.max(largeur, hauteur))

  const canvas = document.createElement('canvas')
  canvas.width = Math.round(largeur * ratio)
  canvas.height = Math.round(hauteur * ratio)
  const contexte = canvas.getContext('2d')
  // Fond blanc : le JPEG ne gère pas la transparence
  contexte.fillStyle = '#ffffff'
  contexte.fillRect(0, 0, canvas.width, canvas.height)
  contexte.drawImage(image, 0, 0, canvas.width, canvas.height)

  return new Promise((resoudre, rejeter) => {
    canvas.toBlob(
      (blob) => (blob ? resoudre(blob) : rejeter(new Error("Impossible de convertir l'image."))),
      'image/jpeg',
      QUALITE_JPEG,
    )
  })
}
