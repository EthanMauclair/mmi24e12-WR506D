<?php

namespace App\Repository;

use App\Entity\Abonnement;
use App\Entity\Utilisateur;
use App\Enum\StatutAbonnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Abonnement>
 */
class AbonnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abonnement::class);
    }

    public function entre(Utilisateur $abonne, Utilisateur $suivi): ?Abonnement
    {
        return $this->findOneBy(['abonne' => $abonne, 'suivi' => $suivi]);
    }

    /**
     * Abonnés acceptés du membre, ses amis d'abord.
     *
     * @return list<Abonnement>
     */
    public function findAbonnes(Utilisateur $suivi): array
    {
        return $this->createQueryBuilder('ab')
            ->join('ab.abonne', 'u')->addSelect('u')
            ->andWhere('ab.suivi = :suivi')
            ->andWhere('ab.statut = :accepte')
            ->setParameter('suivi', $suivi)
            ->setParameter('accepte', StatutAbonnement::ACCEPTE)
            ->orderBy('ab.estAmi', 'DESC')
            ->addOrderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Membres que l'utilisateur suit (abonnements acceptés).
     *
     * @return list<Abonnement>
     */
    public function findAbonnements(Utilisateur $abonne): array
    {
        return $this->createQueryBuilder('ab')
            ->join('ab.suivi', 'u')->addSelect('u')
            ->andWhere('ab.abonne = :abonne')
            ->andWhere('ab.statut = :accepte')
            ->setParameter('abonne', $abonne)
            ->setParameter('accepte', StatutAbonnement::ACCEPTE)
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes d'abonnement reçues et pas encore acceptées, les plus récentes d'abord.
     *
     * @return list<Abonnement>
     */
    public function findDemandesRecues(Utilisateur $suivi): array
    {
        return $this->createQueryBuilder('ab')
            ->join('ab.abonne', 'u')->addSelect('u')
            ->andWhere('ab.suivi = :suivi')
            ->andWhere('ab.statut = :attente')
            ->setParameter('suivi', $suivi)
            ->setParameter('attente', StatutAbonnement::EN_ATTENTE)
            ->orderBy('ab.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{abonnes: int, abonnements: int, amis: int, demandes: int}
     */
    public function compter(Utilisateur $membre): array
    {
        $ligne = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT
                SUM(suivi_id = :id AND statut = :accepte) AS abonnes,
                SUM(abonne_id = :id AND statut = :accepte) AS abonnements,
                SUM(suivi_id = :id AND statut = :accepte AND est_ami = 1) AS amis,
                SUM(suivi_id = :id AND statut = :attente) AS demandes
             FROM abonnement WHERE suivi_id = :id OR abonne_id = :id',
            ['id' => $membre->getId(), 'accepte' => StatutAbonnement::ACCEPTE->value, 'attente' => StatutAbonnement::EN_ATTENTE->value],
        );

        return array_map('intval', $ligne ?: []) + ['abonnes' => 0, 'abonnements' => 0, 'amis' => 0, 'demandes' => 0];
    }

    /**
     * Passe un profil devenu public : toutes les demandes en attente sont acceptées.
     */
    public function accepterDemandes(Utilisateur $suivi): void
    {
        foreach ($this->findDemandesRecues($suivi) as $demande) {
            $demande->accepter();
        }
    }
}
