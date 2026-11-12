<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Article;
use App\Entity\Commentaire;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Repository\CommentaireRepository;
use App\Security\Voter\ArticleVoter;
use App\Security\Voter\TenueVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Commentaires des articles et des tenues : lus par tous ceux qui les voient, écrits par leur auteur
 * (celui qui a ajouté l'article, ou créé la tenue) et ses amis.
 */
#[Route('/api')]
final class CommentaireController extends ApiController
{
    public function __construct(private readonly Presentateur $presentateur)
    {
    }

    #[Route('/articles/{id}/commentaires', name: 'api_article_commentaires', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ArticleVoter::VOIR, 'article')]
    public function lister(
        #[MapEntity(id: 'id')] Article $article,
        #[CurrentUser] ?Utilisateur $utilisateur,
        CommentaireRepository $commentaires,
    ): JsonResponse {
        return $this->json(array_map(
            fn (Commentaire $commentaire) => $this->presenter($commentaire, $utilisateur),
            $commentaires->findBy(['article' => $article], ['createdAt' => 'ASC', 'id' => 'ASC']),
        ));
    }

    #[Route('/articles/{id}/commentaires', name: 'api_article_commenter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ArticleVoter::COMMENTER, 'article', message: 'Seuls les amis de ce membre peuvent commenter ses articles.')]
    public function commenter(
        #[MapEntity(id: 'id')] Article $article,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $contenu = $this->lireJson($request)['contenu'] ?? null;
        $commentaire = (new Commentaire())
            ->setAuteur($utilisateur)
            ->setArticle($article)
            ->setContenu(\is_string($contenu) ? trim($contenu) : '');

        $messages = $this->messagesParChamp($validator->validate($commentaire));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $em->persist($commentaire);
        $em->flush();

        return $this->json($this->presenter($commentaire, $utilisateur), Response::HTTP_CREATED);
    }

    #[Route('/tenues/{id}/commentaires', name: 'api_tenue_commentaires', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TenueVoter::VOIR, 'tenue')]
    public function listerTenue(
        #[MapEntity(id: 'id')] Tenue $tenue,
        #[CurrentUser] ?Utilisateur $utilisateur,
        CommentaireRepository $commentaires,
    ): JsonResponse {
        return $this->json(array_map(
            fn (Commentaire $commentaire) => $this->presenter($commentaire, $utilisateur),
            $commentaires->findBy(['tenue' => $tenue], ['createdAt' => 'ASC', 'id' => 'ASC']),
        ));
    }

    #[Route('/tenues/{id}/commentaires', name: 'api_tenue_commenter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TenueVoter::COMMENTER, 'tenue', message: 'Seuls les amis de ce membre peuvent commenter ses tenues.')]
    public function commenterTenue(
        #[MapEntity(id: 'id')] Tenue $tenue,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $contenu = $this->lireJson($request)['contenu'] ?? null;
        $commentaire = (new Commentaire())
            ->setAuteur($utilisateur)
            ->setTenue($tenue)
            ->setContenu(\is_string($contenu) ? trim($contenu) : '');

        $messages = $this->messagesParChamp($validator->validate($commentaire));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $em->persist($commentaire);
        $em->flush();

        return $this->json($this->presenter($commentaire, $utilisateur), Response::HTTP_CREATED);
    }

    /**
     * Supprime un commentaire : son auteur, ou celui qui a ajouté l'article (ou créé la tenue) commenté.
     */
    #[Route('/commentaires/{id}', name: 'api_commentaire_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(
        #[MapEntity(id: 'id')] Commentaire $commentaire,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$this->peutSupprimer($commentaire, $utilisateur)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce commentaire.');
        }

        $em->remove($commentaire);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenter(Commentaire $commentaire, ?Utilisateur $utilisateur): array
    {
        return $this->presentateur->commentaire($commentaire, $this->peutSupprimer($commentaire, $utilisateur));
    }

    private function peutSupprimer(Commentaire $commentaire, ?Utilisateur $utilisateur): bool
    {
        return null !== $utilisateur
            && ($commentaire->getAuteur() === $utilisateur
                || $commentaire->getArticle()?->getProprietaire() === $utilisateur
                || $commentaire->getTenue()?->getCreateur() === $utilisateur);
    }
}
