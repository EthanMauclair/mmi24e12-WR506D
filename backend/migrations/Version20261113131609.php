<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113131609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Back office : blocage d\'un membre et date de sa dernière connexion';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur ADD est_bloque TINYINT DEFAULT 0 NOT NULL, ADD derniere_connexion_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP est_bloque, DROP derniere_connexion_at');
    }
}
