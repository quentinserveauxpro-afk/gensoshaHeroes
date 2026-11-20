<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120044209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carte réelle (GeoPosition), déplacements (HeroMovement), moteur de simulation, requêtes publiques et traitement, comptes opérateurs — ajouts uniquement';
    }

    public function up(Schema $schema): void
    {
        // Migration strictement additive : aucune colonne ni table existante n'est modifiée ou supprimée.
        $this->addSql('CREATE TABLE geo_position (id INT AUTO_INCREMENT NOT NULL, latitude NUMERIC(10, 7) NOT NULL, longitude NUMERIC(10, 7) NOT NULL, adresse_saisie VARCHAR(255) DEFAULT NULL, libelle VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(120) DEFAULT NULL, source VARCHAR(20) NOT NULL, fournisseur VARCHAR(40) DEFAULT NULL, updated_at DATETIME NOT NULL, INDEX idx_geo_position_coords (latitude, longitude), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_movement (id INT AUTO_INCREMENT NOT NULL, latitude_depart NUMERIC(10, 7) NOT NULL, longitude_depart NUMERIC(10, 7) NOT NULL, latitude_arrivee NUMERIC(10, 7) NOT NULL, longitude_arrivee NUMERIC(10, 7) NOT NULL, latitude_actuelle NUMERIC(10, 7) NOT NULL, longitude_actuelle NUMERIC(10, 7) NOT NULL, distance_km DOUBLE PRECISION NOT NULL, date_depart DATETIME NOT NULL, date_arrivee_prevue DATETIME NOT NULL, date_arrivee_reelle DATETIME DEFAULT NULL, vitesse DOUBLE PRECISION NOT NULL, progression DOUBLE PRECISION NOT NULL, statut VARCHAR(20) NOT NULL, updated_at DATETIME NOT NULL, hero_id INT NOT NULL, mission_id INT NOT NULL, affectation_id INT DEFAULT NULL, INDEX idx_hero_movement_statut (statut), INDEX IDX_51C3245F45B0BCD (hero_id), INDEX IDX_51C3245FBE6CAE90 (mission_id), INDEX IDX_51C3245F6D0ABA22 (affectation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission_simulation_state (id INT AUTO_INCREMENT NOT NULL, dernier_tick DATETIME NOT NULL, nombre_etapes INT NOT NULL, date_engagement DATETIME DEFAULT NULL, menace_initiale DOUBLE PRECISION DEFAULT NULL, menace_actuelle DOUBLE PRECISION NOT NULL, taux_reussite_direct INT NOT NULL, alertes_sante JSON NOT NULL, journal JSON NOT NULL, issue VARCHAR(20) DEFAULT NULL, updated_at DATETIME NOT NULL, mission_id INT NOT NULL, UNIQUE INDEX UNIQ_76001A4BBE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE piece_jointe (id INT AUTO_INCREMENT NOT NULL, nom_original VARCHAR(150) NOT NULL, nom_stockage VARCHAR(80) NOT NULL, type_mime VARCHAR(100) NOT NULL, taille INT NOT NULL, empreinte_sha256 VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_AB5111D45FF6711B (nom_stockage), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE requete_historique (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(40) NOT NULL, ancien_statut VARCHAR(30) DEFAULT NULL, nouveau_statut VARCHAR(30) NOT NULL, commentaire LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, requete_id INT NOT NULL, operateur_id INT DEFAULT NULL, INDEX idx_requete_historique_date (created_at), INDEX IDX_4F88B59D18FB544F (requete_id), INDEX IDX_4F88B59D3F192FC (operateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245FBE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_movement ADD CONSTRAINT FK_51C3245F6D0ABA22 FOREIGN KEY (affectation_id) REFERENCES affectation_mission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mission_simulation_state ADD CONSTRAINT FK_76001A4BBE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE requete_historique ADD CONSTRAINT FK_4F88B59D18FB544F FOREIGN KEY (requete_id) REFERENCES requete_citoyenne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE requete_historique ADD CONSTRAINT FK_4F88B59D3F192FC FOREIGN KEY (operateur_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero ADD geo_position_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E86D7FDFDE6 FOREIGN KEY (geo_position_id) REFERENCES geo_position (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_51CE6E86D7FDFDE6 ON hero (geo_position_id)');
        $this->addSql('ALTER TABLE mission ADD geo_position_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mission ADD CONSTRAINT FK_9067F23CD7FDFDE6 FOREIGN KEY (geo_position_id) REFERENCES geo_position (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9067F23CD7FDFDE6 ON mission (geo_position_id)');
        $this->addSql('ALTER TABLE objectif_mission ADD statistique_cle VARCHAR(20) DEFAULT NULL, ADD date_accomplissement DATETIME DEFAULT NULL, ADD aptitude_requise_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE objectif_mission ADD CONSTRAINT FK_6E2FED50150AED21 FOREIGN KEY (aptitude_requise_id) REFERENCES aptitude (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6E2FED50150AED21 ON objectif_mission (aptitude_requise_id)');
        $this->addSql('ALTER TABLE requete_citoyenne ADD categorie_incident VARCHAR(30) DEFAULT NULL, ADD statut_traitement VARCHAR(30) DEFAULT NULL, ADD urgence_declaree VARCHAR(20) DEFAULT NULL, ADD urgence_aqualifier TINYINT DEFAULT 0 NOT NULL, ADD soumission_publique TINYINT DEFAULT 0 NOT NULL, ADD complement_adresse VARCHAR(255) DEFAULT NULL, ADD code_postal VARCHAR(10) DEFAULT NULL, ADD ville VARCHAR(120) DEFAULT NULL, ADD secteur VARCHAR(60) DEFAULT NULL, ADD email_demandeur VARCHAR(180) DEFAULT NULL, ADD telephone_demandeur VARCHAR(30) DEFAULT NULL, ADD consentement_le DATETIME DEFAULT NULL, ADD informations_complementaires LONGTEXT DEFAULT NULL, ADD motif_rejet LONGTEXT DEFAULT NULL, ADD date_dernier_traitement DATETIME DEFAULT NULL, ADD date_conversion DATETIME DEFAULT NULL, ADD traitee_par_id INT DEFAULT NULL, ADD convertie_par_id INT DEFAULT NULL, ADD geo_position_id INT DEFAULT NULL, ADD piece_jointe_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E8903338F50D FOREIGN KEY (traitee_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E89014DE7F11 FOREIGN KEY (convertie_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E890D7FDFDE6 FOREIGN KEY (geo_position_id) REFERENCES geo_position (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E890A3741A05 FOREIGN KEY (piece_jointe_id) REFERENCES piece_jointe (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8102E890D7FDFDE6 ON requete_citoyenne (geo_position_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8102E890A3741A05 ON requete_citoyenne (piece_jointe_id)');
        $this->addSql('CREATE INDEX IDX_8102E8903338F50D ON requete_citoyenne (traitee_par_id)');
        $this->addSql('CREATE INDEX IDX_8102E89014DE7F11 ON requete_citoyenne (convertie_par_id)');
        $this->addSql('ALTER TABLE `user` ADD actif TINYINT DEFAULT 1 NOT NULL, ADD mot_de_passe_modifie_le DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245F45B0BCD');
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245FBE6CAE90');
        $this->addSql('ALTER TABLE hero_movement DROP FOREIGN KEY FK_51C3245F6D0ABA22');
        $this->addSql('ALTER TABLE mission_simulation_state DROP FOREIGN KEY FK_76001A4BBE6CAE90');
        $this->addSql('ALTER TABLE requete_historique DROP FOREIGN KEY FK_4F88B59D18FB544F');
        $this->addSql('ALTER TABLE requete_historique DROP FOREIGN KEY FK_4F88B59D3F192FC');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E86D7FDFDE6');
        $this->addSql('ALTER TABLE mission DROP FOREIGN KEY FK_9067F23CD7FDFDE6');
        $this->addSql('ALTER TABLE objectif_mission DROP FOREIGN KEY FK_6E2FED50150AED21');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E8903338F50D');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E89014DE7F11');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E890D7FDFDE6');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E890A3741A05');
        $this->addSql('DROP TABLE geo_position');
        $this->addSql('DROP TABLE hero_movement');
        $this->addSql('DROP TABLE mission_simulation_state');
        $this->addSql('DROP TABLE piece_jointe');
        $this->addSql('DROP TABLE requete_historique');
        $this->addSql('DROP INDEX UNIQ_51CE6E86D7FDFDE6 ON hero');
        $this->addSql('ALTER TABLE hero DROP geo_position_id');
        $this->addSql('DROP INDEX UNIQ_9067F23CD7FDFDE6 ON mission');
        $this->addSql('ALTER TABLE mission DROP geo_position_id');
        $this->addSql('DROP INDEX IDX_6E2FED50150AED21 ON objectif_mission');
        $this->addSql('ALTER TABLE objectif_mission DROP statistique_cle, DROP date_accomplissement, DROP aptitude_requise_id');
        $this->addSql('DROP INDEX UNIQ_8102E890D7FDFDE6 ON requete_citoyenne');
        $this->addSql('DROP INDEX UNIQ_8102E890A3741A05 ON requete_citoyenne');
        $this->addSql('DROP INDEX IDX_8102E8903338F50D ON requete_citoyenne');
        $this->addSql('DROP INDEX IDX_8102E89014DE7F11 ON requete_citoyenne');
        $this->addSql('ALTER TABLE requete_citoyenne DROP categorie_incident, DROP statut_traitement, DROP urgence_declaree, DROP urgence_aqualifier, DROP soumission_publique, DROP complement_adresse, DROP code_postal, DROP ville, DROP secteur, DROP email_demandeur, DROP telephone_demandeur, DROP consentement_le, DROP informations_complementaires, DROP motif_rejet, DROP date_dernier_traitement, DROP date_conversion, DROP traitee_par_id, DROP convertie_par_id, DROP geo_position_id, DROP piece_jointe_id');
        $this->addSql('ALTER TABLE `user` DROP actif, DROP mot_de_passe_modifie_le');
    }
}
