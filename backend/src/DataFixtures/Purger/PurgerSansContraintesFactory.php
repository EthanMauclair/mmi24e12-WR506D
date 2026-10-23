<?php

namespace App\DataFixtures\Purger;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * La table categorie se référence elle-même (parent en ON DELETE RESTRICT) : MariaDB refuse de la
 * vider d'un seul DELETE. Les clés étrangères sont donc désactivées le temps de la purge des fixtures.
 *
 * @implements PurgerFactory<ORMPurgerInterface>
 */
#[AsDecorator('doctrine.fixtures.purger.orm_purger_factory')]
final class PurgerSansContraintesFactory implements PurgerFactory
{
    /**
     * @param PurgerFactory<ORMPurgerInterface> $factory
     */
    public function __construct(#[AutowireDecorated] private readonly PurgerFactory $factory)
    {
    }

    public function createForEntityManager(
        ?string $emName,
        EntityManagerInterface $em,
        array $excluded = [],
        bool $purgeWithTruncate = false,
    ): PurgerInterface {
        $purger = $this->factory->createForEntityManager($emName, $em, $excluded, $purgeWithTruncate);

        return new class($purger, $em) implements ORMPurgerInterface {
            public function __construct(private ORMPurgerInterface $purger, private EntityManagerInterface $em)
            {
            }

            public function setEntityManager(EntityManagerInterface $em): void
            {
                $this->em = $em;
                $this->purger->setEntityManager($em);
            }

            public function purge(): void
            {
                $connexion = $this->em->getConnection();
                $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
                try {
                    $this->purger->purge();
                } finally {
                    $connexion->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
                }
            }
        };
    }
}
