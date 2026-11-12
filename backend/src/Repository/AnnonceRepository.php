<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\Article;
use App\Entity\DetailVetement;
use App\Entity\Utilisateur;
use App\Enum\FamilleCategorie;
use App\Enum\StatutAnnonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annonce>
 */
class AnnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annonce::class);
    }

    /**
     * Indique si l'article de l'annonce a déjà une autre annonce en ligne ou réservée.
     */
    public function existeAutreAnnonceActive(Annonce $annonce): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.article = :article')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('article', $annonce->getArticle())
            ->setParameter('statuts', [StatutAnnonce::EN_LIGNE, StatutAnnonce::RESERVEE]);

        if (null !== $annonce->getId()) {
            $qb->andWhere('a.id != :id')->setParameter('id', $annonce->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findActive(Article $article): ?Annonce
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.article = :article')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('article', $article)
            ->setParameter('statuts', [StatutAnnonce::EN_LIGNE, StatutAnnonce::RESERVEE])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Annonces du marché : en ligne et réservées, les plus récentes d'abord.
     *
     * @param list<FamilleCategorie> $familles vide : toutes
     * @param Utilisateur|null       $vendeur  seulement les annonces de ce membre (« Mes annonces »)
     *
     * @return list<Annonce>
     */
    public function rechercher(string $texte, array $familles, ?Utilisateur $vendeur, int $limite = 60): array
    {
        $requete = $this->createQueryBuilder('an')
            ->join('an.article', 'a')->addSelect('a')
            ->join('a.categorie', 'c')
            ->join('a.proprietaire', 'v')->addSelect('v')
            ->leftJoin(DetailVetement::class, 'dv', 'WITH', 'dv.article = a')
            ->leftJoin('dv.marque', 'm')
            ->andWhere('an.statut IN (:statuts)')
            ->setParameter('statuts', [StatutAnnonce::EN_LIGNE, StatutAnnonce::RESERVEE])
            ->orderBy('an.createdAt', 'DESC')
            ->addOrderBy('an.id', 'DESC')
            ->setMaxResults($limite);

        if ('' !== $texte) {
            $requete->andWhere('a.titre LIKE :texte OR c.nom LIKE :texte OR m.nom LIKE :texte OR v.pseudo LIKE :texte')
                ->setParameter('texte', '%'.addcslashes($texte, '%_').'%');
        }
        if ([] !== $familles) {
            $requete->andWhere('c.famille IN (:familles)')->setParameter('familles', $familles);
        }
        if (null !== $vendeur) {
            $requete->andWhere('a.proprietaire = :vendeur')->setParameter('vendeur', $vendeur);
        }

        return $requete->getQuery()->getResult();
    }
}
