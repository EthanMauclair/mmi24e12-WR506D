<?php

namespace App\Repository;

use App\Entity\Pret;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pret>
 */
class PretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pret::class);
    }

    /**
     * Indique si l'article du prêt a déjà un autre prêt en cours.
     */
    public function existeAutrePretEnCours(Pret $pret): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.article = :article')
            ->andWhere('p.dateRetourReelle IS NULL')
            ->setParameter('article', $pret->getArticle());

        if (null !== $pret->getId()) {
            $qb->andWhere('p.id != :id')->setParameter('id', $pret->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
