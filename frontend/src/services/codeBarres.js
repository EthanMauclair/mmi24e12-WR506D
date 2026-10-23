import { BarcodeDetector, prepareZXingModule } from 'barcode-detector/ponyfill'
import urlWasm from 'zxing-wasm/reader/zxing_reader.wasm?url'

// Le lecteur ZXing (WebAssembly) est servi par l'application au lieu d'un CDN
prepareZXingModule({
  overrides: {
    locateFile: (chemin, prefixe) => (chemin.endsWith('.wasm') ? urlWasm : prefixe + chemin),
  },
})

// Codes-barres des livres (ISBN = EAN-13) et des disques
const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e']

let lecteur = null

/**
 * Lit le premier code-barres visible sur une image, une vidéo ou un canvas.
 *
 * @returns {Promise<string|null>} les chiffres du code, ou null si aucun code n'est lisible
 */
export async function lireCodeBarres(source) {
  lecteur ??= new BarcodeDetector({ formats: FORMATS })
  const codes = await lecteur.detect(source)
  return codes[0]?.rawValue ?? null
}
