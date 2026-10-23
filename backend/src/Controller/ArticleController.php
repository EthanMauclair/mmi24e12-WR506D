<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Article;
use App\Entity\Utilisateur;
use App\Repository\LogementRepository;
use App\Security\Voter\ArticleVoter;
use App\Security\Voter\LogementVoter;
use App\Service\Article\ArticleInvalideException;
use App\Service\Article\CreationArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/articles')]
final class ArticleController extends ApiController
{
    public function __construct(private readonly Presentateur $presentateur)
    {
    }

    /**
     * Ajoute un article (après un scan ou une saisie manuelle) dans un logement dont on est membre.
     */
    #[Route('', name: 'api_article_creer', methods: ['POST'])]
    public function creer(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        LogementRepository $logements,
        CreationArticle $creation,
    ): JsonResponse {
        $donnees = $this->lireJson($request);
        $logement = $logements->find((int) ($donnees['logementId'] ?? 0));
        if (null === $logement) {
            return $this->reponseInvalide(['logementId' => 'Choisissez un logement.']);
        }
        $this->denyAccessUnlessGranted(LogementVoter::VOIR, $logement);

        try {
            $article = $creation->creer($logement, $utilisateur, $donnees);
        } catch (ArticleInvalideException $e) {
            return $this->reponseInvalide($e->messages);
        }

        return $this->json($this->presenter($article), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_article', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ArticleVoter::VOIR, 'article')]
    public function detail(#[MapEntity(id: 'id')] Article $article): JsonResponse
    {
        return $this->json($this->presenter($article));
    }

    /**
     * Seul le champ estPersonnel est modifiable pour l'instant.
     */
    #[Route('/{id}', name: 'api_article_modifier', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(ArticleVoter::VOIR, 'article')]
    public function modifier(
        #[MapEntity(id: 'id')] Article $article,
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $donnees = $this->lireJson($request);

        if (\array_key_exists('estPersonnel', $donnees)) {
            $this->denyAccessUnlessGranted(ArticleVoter::CHANGER_CONFIDENTIALITE, $article);
            if (!\is_bool($donnees['estPersonnel'])) {
                return $this->reponseInvalide(['estPersonnel' => 'Cette valeur doit être vraie ou fausse.']);
            }
            $article->setEstPersonnel($donnees['estPersonnel']);
        }

        $messages = $this->messagesParChamp($validator->validate($article));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $em->flush();

        return $this->json($this->presenter($article));
    }

    /**
     * @return array<string, mixed>
     */
    private function presenter(Article $article): array
    {
        return $this->presentateur->articleDetail(
            $article,
            $this->isGranted(ArticleVoter::MODIFIER, $article),
            $this->isGranted(ArticleVoter::CHANGER_CONFIDENTIALITE, $article),
        );
    }
}
