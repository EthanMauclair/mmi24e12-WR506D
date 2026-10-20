<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020143121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le modèle de données (logement, articles, tenues, réseau social, prêts, ventes) et ses contraintes CHECK';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE abonnement (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(255) NOT NULL, est_ami TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, accepte_at DATETIME DEFAULT NULL, abonne_id INT NOT NULL, suivi_id INT NOT NULL, UNIQUE INDEX uniq_abonnement (abonne_id, suivi_id), INDEX IDX_351268BBC325A696 (abonne_id), INDEX IDX_351268BB7FEA59C0 (suivi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE annonce (id INT AUTO_INCREMENT NOT NULL, prix NUMERIC(8, 2) NOT NULL, description LONGTEXT DEFAULT NULL, statut VARCHAR(255) DEFAULT \'EN_LIGNE\' NOT NULL, created_at DATETIME NOT NULL, vendue_at DATETIME DEFAULT NULL, article_id INT NOT NULL, acheteur_id INT DEFAULT NULL, INDEX IDX_F65593E57294869C (article_id), INDEX IDX_F65593E596A7BB5F (acheteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, etat VARCHAR(255) DEFAULT NULL, est_personnel TINYINT DEFAULT 0 NOT NULL, statut VARCHAR(255) DEFAULT \'DISPONIBLE\' NOT NULL, mode_ajout VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, logement_id INT NOT NULL, proprietaire_id INT NOT NULL, stockage_id INT DEFAULT NULL, categorie_id INT NOT NULL, INDEX IDX_23A0E6658ABF955 (logement_id), INDEX IDX_23A0E6676C50E4A (proprietaire_id), INDEX IDX_23A0E66DAA83D7F (stockage_id), INDEX IDX_23A0E66BCF5E72D (categorie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_etiquette (article_id INT NOT NULL, etiquette_id INT NOT NULL, INDEX IDX_C43B497E7294869C (article_id), INDEX IDX_C43B497E7BD2EA57 (etiquette_id), PRIMARY KEY (article_id, etiquette_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(80) NOT NULL, famille VARCHAR(255) NOT NULL, zone_tenue VARCHAR(255) DEFAULT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX uniq_categorie_parent_nom (parent_id, nom), INDEX IDX_497DD634727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, contenu VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL, auteur_id INT NOT NULL, article_id INT DEFAULT NULL, tenue_id INT DEFAULT NULL, INDEX IDX_67F068BC60BB6FE6 (auteur_id), INDEX IDX_67F068BC7294869C (article_id), INDEX IDX_67F068BC28239C7 (tenue_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, annonce_id INT NOT NULL, acheteur_id INT NOT NULL, UNIQUE INDEX uniq_conversation (annonce_id, acheteur_id), INDEX IDX_8A8E26E98805AB2F (annonce_id), INDEX IDX_8A8E26E996A7BB5F (acheteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE couleur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(30) NOT NULL, code_hex VARCHAR(7) NOT NULL, UNIQUE INDEX uniq_couleur_nom (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detail_livre (isbn VARCHAR(13) DEFAULT NULL, auteur VARCHAR(150) DEFAULT NULL, editeur VARCHAR(100) DEFAULT NULL, nb_pages SMALLINT DEFAULT NULL, annee_parution SMALLINT DEFAULT NULL, serie VARCHAR(150) DEFAULT NULL, tome SMALLINT DEFAULT NULL, article_id INT NOT NULL, PRIMARY KEY (article_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detail_vetement (taille VARCHAR(20) DEFAULT NULL, matiere VARCHAR(50) DEFAULT NULL, saison VARCHAR(255) DEFAULT \'TOUTES\' NOT NULL, a_capuche TINYINT DEFAULT 0 NOT NULL, est_impermeable TINYINT DEFAULT 0 NOT NULL, niveau_chaleur SMALLINT DEFAULT 3 NOT NULL, article_id INT NOT NULL, marque_id INT DEFAULT NULL, couleur_principale_id INT DEFAULT NULL, couleur_secondaire_id INT DEFAULT NULL, INDEX IDX_CA6AAF144827B9B2 (marque_id), INDEX IDX_CA6AAF14906D879B (couleur_principale_id), INDEX IDX_CA6AAF14C02A12EE (couleur_secondaire_id), PRIMARY KEY (article_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detail_vinyle (ean VARCHAR(13) DEFAULT NULL, artiste VARCHAR(150) DEFAULT NULL, label VARCHAR(100) DEFAULT NULL, annee_sortie SMALLINT DEFAULT NULL, format VARCHAR(255) DEFAULT NULL, nb_disques SMALLINT DEFAULT 1 NOT NULL, article_id INT NOT NULL, PRIMARY KEY (article_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE etiquette (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(30) NOT NULL, couleur VARCHAR(7) DEFAULT NULL, logement_id INT NOT NULL, createur_id INT DEFAULT NULL, UNIQUE INDEX uniq_etiquette_logement_nom (logement_id, nom), INDEX IDX_1E0E195A58ABF955 (logement_id), INDEX IDX_1E0E195A73A201E5 (createur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE like_tenue (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, utilisateur_id INT NOT NULL, tenue_id INT NOT NULL, UNIQUE INDEX uniq_like_tenue (utilisateur_id, tenue_id), INDEX IDX_5C35618AFB88E14F (utilisateur_id), INDEX IDX_5C35618A28239C7 (tenue_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE logement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, proprietaire_id INT NOT NULL, INDEX IDX_F0FD445776C50E4A (proprietaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marque (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(80) NOT NULL, UNIQUE INDEX uniq_marque_nom (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE membre_logement (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, logement_id INT NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX uniq_membre_logement (logement_id, utilisateur_id), INDEX IDX_459FAC6458ABF955 (logement_id), INDEX IDX_459FAC64FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, envoye_at DATETIME NOT NULL, lu_at DATETIME DEFAULT NULL, conversation_id INT NOT NULL, expediteur_id INT NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307F10335F61 (expediteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE photo_article (id INT AUTO_INCREMENT NOT NULL, chemin VARCHAR(255) NOT NULL, est_image_scan TINYINT DEFAULT 0 NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_37DA19EB7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE piece (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, logement_id INT NOT NULL, UNIQUE INDEX uniq_piece_logement_nom (logement_id, nom), INDEX IDX_44CA0B2358ABF955 (logement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pret (id INT AUTO_INCREMENT NOT NULL, emprunteur_nom VARCHAR(100) NOT NULL, date_pret DATE NOT NULL, date_retour_prevue DATE DEFAULT NULL, date_retour_reelle DATE DEFAULT NULL, note VARCHAR(500) DEFAULT NULL, article_id INT NOT NULL, preteur_id INT NOT NULL, emprunteur_id INT DEFAULT NULL, INDEX IDX_52ECE9797294869C (article_id), INDEX IDX_52ECE9797A87D939 (preteur_id), INDEX IDX_52ECE979F0840037 (emprunteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stockage (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, type VARCHAR(255) NOT NULL, piece_id INT NOT NULL, UNIQUE INDEX uniq_stockage_piece_nom (piece_id, nom), INDEX IDX_CABCB492C40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tenue (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) NOT NULL, description VARCHAR(500) DEFAULT NULL, est_privee TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, createur_id INT NOT NULL, INDEX IDX_EB51486D73A201E5 (createur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tenue_article (tenue_id INT NOT NULL, article_id INT NOT NULL, INDEX IDX_D2AE4CF328239C7 (tenue_id), INDEX IDX_D2AE4CF37294869C (article_id), PRIMARY KEY (tenue_id, article_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, pseudo VARCHAR(30) NOT NULL, prenom VARCHAR(50) DEFAULT NULL, nom VARCHAR(50) DEFAULT NULL, date_naissance DATE NOT NULL, avatar VARCHAR(255) DEFAULT NULL, bio VARCHAR(500) DEFAULT NULL, est_prive TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, tuteur_id INT DEFAULT NULL, UNIQUE INDEX uniq_utilisateur_email (email), UNIQUE INDEX uniq_utilisateur_pseudo (pseudo), INDEX IDX_1D1C63B386EC68D8 (tuteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_351268BBC325A696 FOREIGN KEY (abonne_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT FK_351268BB7FEA59C0 FOREIGN KEY (suivi_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E57294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E596A7BB5F FOREIGN KEY (acheteur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6658ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6676C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66DAA83D7F FOREIGN KEY (stockage_id) REFERENCES stockage (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE article_etiquette ADD CONSTRAINT FK_C43B497E7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_etiquette ADD CONSTRAINT FK_C43B497E7BD2EA57 FOREIGN KEY (etiquette_id) REFERENCES etiquette (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE categorie ADD CONSTRAINT FK_497DD634727ACA70 FOREIGN KEY (parent_id) REFERENCES categorie (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC28239C7 FOREIGN KEY (tenue_id) REFERENCES tenue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E98805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E996A7BB5F FOREIGN KEY (acheteur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detail_livre ADD CONSTRAINT FK_D19384C07294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT FK_CA6AAF147294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT FK_CA6AAF144827B9B2 FOREIGN KEY (marque_id) REFERENCES marque (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT FK_CA6AAF14906D879B FOREIGN KEY (couleur_principale_id) REFERENCES couleur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT FK_CA6AAF14C02A12EE FOREIGN KEY (couleur_secondaire_id) REFERENCES couleur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE detail_vinyle ADD CONSTRAINT FK_9E18214B7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etiquette ADD CONSTRAINT FK_1E0E195A58ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etiquette ADD CONSTRAINT FK_1E0E195A73A201E5 FOREIGN KEY (createur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE like_tenue ADD CONSTRAINT FK_5C35618AFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE like_tenue ADD CONSTRAINT FK_5C35618A28239C7 FOREIGN KEY (tenue_id) REFERENCES tenue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement ADD CONSTRAINT FK_F0FD445776C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre_logement ADD CONSTRAINT FK_459FAC6458ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre_logement ADD CONSTRAINT FK_459FAC64FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F10335F61 FOREIGN KEY (expediteur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE photo_article ADD CONSTRAINT FK_37DA19EB7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B2358ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9797294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9797A87D939 FOREIGN KEY (preteur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE979F0840037 FOREIGN KEY (emprunteur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stockage ADD CONSTRAINT FK_CABCB492C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenue ADD CONSTRAINT FK_EB51486D73A201E5 FOREIGN KEY (createur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenue_article ADD CONSTRAINT FK_D2AE4CF328239C7 FOREIGN KEY (tenue_id) REFERENCES tenue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenue_article ADD CONSTRAINT FK_D2AE4CF37294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B386EC68D8 FOREIGN KEY (tuteur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');

        // Contraintes CHECK : valeurs autorisées des colonnes enum
        $this->addSql('ALTER TABLE membre_logement ADD CONSTRAINT chk_membre_logement_role CHECK (role IN (\'COPROPRIETAIRE\',\'MINEUR\'))');
        $this->addSql('ALTER TABLE stockage ADD CONSTRAINT chk_stockage_type CHECK (type IN (\'ARMOIRE\',\'PENDERIE\',\'COMMODE\',\'ETAGERE\',\'PLACARD\',\'BIBLIOTHEQUE\',\'BOITE\',\'AUTRE\'))');
        $this->addSql('ALTER TABLE categorie ADD CONSTRAINT chk_categorie_famille CHECK (famille IN (\'VETEMENT\',\'ACCESSOIRE\',\'LIVRE\',\'VINYLE\',\'AUTRE\'))');
        $this->addSql('ALTER TABLE categorie ADD CONSTRAINT chk_categorie_zone_tenue CHECK (zone_tenue IN (\'HAUT\',\'BAS\',\'UNE_PIECE\',\'COUCHE_EXTERIEURE\',\'CHAUSSURES\',\'ACCESSOIRE\'))');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT chk_article_etat CHECK (etat IN (\'NEUF_AVEC_ETIQUETTE\',\'NEUF_SANS_ETIQUETTE\',\'TRES_BON_ETAT\',\'BON_ETAT\',\'SATISFAISANT\'))');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT chk_article_statut CHECK (statut IN (\'DISPONIBLE\',\'PRETE\',\'EN_VENTE\',\'VENDU\'))');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT chk_article_mode_ajout CHECK (mode_ajout IN (\'SCAN_IMAGE\',\'SCAN_CODE_BARRE\',\'MANUEL\'))');
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT chk_detail_vetement_saison CHECK (saison IN (\'TOUTES\',\'PRINTEMPS_ETE\',\'AUTOMNE_HIVER\'))');
        $this->addSql('ALTER TABLE detail_vinyle ADD CONSTRAINT chk_detail_vinyle_format CHECK (format IN (\'LP_33T\',\'SINGLE_45T\',\'MAXI_45T\',\'EP\',\'AUTRE\'))');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT chk_abonnement_statut CHECK (statut IN (\'EN_ATTENTE\',\'ACCEPTE\'))');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT chk_annonce_statut CHECK (statut IN (\'EN_LIGNE\',\'RESERVEE\',\'VENDUE\',\'RETIREE\'))');

        // Contraintes CHECK : règles métier
        $this->addSql('ALTER TABLE detail_vetement ADD CONSTRAINT chk_detail_vetement_niveau_chaleur CHECK (niveau_chaleur BETWEEN 1 AND 5)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT chk_annonce_prix CHECK (prix > 0)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT chk_pret_date_retour_prevue CHECK (date_retour_prevue IS NULL OR date_retour_prevue >= date_pret)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT chk_pret_date_retour_reelle CHECK (date_retour_reelle IS NULL OR date_retour_reelle >= date_pret)');
        $this->addSql('ALTER TABLE abonnement ADD CONSTRAINT chk_abonnement_est_ami CHECK (est_ami = 0 OR statut = \'ACCEPTE\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BBC325A696');
        $this->addSql('ALTER TABLE abonnement DROP FOREIGN KEY FK_351268BB7FEA59C0');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E57294869C');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E596A7BB5F');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6658ABF955');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6676C50E4A');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66DAA83D7F');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66BCF5E72D');
        $this->addSql('ALTER TABLE article_etiquette DROP FOREIGN KEY FK_C43B497E7294869C');
        $this->addSql('ALTER TABLE article_etiquette DROP FOREIGN KEY FK_C43B497E7BD2EA57');
        $this->addSql('ALTER TABLE categorie DROP FOREIGN KEY FK_497DD634727ACA70');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC60BB6FE6');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC7294869C');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC28239C7');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E98805AB2F');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E996A7BB5F');
        $this->addSql('ALTER TABLE detail_livre DROP FOREIGN KEY FK_D19384C07294869C');
        $this->addSql('ALTER TABLE detail_vetement DROP FOREIGN KEY FK_CA6AAF147294869C');
        $this->addSql('ALTER TABLE detail_vetement DROP FOREIGN KEY FK_CA6AAF144827B9B2');
        $this->addSql('ALTER TABLE detail_vetement DROP FOREIGN KEY FK_CA6AAF14906D879B');
        $this->addSql('ALTER TABLE detail_vetement DROP FOREIGN KEY FK_CA6AAF14C02A12EE');
        $this->addSql('ALTER TABLE detail_vinyle DROP FOREIGN KEY FK_9E18214B7294869C');
        $this->addSql('ALTER TABLE etiquette DROP FOREIGN KEY FK_1E0E195A58ABF955');
        $this->addSql('ALTER TABLE etiquette DROP FOREIGN KEY FK_1E0E195A73A201E5');
        $this->addSql('ALTER TABLE like_tenue DROP FOREIGN KEY FK_5C35618AFB88E14F');
        $this->addSql('ALTER TABLE like_tenue DROP FOREIGN KEY FK_5C35618A28239C7');
        $this->addSql('ALTER TABLE logement DROP FOREIGN KEY FK_F0FD445776C50E4A');
        $this->addSql('ALTER TABLE membre_logement DROP FOREIGN KEY FK_459FAC6458ABF955');
        $this->addSql('ALTER TABLE membre_logement DROP FOREIGN KEY FK_459FAC64FB88E14F');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F10335F61');
        $this->addSql('ALTER TABLE photo_article DROP FOREIGN KEY FK_37DA19EB7294869C');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B2358ABF955');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE9797294869C');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE9797A87D939');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE979F0840037');
        $this->addSql('ALTER TABLE stockage DROP FOREIGN KEY FK_CABCB492C40FCFA8');
        $this->addSql('ALTER TABLE tenue DROP FOREIGN KEY FK_EB51486D73A201E5');
        $this->addSql('ALTER TABLE tenue_article DROP FOREIGN KEY FK_D2AE4CF328239C7');
        $this->addSql('ALTER TABLE tenue_article DROP FOREIGN KEY FK_D2AE4CF37294869C');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B386EC68D8');
        $this->addSql('DROP TABLE abonnement');
        $this->addSql('DROP TABLE annonce');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_etiquette');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE commentaire');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE couleur');
        $this->addSql('DROP TABLE detail_livre');
        $this->addSql('DROP TABLE detail_vetement');
        $this->addSql('DROP TABLE detail_vinyle');
        $this->addSql('DROP TABLE etiquette');
        $this->addSql('DROP TABLE like_tenue');
        $this->addSql('DROP TABLE logement');
        $this->addSql('DROP TABLE marque');
        $this->addSql('DROP TABLE membre_logement');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE photo_article');
        $this->addSql('DROP TABLE piece');
        $this->addSql('DROP TABLE pret');
        $this->addSql('DROP TABLE stockage');
        $this->addSql('DROP TABLE tenue');
        $this->addSql('DROP TABLE tenue_article');
        $this->addSql('DROP TABLE utilisateur');
    }
}
