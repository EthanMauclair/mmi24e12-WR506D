<?php

namespace App\Repository;

use App\Entity\Annonce;
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
}
