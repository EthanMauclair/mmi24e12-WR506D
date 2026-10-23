<?php

namespace App\Repository;

use App\Entity\Logement;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Logement>
 */
class LogementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logement::class);
    }

    /**
     * Logements dont l'utilisateur est propriétaire ou membre.
     *
     * @return list<Logement>
     */
    public function findPourUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.membres', 'm')
            ->andWhere('l.proprietaire = :utilisateur OR m.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
