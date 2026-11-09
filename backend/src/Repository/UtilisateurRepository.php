<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Ré-hache automatiquement le mot de passe quand l'algorithme évolue.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setMotDePasse($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche de membres par pseudo, prénom ou nom (les pseudos qui commencent par le texte d'abord).
     *
     * @return list<Utilisateur>
     */
    public function rechercher(string $texte, int $limite = 20): array
    {
        $echappe = addcslashes($texte, '%_');

        return $this->createQueryBuilder('u')
            ->andWhere('u.pseudo LIKE :texte OR u.prenom LIKE :texte OR u.nom LIKE :texte OR CONCAT(u.prenom, \' \', u.nom) LIKE :texte')
            ->setParameter('texte', '%'.$echappe.'%')
            ->addSelect('CASE WHEN u.pseudo LIKE :debut THEN 0 ELSE 1 END AS HIDDEN priorite')
            ->setParameter('debut', $echappe.'%')
            ->orderBy('priorite', 'ASC')
            ->addOrderBy('u.pseudo', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
