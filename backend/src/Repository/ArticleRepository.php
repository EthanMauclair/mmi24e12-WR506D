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

    /**
     * Vêtements et accessoires que le générateur peut proposer : ceux du membre dans ce logement, disponibles,
     * non personnels, et dont la catégorie a une zone de tenue (haut, bas, chaussures…).
     *
     * @return list<Article>
     */
    public function findPourTenue(Logement $logement, Utilisateur $membre): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.categorie', 'c')->addSelect('c')
            ->leftJoin('a.etiquettes', 'e')->addSelect('e')
            ->andWhere('a.logement = :logement')
            ->andWhere('a.proprietaire = :membre')
            ->andWhere('a.estPersonnel = false')
            ->andWhere('a.statut = :disponible')
            ->andWhere('c.zoneTenue IS NOT NULL')
            ->setParameter('logement', $logement)
            ->setParameter('membre', $membre)
            ->setParameter('disponible', StatutArticle::DISPONIBLE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Articles montrés sur le profil d'un membre : ceux qu'il a ajoutés, hors articles personnels et vendus.
     */
    private function duProfil(Utilisateur $membre): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.proprietaire = :membre')
            ->andWhere('a.estPersonnel = false')
            ->andWhere('a.statut != :vendu')
            ->setParameter('membre', $membre)
            ->setParameter('vendu', StatutArticle::VENDU);
    }

    /**
     * @return list<Article>
     */
    public function findDuProfil(Utilisateur $membre): array
    {
        return $this->duProfil($membre)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function compterDuProfil(Utilisateur $membre): int
    {
        return (int) $this->duProfil($membre)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
