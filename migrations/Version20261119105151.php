<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119105151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modèle Genosha Heroes : extensions Hero/Mission, objectifs, affectations, vilains, bonus, synergies, aptitudes, requêtes citoyennes, alertes, utilisateurs';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE affectation_mission (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, date_affectation DATETIME NOT NULL, date_arrivee DATETIME DEFAULT NULL, statut VARCHAR(20) NOT NULL, points_de_vie_au_depart INT NOT NULL, contribution INT DEFAULT NULL, hero_id INT NOT NULL, mission_id INT NOT NULL, INDEX idx_affectation_statut (statut), INDEX IDX_5268DC8345B0BCD (hero_id), INDEX IDX_5268DC83BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE alerte (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, niveau VARCHAR(20) NOT NULL, code VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, acquittee TINYINT NOT NULL, mission_id INT DEFAULT NULL, requete_id INT DEFAULT NULL, INDEX idx_alerte_active (acquittee, created_at), INDEX IDX_3AE753ABE6CAE90 (mission_id), INDEX IDX_3AE753A18FB544F (requete_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE aptitude (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, nom VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_1BB4538877153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bonus (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, nom VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, statistique VARCHAR(20) NOT NULL, valeur_par_defaut INT NOT NULL, UNIQUE INDEX UNIQ_9F987F7A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, rang VARCHAR(255) NOT NULL, points_de_vie INT NOT NULL, stat_combat NUMERIC(5, 2) NOT NULL, stat_intelligence NUMERIC(5, 2) NOT NULL, stat_charisme NUMERIC(5, 2) NOT NULL, stat_mobilite NUMERIC(5, 2) NOT NULL, stat_vigueur NUMERIC(5, 2) NOT NULL, position_x DOUBLE PRECISION NOT NULL, position_y DOUBLE PRECISION NOT NULL, niveau INT NOT NULL, xp INT NOT NULL, description LONGTEXT DEFAULT NULL, alias VARCHAR(255) DEFAULT NULL, specialite VARCHAR(255) DEFAULT NULL, role VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, points_de_vie_maximum INT NOT NULL, fatigue INT NOT NULL, secteur VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, missions_id INT DEFAULT NULL, INDEX IDX_51CE6E8617C042CF (missions_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_aptitude (hero_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_BEAAE22045B0BCD (hero_id), INDEX IDX_BEAAE220D4FDF611 (aptitude_id), PRIMARY KEY (hero_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_bonus (id INT AUTO_INCREMENT NOT NULL, valeur INT NOT NULL, date_debut DATETIME NOT NULL, date_expiration DATETIME DEFAULT NULL, origine VARCHAR(20) NOT NULL, cumulable TINYINT NOT NULL, actif TINYINT NOT NULL, hero_id INT NOT NULL, bonus_id INT NOT NULL, INDEX IDX_A770BD45B0BCD (hero_id), INDEX IDX_A770BD69545666 (bonus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, niveau_de_difficulte VARCHAR(255) NOT NULL, taux_de_reussite INT NOT NULL, timer DATETIME NOT NULL, combat_requis INT DEFAULT NULL, intelligence_requis INT DEFAULT NULL, charisme_requis INT DEFAULT NULL, mobilite_requis INT DEFAULT NULL, vigueur_requis INT DEFAULT NULL, localisation VARCHAR(255) DEFAULT NULL, position_x DOUBLE PRECISION NOT NULL, position_y DOUBLE PRECISION NOT NULL, statut VARCHAR(20) NOT NULL, niveau_urgence VARCHAR(20) NOT NULL, taille_equipe_minimale INT NOT NULL, taille_equipe_maximale INT NOT NULL, seuil_sante_minimal INT NOT NULL, cible VARCHAR(255) DEFAULT NULL, etat_cible VARCHAR(255) DEFAULT NULL, date_debut DATETIME DEFAULT NULL, date_fin_reelle DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission_villain (mission_id INT NOT NULL, villain_id INT NOT NULL, INDEX IDX_D6011FE1BE6CAE90 (mission_id), INDEX IDX_D6011FE1363C6CE2 (villain_id), PRIMARY KEY (mission_id, villain_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mission_aptitude (mission_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_13485231BE6CAE90 (mission_id), INDEX IDX_13485231D4FDF611 (aptitude_id), PRIMARY KEY (mission_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE objectif_mission (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, progression INT NOT NULL, ordre_affichage INT NOT NULL, obligatoire TINYINT NOT NULL, termine TINYINT NOT NULL, mission_id INT NOT NULL, INDEX IDX_6E2FED50BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE requete_citoyenne (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, adresse VARCHAR(255) NOT NULL, position_x DOUBLE PRECISION NOT NULL, position_y DOUBLE PRECISION NOT NULL, niveau_urgence VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, categorie VARCHAR(30) NOT NULL, origine VARCHAR(20) NOT NULL, nom_demandeur VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, mission_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8102E890BE6CAE90 (mission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE synergie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, statistique VARCHAR(20) NOT NULL, valeur_bonus INT NOT NULL, nombre_minimal_heros INT DEFAULT NULL, difficulte_minimale VARCHAR(1) DEFAULT NULL, actif TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE synergie_hero (synergie_id INT NOT NULL, hero_id INT NOT NULL, INDEX IDX_C60A309656D2F6A (synergie_id), INDEX IDX_C60A30945B0BCD (hero_id), PRIMARY KEY (synergie_id, hero_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(120) NOT NULL, fonction VARCHAR(120) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE villain (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, niveau_menace INT NOT NULL, combat INT NOT NULL, intelligence INT NOT NULL, image VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE affectation_mission ADD CONSTRAINT FK_5268DC8345B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE affectation_mission ADD CONSTRAINT FK_5268DC83BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte ADD CONSTRAINT FK_3AE753ABE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE alerte ADD CONSTRAINT FK_3AE753A18FB544F FOREIGN KEY (requete_id) REFERENCES requete_citoyenne (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E8617C042CF FOREIGN KEY (missions_id) REFERENCES mission (id)');
        $this->addSql('ALTER TABLE hero_aptitude ADD CONSTRAINT FK_BEAAE22045B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_aptitude ADD CONSTRAINT FK_BEAAE220D4FDF611 FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_bonus ADD CONSTRAINT FK_A770BD45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero_bonus ADD CONSTRAINT FK_A770BD69545666 FOREIGN KEY (bonus_id) REFERENCES bonus (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission_villain ADD CONSTRAINT FK_D6011FE1BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission_villain ADD CONSTRAINT FK_D6011FE1363C6CE2 FOREIGN KEY (villain_id) REFERENCES villain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission_aptitude ADD CONSTRAINT FK_13485231BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mission_aptitude ADD CONSTRAINT FK_13485231D4FDF611 FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE objectif_mission ADD CONSTRAINT FK_6E2FED50BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE requete_citoyenne ADD CONSTRAINT FK_8102E890BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE synergie_hero ADD CONSTRAINT FK_C60A309656D2F6A FOREIGN KEY (synergie_id) REFERENCES synergie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE synergie_hero ADD CONSTRAINT FK_C60A30945B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE affectation_mission DROP FOREIGN KEY FK_5268DC8345B0BCD');
        $this->addSql('ALTER TABLE affectation_mission DROP FOREIGN KEY FK_5268DC83BE6CAE90');
        $this->addSql('ALTER TABLE alerte DROP FOREIGN KEY FK_3AE753ABE6CAE90');
        $this->addSql('ALTER TABLE alerte DROP FOREIGN KEY FK_3AE753A18FB544F');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E8617C042CF');
        $this->addSql('ALTER TABLE hero_aptitude DROP FOREIGN KEY FK_BEAAE22045B0BCD');
        $this->addSql('ALTER TABLE hero_aptitude DROP FOREIGN KEY FK_BEAAE220D4FDF611');
        $this->addSql('ALTER TABLE hero_bonus DROP FOREIGN KEY FK_A770BD45B0BCD');
        $this->addSql('ALTER TABLE hero_bonus DROP FOREIGN KEY FK_A770BD69545666');
        $this->addSql('ALTER TABLE mission_villain DROP FOREIGN KEY FK_D6011FE1BE6CAE90');
        $this->addSql('ALTER TABLE mission_villain DROP FOREIGN KEY FK_D6011FE1363C6CE2');
        $this->addSql('ALTER TABLE mission_aptitude DROP FOREIGN KEY FK_13485231BE6CAE90');
        $this->addSql('ALTER TABLE mission_aptitude DROP FOREIGN KEY FK_13485231D4FDF611');
        $this->addSql('ALTER TABLE objectif_mission DROP FOREIGN KEY FK_6E2FED50BE6CAE90');
        $this->addSql('ALTER TABLE requete_citoyenne DROP FOREIGN KEY FK_8102E890BE6CAE90');
        $this->addSql('ALTER TABLE synergie_hero DROP FOREIGN KEY FK_C60A309656D2F6A');
        $this->addSql('ALTER TABLE synergie_hero DROP FOREIGN KEY FK_C60A30945B0BCD');
        $this->addSql('DROP TABLE affectation_mission');
        $this->addSql('DROP TABLE alerte');
        $this->addSql('DROP TABLE aptitude');
        $this->addSql('DROP TABLE bonus');
        $this->addSql('DROP TABLE hero');
        $this->addSql('DROP TABLE hero_aptitude');
        $this->addSql('DROP TABLE hero_bonus');
        $this->addSql('DROP TABLE mission');
        $this->addSql('DROP TABLE mission_villain');
        $this->addSql('DROP TABLE mission_aptitude');
        $this->addSql('DROP TABLE objectif_mission');
        $this->addSql('DROP TABLE requete_citoyenne');
        $this->addSql('DROP TABLE synergie');
        $this->addSql('DROP TABLE synergie_hero');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE villain');
    }
}
