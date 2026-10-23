<?php

namespace App\Repository;

use App\Entity\Categorie;
use App\Enum\FamilleCategorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    /**
     * Catégories sans enfant (celles qu'on attribue à un article), avec leurs parents chargés.
     *
     * @param list<FamilleCategorie> $familles toutes les familles si vide
     *
     * @return list<Categorie>
     */
    public function findFeuilles(array $familles = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('p', 'r')
            ->leftJoin('c.parent', 'p')
            ->leftJoin('p.parent', 'r')
            ->leftJoin('c.enfants', 'e')
            ->andWhere('e.id IS NULL')
            ->orderBy('c.id', 'ASC');

        if ([] !== $familles) {
            $qb->andWhere('c.famille IN (:familles)')->setParameter('familles', $familles);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<string> $chemin racine › sous-catégorie › catégorie
     */
    public function findParChemin(array $chemin): ?Categorie
    {
        $categorie = null;
        foreach ($chemin as $nom) {
            $categorie = $this->findOneBy(['nom' => $nom, 'parent' => $categorie]);
            if (null === $categorie) {
                return null;
            }
        }

        return $categorie;
    }
}
