<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\FamilleCategorie;
use App\Repository\CategorieRepository;
use App\Repository\CouleurRepository;
use App\Service\CodeBarres\BaseInjoignableException;
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
     * Enregistre la photo d'un article (réduite et convertie en JPEG). L'analyse par l'IA est demandée à part,
     * pour que la fiche s'ouvre sans attendre.
     */
    #[Route('/photo', name: 'api_scan_photo', methods: ['POST'])]
    public function photo(Request $request, TraitementPhoto $photos): JsonResponse
    {
        $fichier = $request->files->get('photo');
        if (!$fichier instanceof UploadedFile) {
            return $this->reponseInvalide(['photo' => 'Ajoutez une photo de l\'article.']);
        }

        try {
            $chemin = $photos->enregistrer($fichier);
        } catch (PhotoInvalideException $e) {
            return $this->reponseInvalide(['photo' => $e->getMessage()]);
        }

        return $this->json(['photo' => $chemin], Response::HTTP_CREATED);
    }

    /**
     * Demande à l'IA de pré-remplir la fiche d'après une photo : le fichier lui-même (multipart, envoyé
     * en même temps qu'à /api/scans/photo pour gagner du temps) ou le chemin d'une photo déjà enregistrée.
     */
    #[Route('/analyse', name: 'api_scan_analyse', methods: ['POST'])]
    public function analyse(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        TraitementPhoto $photos,
        ReconnaissanceVetement $reconnaissance,
        CategorieRepository $categories,
        CouleurRepository $couleurs,
        RateLimiterFactoryInterface $scanIaLimiter,
    ): JsonResponse {
        // L'analyse peut durer : on libère tout de suite la session, sinon PHP la garde verrouillée
        // et bloque les autres requêtes de l'utilisateur (chargement de la fiche) jusqu'à la réponse de l'IA
        $request->getSession()->save();

        $fichier = $request->files->get('photo');
        if ($fichier instanceof UploadedFile) {
            try {
                $image = $photos->versBase64($fichier);
            } catch (PhotoInvalideException $e) {
                return $this->reponseInvalide(['photo' => $e->getMessage()]);
            }
        } else {
            $donnees = $this->lireJson($request);
            $chemin = \is_string($donnees['photo'] ?? null) ? $donnees['photo'] : '';
            if (!$photos->existe($chemin)) {
                return $this->reponseInvalide(['photo' => 'Cette photo n\'est plus disponible : reprenez la photo.']);
            }
            $image = $photos->enBase64($chemin);
        }

        $ia = ['disponible' => $reconnaissance->estDisponible(), 'reussie' => false, 'message' => null];
        $suggestions = null;

        if (!$ia['disponible']) {
            $ia['message'] = 'La reconnaissance automatique n\'est pas configurée : remplissez les champs vous-même.';
        } elseif (!$scanIaLimiter->create('utilisateur-'.$utilisateur->getId())->consume()->isAccepted()) {
            $ia['message'] = 'Vous avez atteint la limite de scans par IA pour cette heure : remplissez les champs vous-même.';
        } else {
            $resultat = $reconnaissance->reconnaitre(
                $image,
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

        return $this->json(['ia' => $ia, 'suggestions' => $suggestions]);
    }

    /**
     * Recherche un livre (ISBN) ou un vinyle (EAN) d'après son code-barres.
     */
    #[Route('/code-barres/{code}', name: 'api_scan_code_barres', requirements: ['code' => '[0-9Xx -]{8,20}'], methods: ['GET'])]
    public function codeBarres(string $code, RechercheCodeBarres $recherche, CategorieRepository $categories): JsonResponse
    {
        try {
            $fiche = $recherche->rechercher($code);
        } catch (BaseInjoignableException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }
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
