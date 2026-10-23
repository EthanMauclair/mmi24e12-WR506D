<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\DetailVetement;
use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\Utilisateur;
use App\Enum\StatutArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Articles du logement visibles par l'utilisateur : un article personnel n'est vu que par celui qui l'a ajouté.
     */
    private function visibles(Logement $logement, Utilisateur $utilisateur): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.logement = :logement')
            ->andWhere('a.estPersonnel = false OR a.proprietaire = :utilisateur')
            ->setParameter('logement', $logement)
            ->setParameter('utilisateur', $utilisateur);
    }

    /**
     * @return array{articles: int, pretes: int}
     */
    public function compterDansLogement(Logement $logement, Utilisateur $utilisateur): array
    {
        $resultat = $this->visibles($logement, $utilisateur)
            ->select('COUNT(a.id) AS articles')
            ->addSelect('SUM(CASE WHEN a.statut = :prete THEN 1 ELSE 0 END) AS pretes')
            ->setParameter('prete', StatutArticle::PRETE)
            ->getQuery()
            ->getSingleResult();

        return ['articles' => (int) $resultat['articles'], 'pretes' => (int) $resultat['pretes']];
    }

    /**
     * @return array<int, int> nombre d'articles visibles par identifiant de pièce
     */
    public function compterParPiece(Logement $logement, Utilisateur $utilisateur): array
    {
        $lignes = $this->visibles($logement, $utilisateur)
            ->select('IDENTITY(s.piece) AS piece, COUNT(a.id) AS nombre')
            ->join('a.stockage', 's')
            ->groupBy('s.piece')
            ->getQuery()
            ->getArrayResult();

        $nombres = [];
        foreach ($lignes as $ligne) {
            $nombres[(int) $ligne['piece']] = (int) $ligne['nombre'];
        }

        return $nombres;
    }

    /**
     * @return list<Article>
     */
    public function findVisiblesDansPiece(Piece $piece, Utilisateur $utilisateur): array
    {
        return $this->visibles($piece->getLogement(), $utilisateur)
            ->join('a.stockage', 's')
            ->andWhere('s.piece = :piece')
            ->setParameter('piece', $piece)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par titre, catégorie, marque ou couleur.
     *
     * @return list<Article>
     */
    public function rechercher(Logement $logement, Utilisateur $utilisateur, string $texte, int $limite = 20): array
    {
        return $this->visibles($logement, $utilisateur)
            ->join('a.categorie', 'c')
            ->leftJoin(DetailVetement::class, 'dv', 'WITH', 'dv.article = a')
            ->leftJoin('dv.marque', 'm')
            ->leftJoin('dv.couleurPrincipale', 'co')
            ->andWhere('a.titre LIKE :texte OR c.nom LIKE :texte OR m.nom LIKE :texte OR co.nom LIKE :texte')
            ->setParameter('texte', '%'.addcslashes($texte, '%_').'%')
            ->orderBy('a.titre', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
