<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\LogementRepository;
use App\Security\Voter\LogementVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/logements')]
final class LogementController extends ApiController
{
    public function __construct(
        private readonly Presentateur $presentateur,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_logements', methods: ['GET'])]
    public function liste(#[CurrentUser] Utilisateur $utilisateur, LogementRepository $logements): JsonResponse
    {
        return $this->json(array_map(
            fn (Logement $logement) => $this->presentateur->logementResume($logement, $utilisateur),
            $logements->findPourUtilisateur($utilisateur),
        ));
    }

    #[Route('', name: 'api_logement_creer', methods: ['POST'])]
    public function creer(Request $request, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $donnees = $this->lireJson($request);
        $logement = (new Logement())
            ->setNom(trim((string) ($donnees['nom'] ?? '')))
            ->setProprietaire($utilisateur);

        $messages = $this->messagesParChamp($this->validator->validate($logement), ['proprietaire' => 'general']);
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $this->em->persist($logement);
        $this->em->flush();

        return $this->json($this->presentateur->logementResume($logement, $utilisateur), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_logement', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(LogementVoter::VOIR, 'logement')]
    public function detail(
        #[MapEntity(id: 'id')] Logement $logement,
        #[CurrentUser] Utilisateur $utilisateur,
        ArticleRepository $articles,
    ): JsonResponse {
        $nombres = $articles->compterDansLogement($logement, $utilisateur);
        $nombresParPiece = $articles->compterParPiece($logement, $utilisateur);

        $pieces = array_map(static fn (Piece $piece) => [
            'id' => $piece->getId(),
            'nom' => $piece->getNom(),
            'stockages' => array_map(static fn ($stockage) => $stockage->getNom(), $piece->getStockages()->toArray()),
            'nbArticles' => $nombresParPiece[$piece->getId()] ?? 0,
        ], $logement->getPieces()->toArray());

        return $this->json([
            ...$this->presentateur->logementResume($logement, $utilisateur),
            'peutGerer' => $this->isGranted(LogementVoter::GERER, $logement),
            'nbArticles' => $nombres['articles'],
            'nbPretes' => $nombres['pretes'],
            'nbPieces' => \count($pieces),
            'pieces' => $pieces,
        ]);
    }

    #[Route('/{id}/pieces', name: 'api_logement_piece_creer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(LogementVoter::GERER, 'logement')]
    public function creerPiece(#[MapEntity(id: 'id')] Logement $logement, Request $request): JsonResponse
    {
        $donnees = $this->lireJson($request);
        $piece = (new Piece())->setNom(trim((string) ($donnees['nom'] ?? '')));
        $logement->addPiece($piece);

        $messages = $this->messagesParChamp($this->validator->validate($piece));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $this->em->flush();

        return $this->json(['id' => $piece->getId(), 'nom' => $piece->getNom()], Response::HTTP_CREATED);
    }

    /**
     * Emplacements (« Pièce › Stockage ») et étiquettes proposés à l'ajout d'un article.
     */
    #[Route('/{id}/rangement', name: 'api_logement_rangement', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(LogementVoter::VOIR, 'logement')]
    public function rangement(#[MapEntity(id: 'id')] Logement $logement): JsonResponse
    {
        $stockages = [];
        foreach ($logement->getPieces() as $piece) {
            foreach ($piece->getStockages() as $stockage) {
                $stockages[] = ['id' => $stockage->getId(), 'chemin' => $piece->getNom().' › '.$stockage->getNom()];
            }
        }

        return $this->json([
            'stockages' => $stockages,
            'etiquettes' => array_map(
                static fn ($etiquette) => ['id' => $etiquette->getId(), 'nom' => $etiquette->getNom()],
                $logement->getEtiquettes()->toArray(),
            ),
        ]);
    }

    #[Route('/{id}/recherche', name: 'api_logement_recherche', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(LogementVoter::VOIR, 'logement')]
    public function recherche(
        #[MapEntity(id: 'id')] Logement $logement,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        ArticleRepository $articles,
    ): JsonResponse {
        $texte = trim($request->query->getString('q'));
        if (mb_strlen($texte) < 2) {
            return $this->json([]);
        }

        return $this->json($this->presentateur->articlesResumes($articles->rechercher($logement, $utilisateur, $texte)));
    }
}
