<?php

namespace App\Service\Photo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Normalise les photos des scans : réduction, conversion en JPEG, enregistrement, encodage en base64.
 */
final class TraitementPhoto
{
    public const DOSSIER_PUBLIC = '/uploads/scans';
    public const COTE_MAXIMUM = 1024;
    private const QUALITE_JPEG = 82;
    private const FORMAT_CHEMIN = '#^/uploads/scans/[a-f0-9]{32}\.jpg$#';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')] private readonly string $dossierPublic,
    ) {
    }

    /**
     * Réduit l'image à COTE_MAXIMUM pixels sur son plus grand côté, la convertit en JPEG et l'enregistre.
     *
     * @return string chemin public de la photo, ex. /uploads/scans/3f2a….jpg
     *
     * @throws PhotoInvalideException si le fichier n'est pas une image lisible
     */
    public function enregistrer(UploadedFile $fichier): string
    {
        $contenu = $fichier->isValid() ? @file_get_contents($fichier->getPathname()) : false;
        $source = false === $contenu ? false : @imagecreatefromstring($contenu);
        if (false === $source) {
            throw new PhotoInvalideException('Ce fichier n\'est pas une image lisible (JPEG, PNG ou WebP).');
        }

        $image = $this->reduire($source);

        $dossier = $this->dossierPublic.self::DOSSIER_PUBLIC;
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException(\sprintf('Impossible de créer le dossier %s.', $dossier));
        }

        $chemin = self::DOSSIER_PUBLIC.'/'.bin2hex(random_bytes(16)).'.jpg';
        imagejpeg($image, $this->dossierPublic.$chemin, self::QUALITE_JPEG);

        return $chemin;
    }

    /**
     * Data URL base64 de la photo, prête à être envoyée à un modèle d'IA.
     */
    public function enBase64(string $chemin): string
    {
        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($this->cheminAbsolu($chemin)));
    }

    /**
     * Indique si le chemin désigne une photo de scan enregistrée par ce service.
     */
    public function existe(string $chemin): bool
    {
        return 1 === preg_match(self::FORMAT_CHEMIN, $chemin) && is_file($this->cheminAbsolu($chemin));
    }

    private function cheminAbsolu(string $chemin): string
    {
        return $this->dossierPublic.$chemin;
    }

    private function reduire(\GdImage $source): \GdImage
    {
        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $ratio = min(1, self::COTE_MAXIMUM / max($largeur, $hauteur));
        $nouvelleLargeur = max(1, (int) round($largeur * $ratio));
        $nouvelleHauteur = max(1, (int) round($hauteur * $ratio));

        // Fond blanc : le JPEG ne gère pas la transparence (PNG, WebP)
        $image = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);

        return $image;
    }
}
