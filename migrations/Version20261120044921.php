<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120044921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simulation : progression décimale des objectifs et contributions des héros (ajout de colonnes)';
    }

    public function up(Schema $schema): void
    {
        // Migration strictement additive : aucune colonne ni table existante n'est modifiée ou supprimée.
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245FBE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245F6D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation_mission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mission_simulation_state ADD progressions JSON NOT NULL, ADD contributions JSON NOT NULL');
        $this->addSql('ALTER TABLE mission_simulation_state ADD CONSTRAINT FK_76001A4BBE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE requete_historique ADD CONSTRAINT FK_4F88B59D18FB544F FOREIGN KEY (requete_id) REFERENCES requete_citoyenne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE requete_historique ADD CONSTRAINT FK_4F88B59D3F192FC FOREIGN KEY (operateur_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245F45B0BCD');
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245FBE6CAE90');
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245F6D0ABA22');
        $this->addSql('ALTER TABLE mission_simulation_state DROP FOREIGN KEY FK_76001A4BBE6CAE90');
        $this->addSql('ALTER TABLE mission_simulation_state DROP progressions, DROP contributions');
        $this->addSql('ALTER TABLE requete_historique DROP FOREIGN KEY FK_4F88B59D18FB544F');
        $this->addSql('ALTER TABLE requete_historique DROP FOREIGN KEY FK_4F88B59D3F192FC');
    }
}
