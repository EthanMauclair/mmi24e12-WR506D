// Image d'une tenue au format portrait d'Instagram (1080 × 1350), dessinée dans un canvas :
// les pièces de la tenue (photo, sinon leur couleur), son titre, le pseudo de son créateur.

const LARGEUR = 1080
const HAUTEUR = 1350
const MARGE = 60
const ESPACE = 12

function couleur(nom) {
  return getComputedStyle(document.documentElement).getPropertyValue(`--couleur-${nom}`).trim()
}

function chargerImage(source) {
  return new Promise((resoudre) => {
    const image = new Image()
    image.onload = () => resoudre(image)
    image.onerror = () => resoudre(null)
    image.src = source
  })
}

// Dessine l'image en la recadrant pour remplir la zone, comme object-fit: cover
function dessinerCouvrant(contexte, image, x, y, largeur, hauteur) {
  const echelle = Math.max(largeur / image.width, hauteur / image.height)
  const [l, h] = [largeur / echelle, hauteur / echelle]
  contexte.drawImage(image, (image.width - l) / 2, (image.height - h) / 2, l, h, x, y, largeur, hauteur)
}

/**
 * @param {object} tenue tenue renvoyée par l'API
 * @returns {Promise<Blob>} image PNG
 */
export async function imageTenue(tenue) {
  await document.fonts.ready
  const canvas = document.createElement('canvas')
  canvas.width = LARGEUR
  canvas.height = HAUTEUR
  const contexte = canvas.getContext('2d')

  contexte.fillStyle = couleur('fond')
  contexte.fillRect(0, 0, LARGEUR, HAUTEUR)

  // Comme sur le fil : une grande pièce à gauche, les suivantes empilées à droite
  const pieces = tenue.articles.slice(0, 3)
  const cote = LARGEUR - 2 * MARGE
  const zones = pieces.length === 1
    ? [[0, 0, cote, cote]]
    : [[0, 0, (cote - ESPACE) / 2, cote], ...pieces.slice(1).map((_, index, suivantes) => {
        const hauteur = (cote - ESPACE * (suivantes.length - 1)) / suivantes.length
        return [(cote + ESPACE) / 2, index * (hauteur + ESPACE), (cote - ESPACE) / 2, hauteur]
      })]

  contexte.save()
  contexte.beginPath()
  contexte.roundRect(MARGE, MARGE, cote, cote, 40)
  contexte.clip()
  for (const [index, article] of pieces.entries()) {
    const [x, y, l, h] = zones[index]
    contexte.fillStyle = article.couleur ?? couleur('vignette-grege')
    contexte.fillRect(MARGE + x, MARGE + y, l, h)
    const photo = article.photo ? await chargerImage(article.photo) : null
    if (photo) {
      dessinerCouvrant(contexte, photo, MARGE + x, MARGE + y, l, h)
    }
  }
  contexte.restore()

  const police = getComputedStyle(document.body).fontFamily
  contexte.textBaseline = 'alphabetic'
  contexte.fillStyle = couleur('texte')
  contexte.font = `800 60px ${police}`
  contexte.fillText(tenue.titre, MARGE, MARGE + cote + 110, cote)
  contexte.fillStyle = couleur('texte-secondaire')
  contexte.font = `600 38px ${police}`
  contexte.fillText(`@${tenue.createur.pseudo}`, MARGE, MARGE + cote + 170, cote)
  contexte.fillStyle = couleur('primaire')
  contexte.font = `800 38px ${police}`
  contexte.textAlign = 'right'
  contexte.fillText('Penderie', LARGEUR - MARGE, MARGE + cote + 170)

  return new Promise((resoudre) => canvas.toBlob(resoudre, 'image/png'))
}
