<?php

namespace App\Repository;

use App\Entity\LikeTenue;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LikeTenue>
 */
class LikeTenueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LikeTenue::class);
    }

    /**
     * Nombre de likes de chaque tenue, et celles que l'utilisateur aime, en une requête.
     *
     * @param list<Tenue> $tenues
     *
     * @return array<int, array{nombre: int, aime: bool}> par identifiant de tenue
     */
    public function resumer(array $tenues, ?Utilisateur $utilisateur): array
    {
        if ([] === $tenues) {
            return [];
        }

        $lignes = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.tenue) AS tenue', 'COUNT(l.id) AS nombre', 'SUM(CASE WHEN l.utilisateur = :utilisateur THEN 1 ELSE 0 END) AS aime')
            ->andWhere('l.tenue IN (:tenues)')
            ->setParameter('tenues', $tenues)
            ->setParameter('utilisateur', $utilisateur?->getId() ?? 0)
            ->groupBy('l.tenue')
            ->getQuery()
            ->getArrayResult();

        $resume = [];
        foreach ($lignes as $ligne) {
            $resume[(int) $ligne['tenue']] = ['nombre' => (int) $ligne['nombre'], 'aime' => (int) $ligne['aime'] > 0];
        }

        return $resume;
    }
}
