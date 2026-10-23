<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\FamilleCategorie;
use App\Repository\CategorieRepository;
use App\Repository\CouleurRepository;
use App\Service\CodeBarres\RechercheCodeBarres;
use App\Service\Ia\ReconnaissanceVetement;
use App\Service\Photo\PhotoInvalideException;
use App\Service\Photo\TraitementPhoto;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/scans')]
final class ScanController extends ApiController
{
    /**
     * Enregistre la photo d'un vêtement et demande à l'IA de pré-remplir la fiche.
     * La photo est toujours conservée, même si l'IA est indisponible.
     */
    #[Route('/vetement', name: 'api_scan_vetement', methods: ['POST'])]
    public function vetement(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        TraitementPhoto $photos,
        ReconnaissanceVetement $reconnaissance,
        CategorieRepository $categories,
        CouleurRepository $couleurs,
        RateLimiterFactoryInterface $scanIaLimiter,
    ): JsonResponse {
        $fichier = $request->files->get('photo');
        if (!$fichier instanceof UploadedFile) {
            return $this->reponseInvalide(['photo' => 'Ajoutez une photo de l\'article.']);
        }

        try {
            $chemin = $photos->enregistrer($fichier);
        } catch (PhotoInvalideException $e) {
            return $this->reponseInvalide(['photo' => $e->getMessage()]);
        }

        $ia = ['disponible' => $reconnaissance->estDisponible(), 'reussie' => false, 'message' => null];
        $suggestions = null;

        if (!$ia['disponible']) {
            $ia['message'] = 'La reconnaissance automatique n\'est pas configurée : remplissez les champs vous-même.';
        } elseif (!$scanIaLimiter->create('utilisateur-'.$utilisateur->getId())->consume()->isAccepted()) {
            $ia['message'] = 'Vous avez atteint la limite de scans par IA pour cette heure : remplissez les champs vous-même.';
        } else {
            $resultat = $reconnaissance->reconnaitre(
                $photos->enBase64($chemin),
                $categories->findFeuilles([FamilleCategorie::VETEMENT, FamilleCategorie::ACCESSOIRE]),
                $couleurs->findBy([], ['id' => 'ASC']),
            );
            if (null === $resultat) {
                $ia['message'] = 'L\'IA n\'a pas pu analyser la photo pour le moment : remplissez les champs vous-même.';
            } else {
                $ia['reussie'] = true;
                $suggestions = [
                    'categorie' => null === $resultat['categorie'] ? null : [
                        'id' => $resultat['categorie']->getId(),
                        'chemin' => $resultat['categorie']->getChemin(),
                    ],
                    'couleur' => null === $resultat['couleur'] ? null : [
                        'id' => $resultat['couleur']->getId(),
                        'nom' => $resultat['couleur']->getNom(),
                    ],
                    'marque' => $resultat['marque'],
                    'titre' => $resultat['titre'],
                ];
            }
        }

        return $this->json(['photo' => $chemin, 'ia' => $ia, 'suggestions' => $suggestions], Response::HTTP_CREATED);
    }

    /**
     * Recherche un livre (ISBN) ou un vinyle (EAN) d'après son code-barres.
     */
    #[Route('/code-barres/{code}', name: 'api_scan_code_barres', requirements: ['code' => '[0-9Xx -]{8,20}'], methods: ['GET'])]
    public function codeBarres(string $code, RechercheCodeBarres $recherche, CategorieRepository $categories): JsonResponse
    {
        $fiche = $recherche->rechercher($code);
        if (null === $fiche) {
            return $this->json(
                ['message' => 'Aucun livre ni vinyle trouvé pour ce code-barres. Vous pouvez saisir l\'article vous-même.', 'code' => RechercheCodeBarres::normaliser($code)],
                Response::HTTP_NOT_FOUND,
            );
        }

        $categorie = $categories->findParChemin($fiche['categorie']);
        $fiche['categorie'] = null === $categorie ? null : ['id' => $categorie->getId(), 'chemin' => $categorie->getChemin()];

        return $this->json($fiche);
    }
}
