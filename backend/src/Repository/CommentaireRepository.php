<?php

namespace App\Repository;

use App\Entity\Commentaire;
use App\Entity\Tenue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commentaire>
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    /**
     * Nombre de commentaires de chaque tenue et le plus récent (aperçu sous la tenue dans le fil).
     *
     * @param list<Tenue> $tenues
     *
     * @return array<int, array{nombre: int, dernier: Commentaire}> par identifiant de tenue
     */
    public function resumerTenues(array $tenues): array
    {
        if ([] === $tenues) {
            return [];
        }

        $commentaires = $this->createQueryBuilder('c')
            ->join('c.auteur', 'a')->addSelect('a')
            ->andWhere('c.tenue IN (:tenues)')
            ->setParameter('tenues', $tenues)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        $resume = [];
        foreach ($commentaires as $commentaire) {
            $id = $commentaire->getTenue()->getId();
            $resume[$id] = ['nombre' => ($resume[$id]['nombre'] ?? 0) + 1, 'dernier' => $commentaire];
        }

        return $resume;
    }
}
