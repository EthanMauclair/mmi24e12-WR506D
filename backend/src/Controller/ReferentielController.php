<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Couleur;
use App\Enum\EtatArticle;
use App\Enum\FormatVinyle;
use App\Repository\CategorieRepository;
use App\Repository\CouleurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Listes de valeurs des formulaires d'article.
 */
final class ReferentielController extends ApiController
{
    #[Route('/api/referentiels', name: 'api_referentiels', methods: ['GET'])]
    public function referentiels(CategorieRepository $categories, CouleurRepository $couleurs): JsonResponse
    {
        return $this->json([
            'categories' => array_map(static fn (Categorie $categorie) => [
                'id' => $categorie->getId(),
                'nom' => $categorie->getNom(),
                'chemin' => $categorie->getChemin(),
                'famille' => $categorie->getFamille()->value,
            ], $categories->findFeuilles()),
            'couleurs' => array_map(static fn (Couleur $couleur) => [
                'id' => $couleur->getId(),
                'nom' => $couleur->getNom(),
                'codeHex' => $couleur->getCodeHex(),
            ], $couleurs->findBy([], ['id' => 'ASC'])),
            'formatsVinyle' => array_map(
                static fn (FormatVinyle $format) => ['valeur' => $format->value, 'libelle' => $format->label()],
                FormatVinyle::cases(),
            ),
            'etats' => array_map(
                static fn (EtatArticle $etat) => ['valeur' => $etat->value, 'libelle' => $etat->label()],
                EtatArticle::cases(),
            ),
        ]);
    }
}
