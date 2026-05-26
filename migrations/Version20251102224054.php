<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251102224054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brand (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, country VARCHAR(120) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', logo VARCHAR(255) DEFAULT NULL, INDEX idx_brand_country (country), UNIQUE INDEX uniq_brand_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE budget_preference (id INT AUTO_INCREMENT NOT NULL, min_cents INT UNSIGNED DEFAULT NULL, max_cents INT UNSIGNED DEFAULT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, firstname VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, dob DATE DEFAULT NULL, picture VARCHAR(255) DEFAULT NULL, language VARCHAR(5) DEFAULT NULL, api_token VARCHAR(1024) DEFAULT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', phone VARCHAR(255) DEFAULT NULL, last_login_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_5D9F75A1E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, family VARCHAR(80) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_note_family (family), UNIQUE INDEX uniq_note_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE perfume (id INT AUTO_INCREMENT NOT NULL, brand_id INT NOT NULL, name VARCHAR(180) NOT NULL, release_year INT DEFAULT NULL, concentration VARCHAR(255) DEFAULT NULL, marketing_gender VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, list_price_cents INT UNSIGNED DEFAULT NULL, list_price_currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_perfume_brand (brand_id), INDEX idx_perfume_price (list_price_cents), UNIQUE INDEX uniq_perfume_brand_name (brand_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE perfume_note (id INT AUTO_INCREMENT NOT NULL, perfume_id INT NOT NULL, note_id INT NOT NULL, layer VARCHAR(10) NOT NULL, intensity INT DEFAULT 3 NOT NULL, INDEX idx_pn_perfume (perfume_id), INDEX idx_pn_note (note_id), UNIQUE INDEX uniq_perfume_note_layer (perfume_id, note_id, layer), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, revoked TINYINT(1) NOT NULL, INDEX IDX_C74F2195A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_brand_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, brand_id INT NOT NULL, weight INT DEFAULT 0 NOT NULL, INDEX idx_ubp_user (user_id), INDEX idx_ubp_brand (brand_id), UNIQUE INDEX uniq_user_brand (user_id, brand_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_budget_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, min_cents INT UNSIGNED DEFAULT NULL, max_cents INT UNSIGNED DEFAULT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_budget_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_concentration_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, concentration VARCHAR(255) NOT NULL, weight INT DEFAULT 3 NOT NULL, INDEX idx_ucp_user (user_id), INDEX idx_ucp_conc (concentration), UNIQUE INDEX uniq_user_concentration (user_id, concentration), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_note_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, note_id INT NOT NULL, weight INT DEFAULT 0 NOT NULL, INDEX idx_unp_user (user_id), INDEX idx_unp_note (note_id), UNIQUE INDEX uniq_user_note (user_id, note_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_occasion_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, occasion VARCHAR(255) NOT NULL, weight INT DEFAULT 3 NOT NULL, INDEX idx_uop_user (user_id), INDEX idx_uop_occasion (occasion), UNIQUE INDEX uniq_user_occasion (user_id, occasion), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_profile (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, gender VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', password VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', email VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_userprofile_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_season_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, season VARCHAR(255) NOT NULL, weight INT DEFAULT 3 NOT NULL, INDEX idx_usp_user (user_id), INDEX idx_usp_season (season), UNIQUE INDEX uniq_user_season (user_id, season), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE perfume ADD CONSTRAINT FK_BD3A04A44F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE perfume_note ADD CONSTRAINT FK_A18B8140AA91F2AA FOREIGN KEY (perfume_id) REFERENCES perfume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE perfume_note ADD CONSTRAINT FK_A18B814026ED0855 FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE user_brand_preference ADD CONSTRAINT FK_1AA3D450A76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_brand_preference ADD CONSTRAINT FK_1AA3D45044F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_budget_preference ADD CONSTRAINT FK_7371C438A76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_concentration_preference ADD CONSTRAINT FK_D28B1D3AA76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_note_preference ADD CONSTRAINT FK_2F12F5ECA76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_note_preference ADD CONSTRAINT FK_2F12F5EC26ED0855 FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_occasion_preference ADD CONSTRAINT FK_EA2C2E47A76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_season_preference ADD CONSTRAINT FK_9548C6DFA76ED395 FOREIGN KEY (user_id) REFERENCES user_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE perfume DROP FOREIGN KEY FK_BD3A04A44F5D008');
        $this->addSql('ALTER TABLE perfume_note DROP FOREIGN KEY FK_A18B8140AA91F2AA');
        $this->addSql('ALTER TABLE perfume_note DROP FOREIGN KEY FK_A18B814026ED0855');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE user_brand_preference DROP FOREIGN KEY FK_1AA3D450A76ED395');
        $this->addSql('ALTER TABLE user_brand_preference DROP FOREIGN KEY FK_1AA3D45044F5D008');
        $this->addSql('ALTER TABLE user_budget_preference DROP FOREIGN KEY FK_7371C438A76ED395');
        $this->addSql('ALTER TABLE user_concentration_preference DROP FOREIGN KEY FK_D28B1D3AA76ED395');
        $this->addSql('ALTER TABLE user_note_preference DROP FOREIGN KEY FK_2F12F5ECA76ED395');
        $this->addSql('ALTER TABLE user_note_preference DROP FOREIGN KEY FK_2F12F5EC26ED0855');
        $this->addSql('ALTER TABLE user_occasion_preference DROP FOREIGN KEY FK_EA2C2E47A76ED395');
        $this->addSql('ALTER TABLE user_season_preference DROP FOREIGN KEY FK_9548C6DFA76ED395');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE budget_preference');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE perfume');
        $this->addSql('DROP TABLE perfume_note');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE user_brand_preference');
        $this->addSql('DROP TABLE user_budget_preference');
        $this->addSql('DROP TABLE user_concentration_preference');
        $this->addSql('DROP TABLE user_note_preference');
        $this->addSql('DROP TABLE user_occasion_preference');
        $this->addSql('DROP TABLE user_profile');
        $this->addSql('DROP TABLE user_season_preference');
    }
}
