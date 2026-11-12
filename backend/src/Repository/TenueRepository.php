<?php

namespace App\Repository;

use App\Entity\Abonnement;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Enum\StatutAbonnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenue>
 */
class TenueRepository extends ServiceEntityRepository
{
    public const TAILLE_PAGE = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenue::class);
    }

    /**
     * Fil des tenues : les tenues publiques des membres suivis et les siennes, les plus récentes d'abord.
     *
     * @param int|null $avant identifiant de la dernière tenue déjà affichée (page suivante)
     *
     * @return list<Tenue>
     */
    public function findFil(Utilisateur $utilisateur, ?int $avant): array
    {
        $suivis = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(ab.suivi)')
            ->from(Abonnement::class, 'ab')
            ->andWhere('ab.abonne = :utilisateur')
            ->andWhere('ab.statut = :accepte');

        $requete = $this->createQueryBuilder('t')
            ->join('t.createur', 'u')->addSelect('u')
            ->andWhere('t.estPrivee = false')
            ->andWhere('t.createur = :utilisateur OR t.createur IN ('.$suivis->getDQL().')')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('accepte', StatutAbonnement::ACCEPTE)
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(self::TAILLE_PAGE);

        if (null !== $avant) {
            $requete->andWhere('t.id < :avant')->setParameter('avant', $avant);
        }

        return $requete->getQuery()->getResult();
    }
}
