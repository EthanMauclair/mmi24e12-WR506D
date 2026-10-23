<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Piece;
use App\Entity\Stockage;
use App\Entity\Utilisateur;
use App\Enum\TypeStockage;
use App\Repository\ArticleRepository;
use App\Security\Voter\LogementVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/pieces/{id}', requirements: ['id' => '\d+'])]
final class PieceController extends ApiController
{
    #[Route('', name: 'api_piece', methods: ['GET'])]
    public function detail(
        #[MapEntity(id: 'id')] Piece $piece,
        #[CurrentUser] Utilisateur $utilisateur,
        ArticleRepository $articles,
        Presentateur $presentateur,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(LogementVoter::VOIR, $piece->getLogement());

        $articlesVisibles = $articles->findVisiblesDansPiece($piece, $utilisateur);
        $nombresParStockage = array_count_values(array_map(
            static fn ($article) => $article->getStockage()->getId(),
            $articlesVisibles,
        ));
        $logement = $piece->getLogement();

        return $this->json([
            'id' => $piece->getId(),
            'nom' => $piece->getNom(),
            'logement' => ['id' => $logement->getId(), 'nom' => $logement->getNom()],
            'peutGerer' => $this->isGranted(LogementVoter::GERER, $logement),
            'stockages' => array_map(static fn (Stockage $stockage) => [
                'id' => $stockage->getId(),
                'nom' => $stockage->getNom(),
                'type' => $stockage->getType()->value,
                'libelleType' => $stockage->getType()->label(),
                'nbArticles' => $nombresParStockage[$stockage->getId()] ?? 0,
            ], $piece->getStockages()->toArray()),
            'articles' => $presentateur->articlesResumes($articlesVisibles),
        ]);
    }

    #[Route('/stockages', name: 'api_piece_stockage_creer', methods: ['POST'])]
    public function creerStockage(
        #[MapEntity(id: 'id')] Piece $piece,
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(LogementVoter::GERER, $piece->getLogement());

        $donnees = $this->lireJson($request);
        $type = TypeStockage::tryFrom((string) ($donnees['type'] ?? ''));
        if (null === $type) {
            return $this->reponseInvalide(['type' => 'Choisissez un type de stockage.']);
        }

        $stockage = (new Stockage())->setNom(trim((string) ($donnees['nom'] ?? '')))->setType($type);
        $piece->addStockage($stockage);

        $messages = $this->messagesParChamp($validator->validate($stockage));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $em->flush();

        return $this->json([
            'id' => $stockage->getId(),
            'nom' => $stockage->getNom(),
            'type' => $type->value,
            'libelleType' => $type->label(),
            'nbArticles' => 0,
        ], Response::HTTP_CREATED);
    }
}
