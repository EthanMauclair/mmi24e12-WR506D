<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Article;
use App\Security\Voter\ArticleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/articles/{id}', requirements: ['id' => '\d+'])]
final class ArticleController extends ApiController
{
    public function __construct(private readonly Presentateur $presentateur)
    {
    }

    #[Route('', name: 'api_article', methods: ['GET'])]
    #[IsGranted(ArticleVoter::VOIR, 'article')]
    public function detail(#[MapEntity(id: 'id')] Article $article): JsonResponse
    {
        return $this->json($this->presenter($article));
    }

    /**
     * Seul le champ estPersonnel est modifiable pour l'instant.
     */
    #[Route('', name: 'api_article_modifier', methods: ['PATCH'])]
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
