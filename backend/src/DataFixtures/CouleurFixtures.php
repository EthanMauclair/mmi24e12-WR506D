<?php

namespace App\DataFixtures;

use App\Entity\Couleur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CouleurFixtures extends Fixture
{
    private const COULEURS = [
        'Noir' => '#000000',
        'Blanc' => '#FFFFFF',
        'Gris' => '#9CA3AF',
        'Beige' => '#E8D9B5',
        'Marron' => '#7C4A2D',
        'Bleu' => '#2563EB',
        'Bleu marine' => '#1E3A8A',
        'Bleu clair' => '#93C5FD',
        'Vert' => '#16A34A',
        'Kaki' => '#6B7140',
        'Rouge' => '#DC2626',
        'Bordeaux' => '#7F1D1D',
        'Rose' => '#F472B6',
        'Violet' => '#7C3AED',
        'Jaune' => '#FACC15',
        'Orange' => '#F97316',
        'Doré' => '#D4AF37',
        'Argenté' => '#C0C0C0',
        'Multicolore' => '#808080',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::COULEURS as $nom => $codeHex) {
            $manager->persist((new Couleur())->setNom($nom)->setCodeHex($codeHex));
        }

        $manager->flush();
    }
}
