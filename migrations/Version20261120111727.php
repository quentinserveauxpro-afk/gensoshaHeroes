<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120111727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signalement citoyen : prénom, anonymat, super-vilain signalé et compte auteur (colonnes additives).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE requete_citoyenne ADD prenom_demandeur VARCHAR(120) DEFAULT NULL, ADD anonyme TINYINT DEFAULT 0 NOT NULL, ADD villain_signale_id INT DEFAULT NULL, ADD citoyen_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E8907FEE64D8 FOREIGN KEY (villain_signale_id) REFERENCES villain (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E89043787BBA FOREIGN KEY (citoyen_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8102E8907FEE64D8 ON requete_citoyenne (villain_signale_id)');
        $this->addSql('CREATE INDEX IDX_8102E89043787BBA ON requete_citoyenne (citoyen_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E8907FEE64D8');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E89043787BBA');
        $this->addSql('DROP INDEX IDX_8102E8907FEE64D8 ON requete_citoyenne');
        $this->addSql('DROP INDEX IDX_8102E89043787BBA ON requete_citoyenne');
        $this->addSql('ALTER TABLE requete_citoyenne DROP prenom_demandeur, DROP anonyme, DROP villain_signale_id, DROP citoyen_id');
    }
}
