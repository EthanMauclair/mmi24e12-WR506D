<?php

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Entity\Annonce;
use App\Entity\Commentaire;
use App\Entity\Tenue;
use App\Enum\StatutAnnonce;
use App\Enum\StatutArticle;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modération par l'administrateur des contenus visibles par les autres membres :
 * commentaires, annonces de la place de marché et tenues publiques.
 */
#[Route('/api/admin/moderation')]
final class ModerationController extends ApiController
{
    /** Nombre de contenus récents listés par type */
    private const LIMITE = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connexion,
    ) {
    }

    #[Route('', name: 'api_admin_moderation', methods: ['GET'])]
    public function lister(): JsonResponse
    {
        $limite = self::LIMITE;

        $commentaires = $this->connexion->fetchAllAssociative(
            "SELECT c.id, c.contenu, c.created_at createdAt, u.pseudo auteur, c.article_id articleId, a.titre articleTitre,
                c.tenue_id tenueId, t.titre tenueTitre
             FROM commentaire c JOIN utilisateur u ON u.id = c.auteur_id
             LEFT JOIN article a ON a.id = c.article_id LEFT JOIN tenue t ON t.id = c.tenue_id
             ORDER BY c.created_at DESC, c.id DESC LIMIT $limite",
        );

        $annonces = $this->connexion->fetchAllAssociative(
            "SELECT an.id, an.prix, an.description, an.statut, an.created_at createdAt, a.id articleId, a.titre, u.pseudo vendeur,
                (SELECT p.chemin FROM photo_article p WHERE p.article_id = a.id ORDER BY p.position, p.id LIMIT 1) photo
             FROM annonce an JOIN article a ON a.id = an.article_id JOIN utilisateur u ON u.id = a.proprietaire_id
             WHERE an.statut IN ('EN_LIGNE', 'RESERVEE')
             ORDER BY an.created_at DESC, an.id DESC LIMIT $limite",
        );

        $tenues = $this->connexion->fetchAllAssociative(
            "SELECT t.id, t.titre, t.description, t.created_at createdAt, u.pseudo createur,
                (SELECT COUNT(*) FROM tenue_article ta WHERE ta.tenue_id = t.id) nbArticles,
                (SELECT COUNT(*) FROM like_tenue l WHERE l.tenue_id = t.id) nbLikes
             FROM tenue t JOIN utilisateur u ON u.id = t.createur_id
             WHERE t.est_privee = 0
             ORDER BY t.created_at DESC, t.id DESC LIMIT $limite",
        );

        return $this->json([
            'commentaires' => array_map(fn (array $c) => [
                'id' => (int) $c['id'],
                'contenu' => $c['contenu'],
                'createdAt' => $this->date($c['createdAt']),
                'auteur' => $c['auteur'],
                'cible' => null !== $c['articleId']
                    ? ['type' => 'ARTICLE', 'id' => (int) $c['articleId'], 'titre' => $c['articleTitre']]
                    : ['type' => 'TENUE', 'id' => (int) $c['tenueId'], 'titre' => $c['tenueTitre']],
            ], $commentaires),
            'annonces' => array_map(fn (array $a) => [
                'id' => (int) $a['id'],
                'titre' => $a['titre'],
                'prix' => (float) $a['prix'],
                'description' => $a['description'],
                'statut' => $a['statut'],
                'createdAt' => $this->date($a['createdAt']),
                'vendeur' => $a['vendeur'],
                'photo' => $a['photo'],
            ], $annonces),
            'tenues' => array_map(fn (array $t) => [
                'id' => (int) $t['id'],
                'titre' => $t['titre'],
                'description' => $t['description'],
                'createdAt' => $this->date($t['createdAt']),
                'createur' => $t['createur'],
                'nbArticles' => (int) $t['nbArticles'],
                'nbLikes' => (int) $t['nbLikes'],
            ], $tenues),
        ]);
    }

    #[Route('/commentaires/{id}', name: 'api_admin_commentaire_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimerCommentaire(#[MapEntity(id: 'id')] Commentaire $commentaire): JsonResponse
    {
        $this->em->remove($commentaire);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Retire une annonce de la place de marché : l'article redevient disponible chez son propriétaire.
     */
    #[Route('/annonces/{id}', name: 'api_admin_annonce_retirer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function retirerAnnonce(#[MapEntity(id: 'id')] Annonce $annonce): JsonResponse
    {
        if (!\in_array($annonce->getStatut(), [StatutAnnonce::EN_LIGNE, StatutAnnonce::RESERVEE], true)) {
            return $this->reponseInvalide(['general' => 'Cette annonce n\'est plus en ligne.']);
        }

        $annonce->setStatut(StatutAnnonce::RETIREE);
        $annonce->getArticle()->setStatut(StatutArticle::DISPONIBLE);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/tenues/{id}', name: 'api_admin_tenue_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimerTenue(#[MapEntity(id: 'id')] Tenue $tenue): JsonResponse
    {
        $this->em->remove($tenue);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function date(string $date): string
    {
        return (new \DateTimeImmutable($date))->format(\DATE_ATOM);
    }
}
