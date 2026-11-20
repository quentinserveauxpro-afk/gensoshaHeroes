<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120062618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Infirmerie : séjours des héros (historique, retour au QG, récupération) et fatigue précise du moteur.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sejour_infirmerie (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(40) NOT NULL, type_admission VARCHAR(20) NOT NULL, statut VARCHAR(30) NOT NULL, cle_active INT DEFAULT NULL, admis_le DATETIME NOT NULL, debut_traitement_le DATETIME DEFAULT NULL, sortie_prevue_le DATETIME DEFAULT NULL, retabli_le DATETIME DEFAULT NULL, sorti_le DATETIME DEFAULT NULL, sante_admission INT NOT NULL, fatigue_admission INT NOT NULL, sante_actuelle DOUBLE PRECISION NOT NULL, fatigue_actuelle DOUBLE PRECISION NOT NULL, sante_cible INT NOT NULL, fatigue_cible INT NOT NULL, taux_sante DOUBLE PRECISION NOT NULL, taux_fatigue DOUBLE PRECISION NOT NULL, notes LONGTEXT DEFAULT NULL, latitude_depart NUMERIC(10, 7) DEFAULT NULL, longitude_depart NUMERIC(10, 7) DEFAULT NULL, distance_retour_km DOUBLE PRECISION DEFAULT NULL, vitesse_retour DOUBLE PRECISION DEFAULT NULL, depart_retour_le DATETIME DEFAULT NULL, arrivee_qg_prevue_le DATETIME DEFAULT NULL, arrivee_qg_le DATETIME DEFAULT NULL, dernier_calcul_le DATETIME DEFAULT NULL, alertes_emises JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, hero_id INT NOT NULL, demande_par_id INT DEFAULT NULL, mission_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8296FA69D447CF95 (cle_active), INDEX idx_sejour_infirmerie_statut (statut), INDEX IDX_8296FA6945B0BCD (hero_id), INDEX IDX_8296FA694C0C045 (demande_par_id), INDEX IDX_8296FA69BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sejour_infirmerie ADD CONSTRAINT FK_8296FA6945B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sejour_infirmerie ADD CONSTRAINT FK_8296FA694C0C045 FOREIGN KEY (demande_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sejour_infirmerie ADD CONSTRAINT FK_8296FA69BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mission_simulation_state ADD fatigues JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sejour_infirmerie DROP FOREIGN KEY FK_8296FA6945B0BCD');
        $this->addSql('ALTER TABLE sejour_infirmerie DROP FOREIGN KEY FK_8296FA694C0C045');
        $this->addSql('ALTER TABLE sejour_infirmerie DROP FOREIGN KEY FK_8296FA69BE6CAE90');
        $this->addSql('DROP TABLE sejour_infirmerie');
        $this->addSql('ALTER TABLE mission_simulation_state DROP fatigues');
    }
}
