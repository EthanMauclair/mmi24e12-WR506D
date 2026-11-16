<?php

namespace App\DataFixtures;

use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Les deux comptes d'un site vierge : le super admin (propriétaire du site, accès complet au back office)
 * et un membre classique, sans logement ni article. Mot de passe MOT_DE_PASSE (développement uniquement).
 */
class ComptesFixtures extends Fixture implements FixtureGroupInterface
{
    public const MOT_DE_PASSE = 'penderie2026';

    public function __construct(private readonly UserPasswordHasherInterface $hacheur)
    {
    }

    public static function getGroups(): array
    {
        return ['base', 'demo'];
    }

    public function load(ObjectManager $manager): void
    {
        $this->compte($manager, 'admin', 'admin@penderie.fr', ['ROLE_SUPER_ADMIN']);
        $this->compte($manager, 'membre', 'membre@penderie.fr', []);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function compte(ObjectManager $manager, string $pseudo, string $email, array $roles): void
    {
        $utilisateur = (new Utilisateur())
            ->setPseudo($pseudo)
            ->setEmail($email)
            ->setDateNaissance(new \DateTimeImmutable('1990-01-01'))
            ->setRoles($roles);
        $utilisateur->setMotDePasse($this->hacheur->hashPassword($utilisateur, self::MOT_DE_PASSE));
        $manager->persist($utilisateur);
    }
}
