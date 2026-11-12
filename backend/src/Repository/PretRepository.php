<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Pret;
use App\Entity\Utilisateur;
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

    public function findEnCours(Article $article): ?Pret
    {
        return $this->findOneBy(['article' => $article, 'dateRetourReelle' => null]);
    }

    /**
     * Prêts consentis par le membre : en cours (retour prévu le plus proche d'abord, sans date à la fin)
     * et rendus (les plus récents d'abord).
     *
     * @return array{enCours: list<Pret>, rendus: list<Pret>}
     */
    public function findDuPreteur(Utilisateur $preteur): array
    {
        $prets = $this->createQueryBuilder('p')
            ->join('p.article', 'a')->addSelect('a')
            ->leftJoin('p.emprunteur', 'e')->addSelect('e')
            ->andWhere('p.preteur = :preteur')
            ->setParameter('preteur', $preteur)
            ->addSelect('CASE WHEN p.dateRetourPrevue IS NULL THEN 1 ELSE 0 END AS HIDDEN sansDate')
            ->orderBy('sansDate', 'ASC')
            ->addOrderBy('p.dateRetourPrevue', 'ASC')
            ->addOrderBy('p.dateRetourReelle', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $enCours = array_values(array_filter($prets, static fn (Pret $pret) => $pret->estEnCours()));
        $rendus = array_values(array_filter($prets, static fn (Pret $pret) => !$pret->estEnCours()));
        usort($rendus, static fn (Pret $a, Pret $b) => $b->getDateRetourReelle() <=> $a->getDateRetourReelle() ?: $b->getId() <=> $a->getId());

        return ['enCours' => $enCours, 'rendus' => $rendus];
    }

    public function compterEnCours(Utilisateur $preteur): int
    {
        return $this->count(['preteur' => $preteur, 'dateRetourReelle' => null]);
    }
}
