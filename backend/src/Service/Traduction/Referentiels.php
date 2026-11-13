<?php

namespace App\Service\Traduction;

use App\Entity\Categorie;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traduit les données de référence (catégories, couleurs, états, formats, météo) dans la langue de la requête.
 *
 * Elles sont enregistrées en français, la langue principale, qui sert de clé dans translations/referentiels.*.yaml.
 * Les textes saisis par les membres (titres, notes, noms de pièces…) ne sont jamais traduits.
 */
final class Referentiels
{
    private const DOMAINE = 'referentiels';
    private const SEPARATEUR = ' › ';

    public function __construct(private readonly TranslatorInterface $traducteur)
    {
    }

    public function nom(?string $nom): ?string
    {
        return null === $nom ? null : $this->traducteur->trans($nom, [], self::DOMAINE);
    }

    /**
     * Libellé avec paramètres, par exemple « Taille %taille% ».
     *
     * @param array<string, string|int> $parametres
     */
    public function libelle(string $libelle, array $parametres): string
    {
        return $this->traducteur->trans($libelle, $parametres, self::DOMAINE);
    }

    /**
     * Chemin d'une catégorie (« Femmes › Pulls et sweats › Pulls »), traduit segment par segment.
     */
    public function chemin(Categorie|string $categorie): string
    {
        $chemin = $categorie instanceof Categorie ? $categorie->getChemin() : $categorie;

        return implode(self::SEPARATEUR, array_map($this->nom(...), explode(self::SEPARATEUR, $chemin)));
    }
}
